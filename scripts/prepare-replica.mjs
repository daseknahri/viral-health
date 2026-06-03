import fs from 'node:fs';
import path from 'node:path';

const root = process.cwd();
const failures = [];
const args = parseArgs(process.argv.slice(2));

if (args.help || args.h) {
  printHelp();
  process.exit(0);
}

const write = Boolean(args.write);
const brand = stringArg('brand');
const domain = stringArg('domain');
const siteEmail = stringArg('site-email');
const writerName = stringArg('writer-name');
const writerEmail = stringArg('writer-email');

if (failures.length > 0) {
  console.error('Replica preparation needs a little more information:');
  for (const failure of failures) console.error(`- ${failure}`);
  console.error('\nRun with --help to see an example.');
  process.exit(1);
}

const siteUrl = normalizeSiteUrl(domain);
const hostname = new URL(siteUrl).hostname;
const projectSlug = slugify(args['project-slug'] || brand);
const projectUnderscore = projectSlug.replace(/-/g, '_');
const writerParts = splitName(writerName);
const language = resolveLanguage(args.language || args.lang || '', args['wp-locale']);
const wpLocale = args['wp-locale'] || (language === 'en' ? 'en_US' : 'ro_RO');
const wpAdminLocale = 'en_US';
const homeSlug = slugify(args['home-slug'] || defaultHomeSlug(language));
const authorSlug = slugify(args['author-slug'] || defaultAuthorSlug(language));
const aboutSlug = slugify(args['about-slug'] || defaultAboutSlug(language, projectSlug));
const recipesSlug = slugify(args['recipes-slug'] || defaultRecipesSlug(language));
const guidesSlug = slugify(args['guides-slug'] || defaultGuidesSlug(language));
const privacySlug = slugify(args['privacy-slug'] || defaultPrivacySlug(language));
const cookiesSlug = slugify(args['cookies-slug'] || defaultCookiesSlug(language));
const advertisingSlug = slugify(args['advertising-slug'] || defaultAdvertisingSlug(language));
const editorialSlug = slugify(args['editorial-slug'] || defaultEditorialSlug(language));
const termsSlug = slugify(args['terms-slug'] || defaultTermsSlug(language));
const disclaimerSlug = slugify(args['disclaimer-slug'] || defaultDisclaimerSlug(language));
const brandTagline = args['brand-tagline'] || defaultBrandTagline(language, brand);
const brandDescription = args['brand-description'] || defaultBrandDescription(language, brand);
const writerBio = args['writer-bio'] || defaultWriterBio(language, brand, writerName);
const writerGravatarUrl = args['writer-gravatar-url'] || '';
const canonicalHosts = args['canonical-hosts'] || `www.${hostname}`;
const adsenseClientId = args['adsense-client-id'] || '';
const adsensePubId = args['adsense-pub-id'] || '';
const gaMeasurementId = args['ga-measurement-id'] || '';
const themeDescription = args['theme-description']
  || (language === 'en'
    ? `A lightweight food blog theme for ${brand}, built for recipes, food guides, internal linking, and ad-ready spacing.`
    : `A lightweight food blog theme for ${brand}, built for recipes, editorial reading, internal linking, and ad-ready spacing.`);

const operations = [];

updateSiteProfile();
updateEnvExample();
updateDockerCompose();
updateThemeHeader();
updatePublicIdentityFiles();
updateThemeAssetNames();

if (failures.length > 0) {
  console.error('Replica preparation could not continue:');
  for (const failure of failures) console.error(`- ${failure}`);
  process.exit(1);
}

if (operations.length === 0) {
  console.log('No replica changes needed.');
  process.exit(0);
}

console.log(`${write ? 'Applied' : 'Planned'} ${operations.length} replica change${operations.length === 1 ? '' : 's'}:`);
for (const operation of operations) {
  console.log(`- ${operation}`);
}

if (!write) {
  console.log('\nDry run only. Add --write to apply these changes in the cloned repo.');
}

