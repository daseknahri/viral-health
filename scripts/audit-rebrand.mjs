import fs from 'node:fs';
import path from 'node:path';

const root = process.cwd();
const args = parseArgs(process.argv.slice(2));

if (args.help || args.h) {
  console.log(`Usage: node scripts/audit-rebrand.mjs
node scripts/audit-rebrand.mjs --old-brand "Old Site" --old-domain old-domain.com --old-email contact@old-domain.com

Checks a cloned blog for old public identity and launch-content leftovers.
Run it after changing the new site's brand, domain, author, legal pages, posts, and images.

The script ignores internal function/class prefixes such as kepoli_ by default.
It flags public-facing old names, emails, domains, AdSense IDs, seed content, and old launch slugs.

Options:
  --old-brand   Previous public brand to scan for, for example "Old Site"
  --old-domain  Previous public domain to scan for, for example old-domain.com
  --old-email   Previous public contact email to scan for`);
  process.exit(0);
}

const ignorePaths = new Set([
  normalizePath('docs/replicate-food-blog.md'),
  normalizePath('scripts/audit-rebrand.mjs'),
  normalizePath('scripts/prepare-replica.mjs'),
  normalizePath('scripts/reset-replica-content.mjs'),
  normalizePath('scripts/audit-replica-readiness.mjs'),
  normalizePath('scripts/generate-replica-shell.mjs'),
]);

const ignoredDirectoryParts = new Set([
  '.git',
  'node_modules',
  'vendor',
]);

const textExtensions = new Set([
  '',
  '.conf',
  '.css',
  '.example',
  '.js',
  '.json',
  '.md',
  '.mjs',
  '.php',
  '.sh',
  '.txt',
  '.yaml',
  '.yml',
]);

const scanRoots = [
  '.env.example',
  'docker-compose.yml',
  'README.md',
  'docs',
  'content/site-profile.json',
  'content/categories.json',
  'content/pages.json',
  'content/posts.json',
  'content/image-plan.json',
  'seed',
  'wp-content/themes/kepoli',
  'wp-content/mu-plugins',
  'wp-content/plugins/kepoli-author-tools',
  'wp-content/plugins/dr-purg-social-syndicator',
];

const identityPatterns = [
  ['old brand name', /\bKepoli\b/g],
  ['old domain', /\bkepoli\.com\b/gi],
  ['old contact email', /contact@kepoli\.com/gi],
  ['old AdSense client ID', /ca-pub-8166411196603757/g],
  ['old AdSense publisher ID', /pub-8166411196603757/g],
];

if (args['old-brand']) {
  identityPatterns.push(['previous brand name', new RegExp(escapeRegExp(args['old-brand']), 'gi')]);
}

if (args['old-domain']) {
  identityPatterns.push(['previous domain', new RegExp(escapeRegExp(args['old-domain']), 'gi')]);
}

if (args['old-email']) {
  identityPatterns.push(['previous contact email', new RegExp(escapeRegExp(args['old-email']), 'gi')]);
}

const infrastructurePatterns = [
  ['old WordPress image tag', /\bkepoli-wordpress\b/i],
  ['old WP-CLI image tag', /\bkepoli-wp-cli\b/i],
  ['old database volume', /\bkepoli_db\b/],
  ['old WordPress volume', /\bkepoli_wordpress\b/],
  ['old uploads volume', /\bkepoli_uploads\b/],
  ['old DB name', /\bWORDPRESS_DB_NAME=kepoli\b/],
  ['old DB user', /\bWORDPRESS_DB_USER=kepoli\b/],
];

const oldLaunchSlugs = new Set([
  'ardei-umpluti',
  'calendarul-gusturilor-de-sezon',
  'ciorba-de-burta',
  'ciorba-de-fasole-cu-afumatura',
  'ciorba-de-perisoare',
  'ciorba-radauteana',
  'clatite-cu-dulceata',
  'cornulete-fragede',
  'cozonac-cu-nuca',
  'cum-alegi-ingredientele-pentru-retete-romanesti',
  'cum-pastrezi-mancarea-gatita',
  'ghidul-camarii-romanesti',
  'gogosi-pufoase',
  'iahnie-de-fasole',
  'mamaliga-cu-branza-si-smantana',
  'meniu-romanesc-de-duminica',
  'muraturi-asortate',
  'ostropel-de-pui',
  'papanasi-prajiti',
  'placinta-cu-mere',
  'salam-de-biscuiti',
  'salata-de-vinete',
  'sarmale-in-foi-de-varza',
  'supa-crema-de-legume-de-iarna',
  'supa-de-pui-cu-galuste',
  'tehnici-simple-pentru-aluaturi-si-baze',
  'tocanita-de-pui-cu-mamaliga',
  'tochitura-moldoveneasca',
  'varza-calita-cu-carnati',
  'zacusca-de-vinete',
]);

const failures = [];
const warnings = [];

