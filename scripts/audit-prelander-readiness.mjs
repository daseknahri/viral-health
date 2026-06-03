import fs from 'node:fs';
import path from 'node:path';

const root = process.cwd();
const failures = [];

const env = readEnv('.env.example');
const compose = readFile('docker-compose.yml');
const functionsPhp = readFile('wp-content/themes/kepoli/functions.php');
const singlePhp = readFile('wp-content/themes/kepoli/single.php');
const articleJs = readFile('wp-content/themes/kepoli/assets/js/article.js');
const styleCss = readFile('wp-content/themes/kepoli/style.css');
const styleMinCss = readFile('wp-content/themes/kepoli/style.min.css');
const playbook = readFile('docs/ads-optimization-playbook.md');

checkEnv();
checkCompose();
checkTheme();
checkScripts();
checkStyles();
checkDocs();

if (failures.length === 0) {
  console.log('Pre-lander readiness OK.');
} else {
  console.log(`Pre-lander readiness found ${failures.length} required fix${failures.length === 1 ? '' : 'es'}.`);
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

function readEnv(relativePath) {
  const values = new Map();
  const content = readFile(relativePath);
  for (const line of content.split(/\r?\n/)) {
    const trimmed = line.trim();
    if (!trimmed || trimmed.startsWith('#')) continue;
    const equalIndex = trimmed.indexOf('=');
    if (equalIndex === -1) continue;
    values.set(trimmed.slice(0, equalIndex), trimmed.slice(equalIndex + 1));
  }

  return values;
}

function envValue(key) {
  return String(env.get(key) ?? '').trim();
}

function occurrences(value, pattern) {
  return (String(value).match(new RegExp(escapeRegExp(pattern), 'g')) || []).length;
}

function escapeRegExp(value) {
  return String(value).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

function requireIncludes(label, content, patterns) {
  for (const pattern of patterns) {
    if (!pattern.test(content)) {
      failures.push(`${label} is missing: ${pattern}`);
    }
  }
}

function checkEnv() {
  if (!env.has('KT_AD_MODE') || envValue('KT_AD_MODE') !== 'baseline') {
    failures.push('.env.example must default KT_AD_MODE=baseline.');
  }
  if (!env.has('KT_PRELANDER_ENABLE') || envValue('KT_PRELANDER_ENABLE') !== '0') {
    failures.push('.env.example must default KT_PRELANDER_ENABLE=0.');
  }
  if (!env.has('KT_ACTION_AD_MIN_SECONDS') || envValue('KT_ACTION_AD_MIN_SECONDS') !== '45') {
    failures.push('.env.example must default KT_ACTION_AD_MIN_SECONDS=45.');
  }
  if (!env.has('KT_ACTION_AD_MIN_SCROLL') || envValue('KT_ACTION_AD_MIN_SCROLL') !== '35') {
    failures.push('.env.example must default KT_ACTION_AD_MIN_SCROLL=35.');
  }
  if (!env.has('MONETAG_VIGNETTE_MINUTES') || envValue('MONETAG_VIGNETTE_MINUTES') !== '0') {
    failures.push('.env.example must keep baseline MONETAG_VIGNETTE_MINUTES=0.');
  }
}

function checkCompose() {
  for (const key of ['KT_AD_MODE', 'KT_PRELANDER_ENABLE', 'KT_ACTION_AD_MIN_SECONDS', 'KT_ACTION_AD_MIN_SCROLL']) {
    if (occurrences(compose, key) < 2) {
      failures.push(`docker-compose.yml must pass ${key} to both wordpress and wp-init.`);
    }
  }
}

function checkTheme() {
  requireIncludes('theme pre-lander/ad mode helpers', functionsPhp, [
    /function\s+kepoli_ad_mode\s*\(/,
    /function\s+kepoli_prelander_enabled\s*\(/,
    /function\s+kepoli_ad_mode_allows_action_onclick\s*\(/,
    /KT_AD_MODE/,
    /KT_PRELANDER_ENABLE/,
    /function\s+kepoli_prelander_request_slug\s*\(/,
    /function\s+kepoli_prelander_target_url\s*\(/,
    /function\s+kepoli_maybe_render_prelander\s*\(/,
    /prelander\//,
    /utm_source/,
    /utm_campaign/,
    /data-prelander-cta/,
    /prelander_cta_click/,
    /MONETAG_ONCLICK_BASE64/,
    /kepoli_monetag_action_onclick_code/,
    /kepoli_action_ad_min_seconds/,
    /kepoli_action_ad_min_scroll/,
    /\$format\s*===\s*'inpage_push'[\s\S]{0,80}\$mode\s*===\s*'baseline'/,
    /\$format\s*===\s*'vignette'[\s\S]{0,80}\$mode\s*!==\s*'aggressive'/,
  ]);

  requireIncludes('single post tracking hooks', singlePhp, [
    /data-ad-event="related_recipe_click"/,
  ]);
}

function checkScripts() {
  requireIncludes('article.js action tracking', articleJs, [
    /window\.kepoliAdStrategy/,
    /recipe_page_view/,
    /scroll_50/,
    /\$\{action\}_click/,
    /popunder_triggered/,
    /kepoli_monetag_onclick_action/,
    /kepoli_monetag_vignette/,
    /minActionSeconds/,
    /minActionScroll/,
    /data-ad-intent-action/,
    /data-ad-event/,
  ]);
}

function checkStyles() {
  requireIncludes('style.css pre-lander styles', styleCss, [
    /\.prelander-layout/,
    /\.prelander-card/,
    /\.prelander-card__cta/,
  ]);

  requireIncludes('style.min.css pre-lander styles', styleMinCss, [
    /\.prelander-layout/,
    /\.prelander-card/,
    /\.prelander-card__cta/,
  ]);
}

function checkDocs() {
  requireIncludes('docs/ads-optimization-playbook.md pre-lander docs', playbook, [
    /KT_AD_MODE=baseline/,
    /KT_PRELANDER_ENABLE=0/,
    /\/prelander\/\{post-slug\}\//,
    /medium/,
    /aggressive/,
    /MONETAG_VIGNETTE_BASE64=/,
    /MONETAG_INPAGE_PUSH_BASE64=/,
    /KT_ACTION_AD_MIN_SECONDS=45/,
    /KT_ACTION_AD_MIN_SCROLL=35/,
    /stable article intent hook/i,
  ]);
}