function printHelp() {
  console.log(`Usage:
node scripts/prepare-replica.mjs --brand "New Blog" --domain https://new-domain.com --site-email contact@new-domain.com --writer-name "Writer Name" --writer-email writer@example.com --project-slug new-blog --language en --write

Required:
  --brand             Public site name
  --domain            Canonical site URL or domain
  --site-email        Public site email
  --writer-name       Public writer name
  --writer-email      Public writer email

Optional:
  --language          Language preset: en or ro. Defaults from --wp-locale, otherwise ro.
  --brand-tagline     Public tagline written to content/site-profile.json
  --brand-description Public brand description written to content/site-profile.json
  --writer-bio        Public writer bio written to content/site-profile.json
  --writer-gravatar-url Public Gravatar profile URL, optional
  --project-slug      Internal project slug for Docker image, DB, and volume names
  --home-slug         Home page slug, default: home or acasa
  --canonical-hosts   Extra hosts that should redirect to the canonical domain
  --about-slug        About-site page slug, default: about-{project-slug} for English or despre-{project-slug} for Romanian
  --author-slug       Author page slug, default: about-author for English or despre-autor for Romanian
  --recipes-slug      Recipes landing page slug, default: recipes or retete
  --guides-slug       Guides/articles landing page slug, default: guides or articole
  --privacy-slug      Privacy page slug, default: privacy-policy or politica-de-confidentialitate
  --cookies-slug      Cookie page slug, default: cookie-policy or politica-de-cookies
  --advertising-slug  Advertising/consent page slug
  --editorial-slug    Editorial policy page slug
  --terms-slug        Terms page slug
  --disclaimer-slug   Culinary disclaimer page slug
  --wp-locale         WordPress public locale, default: en_US for English or ro_RO for Romanian
  --wp-admin-locale   Deprecated. Admin locale is always forced to en_US
  --adsense-client-id AdSense client ID, usually blank until the new site is ready
  --adsense-pub-id    AdSense publisher ID, usually blank until the new site is ready
  --ga-measurement-id GA4 measurement ID, usually blank until consent is ready
  --write             Apply changes. Without this, the script only reports planned changes.`);
}