function parseArgs(argv) {
  const parsed = {};

  for (let index = 0; index < argv.length; index += 1) {
    const item = argv[index];
    if (!item.startsWith('--')) continue;

    const raw = item.slice(2);
    const equalIndex = raw.indexOf('=');
    if (equalIndex !== -1) {
      parsed[raw.slice(0, equalIndex)] = raw.slice(equalIndex + 1);
      continue;
    }

    const next = argv[index + 1];
    if (!next || next.startsWith('--')) {
      parsed[raw] = true;
      continue;
    }

    parsed[raw] = next;
    index += 1;
  }

  return parsed;
}

function escapeRegExp(value) {
  return String(value).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

function normalizePath(value) {
  return value.replace(/\\/g, '/');
}

function relativePath(filePath) {
  return normalizePath(path.relative(root, filePath));
}

function shouldIgnore(filePath) {
  const relative = relativePath(filePath);
  if (ignorePaths.has(relative)) return true;

  const parts = relative.split('/');
  if (parts.some((part) => ignoredDirectoryParts.has(part))) return true;
  if (parts.includes('images')) return true;
  if (/\.(min\.css|min\.js)$/i.test(relative)) return true;

  return false;
}

function isLikelyText(filePath) {
  return textExtensions.has(path.extname(filePath).toLowerCase());
}

function collectFiles(targetPath) {
  const absolutePath = path.resolve(root, targetPath);
  if (!fs.existsSync(absolutePath)) return [];

  const stat = fs.statSync(absolutePath);
  if (stat.isFile()) {
    return shouldIgnore(absolutePath) || !isLikelyText(absolutePath) ? [] : [absolutePath];
  }

  const files = [];
  for (const entry of fs.readdirSync(absolutePath, { withFileTypes: true })) {
    const entryPath = path.join(absolutePath, entry.name);
    if (shouldIgnore(entryPath)) continue;

    if (entry.isDirectory()) {
      files.push(...collectFiles(entryPath));
    } else if (entry.isFile() && isLikelyText(entryPath)) {
      files.push(entryPath);
    }
  }

  return files;
}

function addLineIssue(collection, filePath, lineNumber, label, line) {
  collection.push(`${relativePath(filePath)}:${lineNumber} ${label}: ${line.trim()}`);
}

function scanTextFiles() {
  const files = [...new Set(scanRoots.flatMap(collectFiles))].sort();

  for (const file of files) {
    const relative = relativePath(file);
    const content = fs.readFileSync(file, 'utf8');
    const lines = content.split(/\r?\n/);

    lines.forEach((line, index) => {
      for (const [label, pattern] of identityPatterns) {
        pattern.lastIndex = 0;
        if (pattern.test(line)) {
          addLineIssue(failures, file, index + 1, label, line);
        }
      }

      if (relative === '.env.example' || relative === 'docker-compose.yml' || relative === 'README.md') {
        for (const [label, pattern] of infrastructurePatterns) {
          pattern.lastIndex = 0;
          if (pattern.test(line)) {
            addLineIssue(warnings, file, index + 1, label, line);
          }
        }
      }
    });
  }
}

function scanLaunchContent() {
  const postsPath = path.join(root, 'content/posts.json');
  if (fs.existsSync(postsPath)) {
    try {
      const posts = JSON.parse(fs.readFileSync(postsPath, 'utf8'));
      for (const post of posts) {
        if (oldLaunchSlugs.has(post?.slug)) {
          failures.push(`content/posts.json old Kepoli launch post still present: ${post.slug}`);
        }
      }
    } catch (error) {
      warnings.push(`content/posts.json could not be parsed: ${error.message}`);
    }
  }

  const imagesPath = path.join(root, 'content/images');
  if (fs.existsSync(imagesPath)) {
    const files = fs.readdirSync(imagesPath).filter((file) => /\.(jpe?g|png|webp)$/i.test(file));
    for (const file of files) {
      const slug = file.replace(/\.(jpe?g|png|webp)$/i, '');
      if (oldLaunchSlugs.has(slug)) {
        failures.push(`content/images old Kepoli launch image still present: ${file}`);
      }
    }
  }
}

function printSection(title, items, limit = 80) {
  if (items.length === 0) return;
  console.log(`\n${title}`);

  for (const item of items.slice(0, limit)) {
    console.log(`- ${item}`);
  }

  if (items.length > limit) {
    console.log(`- ...and ${items.length - limit} more`);
  }
}

scanTextFiles();
scanLaunchContent();

if (failures.length === 0 && warnings.length === 0) {
  console.log('Rebrand audit passed. No old public identity or launch-content leftovers found.');
  process.exit(0);
}

if (failures.length > 0) {
  console.log(`Rebrand audit found ${failures.length} required fix${failures.length === 1 ? '' : 'es'}.`);
}

if (warnings.length > 0) {
  console.log(`Rebrand audit found ${warnings.length} infrastructure warning${warnings.length === 1 ? '' : 's'}.`);
}

printSection('Required fixes', failures);
printSection('Infrastructure warnings', warnings);

if (warnings.length > 0) {
  console.log('\nWarnings are not public content, but renaming them helps avoid collisions when several cloned blogs run on one server.');
}

process.exit(failures.length > 0 ? 1 : 0);
