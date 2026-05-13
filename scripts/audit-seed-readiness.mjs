import fs from 'node:fs';
import path from 'node:path';

const root = process.cwd();
const failures = [];

const autoseed = readFile('wp-content/mu-plugins/kepoli-autoseed.php');
const seedBootstrap = readFile('seed/bootstrap.php');
const compose = readFile('docker-compose.yml');
const envExample = readFile('.env.example');
const coolifyDocs = readFile('docs/coolify.md');
const readme = readFile('README.md');

checkAutoseed();
checkEnvContract();
checkDocs();

if (failures.length === 0) {
  console.log('Seed readiness OK.');
} else {
  console.log(`Seed readiness found ${failures.length} required fix${failures.length === 1 ? '' : 'es'}.`);
  console.log('\nRequired fixes:');
  for (const failure of failures) {
    console.log(`- ${failure}`);
  }
}

process.exit(failures.length > 0 ? 1 : 0);

function readFile(relativePath) {
  const absolutePath = path.join(root, relativePath);
  if (!fs.existsSync(absolutePath)) {
    failures.push(`Missing file: ${relativePath}`);
    return '';
  }

  return fs.readFileSync(absolutePath, 'utf8');
}

function requireIncludes(label, content, patterns) {
  for (const pattern of patterns) {
    if (!pattern.test(content)) {
      failures.push(`${label} is missing: ${pattern}`);
    }
  }
}

function checkAutoseed() {
  requireIncludes('wp-content/mu-plugins/kepoli-autoseed.php first-launch guard', autoseed, [
    /KEPOLI_AUTOSEED_ENABLE/,
    /KEPOLI_FORCE_RESEED/,
    /kepoli_autoseed_has_real_content/,
    /get_option\('kepoli_seed_version'/,
    /!\$force_reseed\s*&&\s*\$current_version\s*!==\s*''/,
    /!\$force_reseed\s*&&\s*kepoli_autoseed_has_real_content\(\)/,
  ]);

  requireIncludes('social plugins activation', autoseed + '\n' + seedBootstrap, [
    /kepoli_autoseed_activate_plugin\('kepoli-author-tools\/kepoli-author-tools\.php'\)/,
    /kepoli_autoseed_activate_plugin\('dr-purg-social-syndicator\/dr-purg-social-syndicator\.php'\)/,
    /kepoli_seed_activate_plugin\('kepoli-author-tools\/kepoli-author-tools\.php'\)/,
    /kepoli_seed_activate_plugin\('dr-purg-social-syndicator\/dr-purg-social-syndicator\.php'\)/,
  ]);

  const oldRerunPattern = /get_option\('kepoli_seed_version'\)\s*===\s*\$target_version[\s\S]{0,220}require\s+['"]\/seed\/bootstrap\.php['"]/;
  if (oldRerunPattern.test(autoseed) && !/KEPOLI_FORCE_RESEED/.test(autoseed)) {
    failures.push('Auto-seed looks like it can rerun on seed fingerprint changes without an explicit force flag.');
  }
}

function checkEnvContract() {
  requireIncludes('.env.example seed controls', envExample, [
    /KEPOLI_AUTOSEED_ENABLE=1/,
    /KEPOLI_FORCE_RESEED=0/,
  ]);

  requireIncludes('docker-compose.yml seed controls', compose, [
    /KEPOLI_AUTOSEED_ENABLE:\s*\$\{KEPOLI_AUTOSEED_ENABLE:-1\}/,
    /KEPOLI_FORCE_RESEED:\s*\$\{KEPOLI_FORCE_RESEED:-0\}/,
  ]);
}

function checkDocs() {
  requireIncludes('docs/coolify.md seed policy', coolifyDocs, [
    /self-seeds only on a fresh install/i,
    /Seed content is only for first launch/i,
    /KEPOLI_FORCE_RESEED=1/,
    /KEPOLI_FORCE_RESEED=0/,
    /future deploys do not touch posts again/i,
  ]);

  requireIncludes('README.md seed policy', readme, [
    /first launch/i,
    /KEPOLI_FORCE_RESEED/,
  ]);
}