function parseArgs(argv) {
  const parsed = {};

  for (let index = 0; index < argv.length; index += 1) {
    const item = argv[index];
    if (!item.startsWith('--')) {
      failures.push(`Unexpected argument: ${item}`);
      continue;
    }

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

function stringArg(name) {
  const value = args[name];
  if (typeof value !== 'string' || value.trim() === '') {
    failures.push(`Missing required --${name}`);
    return '';
  }

  return value.trim();
}

function normalizeSiteUrl(value) {
  const withProtocol = /^https?:\/\//i.test(value) ? value : `https://${value}`;
  return withProtocol.replace(/\/+$/, '');
}

function slugify(value) {
  const slug = String(value || '')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '');

  return slug || 'food-blog';
}

function splitName(value) {
  const parts = value.trim().split(/\s+/);
  return {
    first: parts[0] || value,
    last: parts.slice(1).join(' '),
  };
}

function resolveLanguage(value, locale) {
  const raw = String(value || locale || '').trim().toLowerCase();
  if (raw.startsWith('en')) return 'en';
  return 'ro';
}

function defaultAuthorSlug(languageCode) {
  return languageCode === 'en' ? 'about-author' : 'despre-autor';
}

function defaultHomeSlug(languageCode) {
  return languageCode === 'en' ? 'home' : 'acasa';
}

function defaultAboutSlug(languageCode, slug) {
  return languageCode === 'en' ? `about-${slug}` : `despre-${slug}`;
}

function defaultRecipesSlug(languageCode) {
  return languageCode === 'en' ? 'recipes' : 'retete';
}

function defaultGuidesSlug(languageCode) {
  return languageCode === 'en' ? 'guides' : 'articole';
}

function defaultPrivacySlug(languageCode) {
  return languageCode === 'en' ? 'privacy-policy' : 'politica-de-confidentialitate';
}

function defaultCookiesSlug(languageCode) {
  return languageCode === 'en' ? 'cookie-policy' : 'politica-de-cookies';
}

function defaultAdvertisingSlug(languageCode) {
  return languageCode === 'en' ? 'advertising-and-consent' : 'publicitate-si-consimtamant';
}

function defaultEditorialSlug(languageCode) {
  return languageCode === 'en' ? 'editorial-policy' : 'politica-editoriala';
}

function defaultTermsSlug(languageCode) {
  return languageCode === 'en' ? 'terms-and-conditions' : 'termeni-si-conditii';
}

function defaultDisclaimerSlug(languageCode) {
  return languageCode === 'en' ? 'culinary-disclaimer' : 'disclaimer-culinar';
}

function defaultBrandTagline(languageCode, siteName) {
  return languageCode === 'en'
    ? `${siteName} shares practical recipes and kitchen guides for home cooks.`
    : `${siteName} publica retete pentru acasa si ghiduri practice de bucatarie.`;
}

function defaultBrandDescription(languageCode, siteName) {
  return languageCode === 'en'
    ? `${siteName} publishes recipes, food guides, and practical kitchen notes for readers who cook at home.`
    : `${siteName} publica retete, articole culinare si ghiduri practice pentru cititori care gatesc acasa.`;
}

function defaultWriterBio(languageCode, siteName, authorName) {
  return languageCode === 'en'
    ? `${authorName} writes practical recipes and kitchen guides for ${siteName}.`
    : `${authorName} scrie retete si ghiduri practice pentru ${siteName}.`;
}

function filePath(relativePath) {
  return path.join(root, relativePath);
}

function readFile(relativePath) {
  const absolutePath = filePath(relativePath);
  if (!fs.existsSync(absolutePath)) {
    failures.push(`Missing file: ${relativePath}`);
    return '';
  }

  return fs.readFileSync(absolutePath, 'utf8');
}

function writeFile(relativePath, content, label) {
  const absolutePath = filePath(relativePath);
  const previous = fs.existsSync(absolutePath) ? fs.readFileSync(absolutePath, 'utf8') : '';
  if (previous === content) return;

  operations.push(label || `Updated ${relativePath}`);
  if (write) fs.writeFileSync(absolutePath, content);
}

function writeJsonFile(relativePath, value, label) {
  writeFile(relativePath, `${JSON.stringify(value, null, 2)}\n`, label);
}

function buildSiteProfile() {
  return {
    brand: {
      name: brand,
      tagline: brandTagline,
      description: brandDescription,
      site_email: siteEmail,
    },
    locales: {
      public: wpLocale,
      admin: 'en_US',
      force_admin: true,
    },
    writer: {
      name: writerName,
      email: writerEmail,
      gravatar_url: writerGravatarUrl,
      bio: writerBio,
    },
    assets: {
      wordmark: `${projectSlug}-wordmark`,
      icon: `${projectSlug}-icon`,
      social_cover: `${projectSlug}-social-cover`,
    },
    slugs: {
      home: homeSlug,
      recipes: recipesSlug,
      guides: guidesSlug,
      about: aboutSlug,
      author: authorSlug,
      privacy: privacySlug,
      cookies: cookiesSlug,
      advertising: advertisingSlug,
      editorial: editorialSlug,
      terms: termsSlug,
      disclaimer: disclaimerSlug,
    },
  };
}

function updateSiteProfile() {
  writeJsonFile('content/site-profile.json', buildSiteProfile(), 'Updated content/site-profile.json for public identity and locales');
}

function updateEnvExample() {
  const relativePath = '.env.example';
  const env = readFile(relativePath);
  if (!env) return;

  const updates = new Map([
    ['SITE_URL', siteUrl],
    ['SITE_EMAIL', siteEmail],
    ['WRITER_EMAIL', writerEmail],
    ['WP_LOCALE', wpLocale],
    ['WP_ADMIN_LOCALE', wpAdminLocale],
    ['CANONICAL_REDIRECT_HOSTS', canonicalHosts],
    ['WORDPRESS_DB_NAME', projectUnderscore],
    ['WORDPRESS_DB_USER', projectUnderscore],
    ['WP_ADMIN_EMAIL', siteEmail],
    ['ADSENSE_CLIENT_ID', adsenseClientId],
    ['ADSENSE_PUB_ID', adsensePubId],
    ['ADSENSE_ENABLE', '0'],
    ['GA_ENABLE', '0'],
    ['GA_MEASUREMENT_ID', gaMeasurementId],
  ]);

  const seen = new Set();
  const lines = env.split(/\r?\n/).map((line) => {
    const match = /^([A-Z0-9_]+)=/.exec(line);
    if (!match || !updates.has(match[1])) return line;

    seen.add(match[1]);
    return `${match[1]}=${updates.get(match[1])}`;
  });

  for (const [key, value] of updates.entries()) {
    if (!seen.has(key)) lines.push(`${key}=${value}`);
  }

  writeFile(relativePath, lines.join('\n'), 'Updated .env.example for the replica identity');
}

function updateDockerCompose() {
  const relativePath = 'docker-compose.yml';
  let compose = readFile(relativePath);
  if (!compose) return;

  compose = compose
    .replace(/\bkepoli_db\b/g, `${projectUnderscore}_db`)
    .replace(/\bkepoli_wordpress\b/g, `${projectUnderscore}_wordpress`)
    .replace(/\bkepoli_uploads\b/g, `${projectUnderscore}_uploads`)
    .replace(/\bkepoli-wordpress\b/g, `${projectSlug}-wordpress`)
    .replace(/\bkepoli-wp-cli\b/g, `${projectSlug}-wp-cli`)
    .replace(/SITE_URL: \$\{SITE_URL:-[^}]+}/g, `SITE_URL: \${SITE_URL:-${siteUrl}}`)
    .replace(/SITE_EMAIL: \$\{SITE_EMAIL:-[^}]+}/g, `SITE_EMAIL: \${SITE_EMAIL:-${siteEmail}}`)
    .replace(/WRITER_EMAIL: \$\{WRITER_EMAIL:-[^}]+}/g, `WRITER_EMAIL: \${WRITER_EMAIL:-${writerEmail}}`)
    .replace(/WP_LOCALE: \$\{WP_LOCALE:-[^}]+}/g, `WP_LOCALE: \${WP_LOCALE:-${wpLocale}}`)
    .replace(/WP_ADMIN_LOCALE: \$\{WP_ADMIN_LOCALE:-[^}]+}/g, `WP_ADMIN_LOCALE: \${WP_ADMIN_LOCALE:-${wpAdminLocale}}`)
    .replace(/CANONICAL_REDIRECT_HOSTS: \$\{CANONICAL_REDIRECT_HOSTS:-[^}]*}/g, `CANONICAL_REDIRECT_HOSTS: \${CANONICAL_REDIRECT_HOSTS:-${canonicalHosts}}`)
    .replace(/ADSENSE_CLIENT_ID: \$\{ADSENSE_CLIENT_ID:-[^}]*}/g, `ADSENSE_CLIENT_ID: \${ADSENSE_CLIENT_ID:-${adsenseClientId}}`)
    .replace(/ADSENSE_PUB_ID: \$\{ADSENSE_PUB_ID:-[^}]*}/g, `ADSENSE_PUB_ID: \${ADSENSE_PUB_ID:-${adsensePubId}}`)
    .replace(/GA_MEASUREMENT_ID: \$\{GA_MEASUREMENT_ID:-[^}]*}/g, `GA_MEASUREMENT_ID: \${GA_MEASUREMENT_ID:-${gaMeasurementId}}`);

  writeFile(relativePath, compose, 'Updated Docker defaults, images, and volume names');
}

function updateThemeHeader() {
  const relativePath = 'wp-content/themes/kepoli/style.css';
  let style = readFile(relativePath);
  if (!style) return;

  style = style
    .replace(/^Theme Name: .+$/m, `Theme Name: ${brand}`)
    .replace(/^Theme URI: .+$/m, `Theme URI: ${siteUrl}`)
    .replace(/^Author: .+$/m, `Author: ${writerName}`)
    .replace(/^Author URI: .+$/m, `Author URI: ${siteUrl}/${authorSlug}/`)
    .replace(/^Description: .+$/m, `Description: ${themeDescription}`);

  writeFile(relativePath, style, 'Updated public theme header');
}

function updatePublicIdentityFiles() {
  const files = [
    'README.md',
    'docs/ad-code-inventory.md',
    'docs/ad-operations-manual.md',
    'docs/ads-optimization-playbook.md',
    'docs/adsense-readiness.md',
    'docs/ai-content-growth-strategy.md',
    'docs/author-workflow.md',
    'docs/content-machine-extraction-map.md',
    'docs/coolify.md',
    'docs/display-ads-readiness.md',
    'docs/ezoic-readiness.md',
    'docs/future-session-handoff.md',
    'docs/histats-readiness.md',
    'docs/image-generation.md',
    'docs/monetag-readiness.md',
    'docs/project-status.md',
    'docs/replicate-food-blog.md',
    'docs/site-brief-dr-purg-jr.md',
    'docs/social-syndicator.md',
    'content/pages.json',
    'seed/bin/bootstrap.sh',
    'wp-content/themes/kepoli/functions.php',
    'wp-content/themes/kepoli/header.php',
    'wp-content/themes/kepoli/footer.php',
    'wp-content/themes/kepoli/front-page.php',
    'wp-content/themes/kepoli/single.php',
    'wp-content/themes/kepoli/archive.php',
    'wp-content/themes/kepoli/index.php',
    'wp-content/themes/kepoli/page.php',
    'wp-content/themes/kepoli/page-about-kuchniatwist.php',
    'wp-content/themes/kepoli/page-about-author.php',
    'wp-content/themes/kepoli/page-recipes.php',
    'wp-content/themes/kepoli/page-guides.php',
    'wp-content/themes/kepoli/page-despre-kepoli.php',
    'wp-content/themes/kepoli/page-despre-autor.php',
    'wp-content/themes/kepoli/page-retete.php',
    'wp-content/themes/kepoli/page-articole.php',
    'wp-content/themes/kepoli/template-parts-sidebar.php',
    'wp-content/mu-plugins/kepoli-adtech.php',
    'wp-content/mu-plugins/kepoli-autoseed.php',
    'wp-content/mu-plugins/kepoli-newsletter.php',
    'wp-content/plugins/kepoli-author-tools/kepoli-author-tools.php',
    'wp-content/plugins/kepoli-author-tools/assets/admin.js',
    'wp-content/plugins/kepoli-author-tools/assets/editor-tools.js',
  ];

  for (const relativePath of files) {
    const absolutePath = filePath(relativePath);
    if (!fs.existsSync(absolutePath)) continue;

    let content = fs.readFileSync(absolutePath, 'utf8');
    content = replaceIdentity(content);

    if (relativePath === 'README.md') {
      content = content
        .replace(/# .+ WordPress Blog/, `# ${brand} WordPress Blog`)
        .replace(/kepoli-wordpress/g, `${projectSlug}-wordpress`)
        .replace(/kepoli-wp-cli/g, `${projectSlug}-wp-cli`);
    }

    writeFile(relativePath, content, `Updated public identity in ${relativePath}`);
  }
}

function updateThemeAssetNames() {
  const assetRenames = [
    ['dr-purg-jr-wordmark', `${projectSlug}-wordmark`],
    ['dr-purg-jr-icon', `${projectSlug}-icon`],
    ['dr-purg-jr-social-cover', `${projectSlug}-social-cover`],
    ['kuchniatwist-wordmark', `${projectSlug}-wordmark`],
    ['kuchniatwist-icon', `${projectSlug}-icon`],
    ['kuchniatwist-social-cover', `${projectSlug}-social-cover`],
  ];
  const extensions = ['svg', 'png', 'jpg', 'jpeg', 'webp'];

  for (const [fromBase, toBase] of assetRenames) {
    if (fromBase === toBase) continue;

    for (const extension of extensions) {
      const from = filePath(`wp-content/themes/kepoli/assets/img/${fromBase}.${extension}`);
      const to = filePath(`wp-content/themes/kepoli/assets/img/${toBase}.${extension}`);
      if (!fs.existsSync(from) || fs.existsSync(to)) continue;

      operations.push(`Renamed theme asset ${fromBase}.${extension} to ${toBase}.${extension}`);
      if (write) fs.renameSync(from, to);
    }
  }
}

function replaceIdentity(value) {
  return value
    .replace(/Dr Purg Jr Editorial Desk/g, writerName)
    .replace(/Dr Purg Jr\.?/g, brand)
    .replace(/contact@health\.ibnbatoutaweb\.com/g, siteEmail)
    .replace(/editor@health\.ibnbatoutaweb\.com/g, writerEmail)
    .replace(/health\.ibnbatoutaweb\.com/g, hostname)
    .replace(/dr-purg-jr-wordmark/g, `${projectSlug}-wordmark`)
    .replace(/dr-purg-jr-icon/g, `${projectSlug}-icon`)
    .replace(/dr-purg-jr-social-cover/g, `${projectSlug}-social-cover`)
    .replace(/about-dr-purg-jr/g, aboutSlug)
    .replace(/dr-purg-jr/g, projectSlug)
    .replace(/Kepoli/g, brand)
    .replace(/contact@kepoli\.com/g, siteEmail)
    .replace(/isalunemerovik@gmail\.com/g, writerEmail)
    .replace(/kepoli\.com/g, hostname)
    .replace(/Isalune Merovik/g, writerName)
    .replace(/Isalune/g, writerParts.first)
    .replace(/Merovik/g, writerParts.last)
    .replace(/about-kuchniatwist/g, aboutSlug)
    .replace(/about-author/g, authorSlug)
    .replace(/privacy-policy/g, privacySlug)
    .replace(/cookie-policy/g, cookiesSlug)
    .replace(/advertising-and-consent/g, advertisingSlug)
    .replace(/editorial-policy/g, editorialSlug)
    .replace(/terms-and-conditions/g, termsSlug)
    .replace(/culinary-disclaimer/g, disclaimerSlug)
    .replace(/despre-kepoli/g, aboutSlug)
    .replace(/despre-autor/g, authorSlug)
    .replace(/\/retete\//g, `/${recipesSlug}/`)
    .replace(/\/articole\//g, `/${guidesSlug}/`)
    .replace(/(['"])retete\1/g, `$1${recipesSlug}$1`)
    .replace(/(['"])articole\1/g, `$1${guidesSlug}$1`);
}
