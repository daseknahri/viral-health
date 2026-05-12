import fs from 'node:fs';
import path from 'node:path';

const root = process.cwd();
const failures = [];
const warnings = [];

const env = readEnv('.env.example');
const compose = readFile('docker-compose.yml');
const themeFunctions = readFile('wp-content/themes/kepoli/functions.php');
const adtechMuPlugin = readFile('wp-content/mu-plugins/kepoli-adtech.php');
const bundledSw = readFile('content/monetag/sw.js');
const docs = readFile('docs/monetag-readiness.md');
const siteProfile = readJsonObject('content/site-profile.json');
const pages = readJsonArray('content/pages.json');

checkEnvContract();
checkComposeContract();
checkThemeGate();
checkServiceWorkerGate();
checkPolicyPages();
checkDocs();

if (failures.length === 0) {
  console.log('Monetag readiness OK.');
} else {
  console.log(`Monetag readiness found ${failures.length} required fix${failures.length === 1 ? '' : 'es'}.`);
}

if (warnings.length > 0) {
  console.log(`Monetag readiness found ${warnings.length} warning${warnings.length === 1 ? '' : 's'}.`);
}

printSection('Required fixes', failures);
printSection('Warnings', warnings);

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

function readJsonArray(relativePath) {
  const content = readFile(relativePath);
  if (!content) return [];

  try {
    const value = JSON.parse(content);
    if (!Array.isArray(value)) {
      failures.push(`${relativePath} must contain a JSON array.`);
      return [];
    }

    return value;
  } catch (error) {
    failures.push(`${relativePath} is invalid JSON: ${error.message}`);
    return [];
  }
}

function readJsonObject(relativePath) {
  const content = readFile(relativePath);
  if (!content) return {};

  try {
    const value = JSON.parse(content);
    if (!value || Array.isArray(value) || typeof value !== 'object') {
      failures.push(`${relativePath} must contain a JSON object.`);
      return {};
    }

    return value;
  } catch (error) {
    failures.push(`${relativePath} is invalid JSON: ${error.message}`);
    return {};
  }
}

function envValue(key) {
  return String(env.get(key) ?? '').trim();
}

function profileSlug(key, fallback) {
  return String(siteProfile.slugs?.[key] || fallback).trim().replace(/^\/+|\/+$/g, '');
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

function checkEnvContract() {
  const monetagDefaults = new Map([
    ['KT_AD_MODE', 'baseline'],
    ['KT_PRELANDER_ENABLE', '0'],
    ['MONETAG_ENABLE', '0'],
    ['KT_ACTION_AD_MIN_SECONDS', '45'],
    ['KT_ACTION_AD_MIN_SCROLL', '35'],
    ['MONETAG_INPAGE_PUSH_BASE64', ''],
    ['MONETAG_VIGNETTE_BASE64', ''],
    ['MONETAG_ONCLICK_BASE64', ''],
    ['MONETAG_PUSH_BASE64', ''],
    ['MONETAG_INPAGE_PUSH_MINUTES', '0'],
    ['MONETAG_VIGNETTE_MINUTES', '0'],
    ['MONETAG_ONCLICK_MINUTES', '0'],
    ['MONETAG_PUSH_MINUTES', '0'],
    ['MONETAG_POST_ONLY', '1'],
    ['MONETAG_INSTALL_CHECK', '0'],
    ['MONETAG_VERIFICATION', ''],
    ['MONETAG_SW_JS_BASE64', ''],
  ]);

  for (const [key, expected] of monetagDefaults.entries()) {
    if (!env.has(key)) {
      failures.push(`.env.example is missing ${key}.`);
    } else if (envValue(key) !== expected) {
      failures.push(`.env.example must default ${key}=${expected}.`);
    }
  }

  if (envValue('ADSENSE_ENABLE') !== '0') failures.push('.env.example must keep ADSENSE_ENABLE=0 for the Monetag test.');
  if (envValue('ADSENSE_CLIENT_ID') !== '') failures.push('.env.example must keep ADSENSE_CLIENT_ID empty for the Monetag test.');
  if (envValue('ADSENSE_PUB_ID') !== '') failures.push('.env.example must keep ADSENSE_PUB_ID empty for the Monetag test.');
}

function checkComposeContract() {
  for (const key of [
    'MONETAG_ENABLE',
    'KT_AD_MODE',
    'KT_PRELANDER_ENABLE',
    'KT_ACTION_AD_MIN_SECONDS',
    'KT_ACTION_AD_MIN_SCROLL',
    'MONETAG_INPAGE_PUSH_BASE64',
    'MONETAG_VIGNETTE_BASE64',
    'MONETAG_ONCLICK_BASE64',
    'MONETAG_PUSH_BASE64',
    'MONETAG_INPAGE_PUSH_MINUTES',
    'MONETAG_VIGNETTE_MINUTES',
    'MONETAG_ONCLICK_MINUTES',
    'MONETAG_PUSH_MINUTES',
    'MONETAG_POST_ONLY',
    'MONETAG_INSTALL_CHECK',
    'MONETAG_VERIFICATION',
    'MONETAG_SW_JS_BASE64',
  ]) {
    if (occurrences(compose, key) < 2) {
      failures.push(`docker-compose.yml must pass ${key} to both wordpress and wp-init.`);
    }
  }

  requireIncludes('docker-compose.yml AdSense defaults', compose, [
    /ADSENSE_ENABLE:\s*\$\{ADSENSE_ENABLE:-0\}/,
    /ADSENSE_CLIENT_ID:\s*\$\{ADSENSE_CLIENT_ID:-\}/,
    /ADSENSE_PUB_ID:\s*\$\{ADSENSE_PUB_ID:-\}/,
  ]);
}

function checkThemeGate() {
  requireIncludes('theme Monetag helpers', themeFunctions, [
    /function\s+kepoli_monetag_enabled\s*\(/,
    /function\s+kepoli_ad_mode\s*\(/,
    /function\s+kepoli_ad_mode_allows_action_onclick\s*\(/,
    /function\s+kepoli_action_ad_min_seconds\s*\(/,
    /function\s+kepoli_action_ad_min_scroll\s*\(/,
    /KT_AD_MODE/,
    /KT_PRELANDER_ENABLE/,
    /KT_ACTION_AD_MIN_SECONDS/,
    /KT_ACTION_AD_MIN_SCROLL/,
    /function\s+kepoli_monetag_should_render\s*\(/,
    /function\s+kepoli_monetag_head\s*\(/,
    /MONETAG_ENABLE/,
    /MONETAG_VERIFICATION/,
    /MONETAG_INPAGE_PUSH_BASE64/,
    /MONETAG_VIGNETTE_BASE64/,
    /MONETAG_ONCLICK_BASE64/,
    /MONETAG_PUSH_BASE64/,
    /MONETAG_INPAGE_PUSH_MINUTES/,
    /MONETAG_VIGNETTE_MINUTES/,
    /MONETAG_ONCLICK_MINUTES/,
    /MONETAG_PUSH_MINUTES/,
    /function\s+kepoli_monetag_frequency_minutes\s*\(/,
    /function\s+kepoli_monetag_render_snippet\s*\(/,
    /function\s+kepoli_monetag_action_onclick_code\s*\(/,
    /\$format\s*===\s*'inpage_push'[\s\S]{0,80}\$mode\s*===\s*'baseline'/,
    /\$format\s*===\s*'vignette'[\s\S]{0,80}\$mode\s*!==\s*'aggressive'/,
    /localStorage\.getItem\(key\)/,
    /base64_decode\(\$encoded,\s*true\)/,
    /MONETAG_POST_ONLY/,
    /MONETAG_INSTALL_CHECK/,
  ]);

  requireIncludes('theme public-only Monetag gate', themeFunctions, [
    /is_admin\(\)/,
    /wp_doing_ajax\(\)/,
    /is_feed\(\)/,
    /is_search\(\)/,
    /is_404\(\)/,
    /is_front_page\(\)/,
    /is_home\(\)/,
    /is_user_logged_in\(\)\s*&&\s*current_user_can\('manage_options'\)/,
    /is_singular\('post'\)/,
  ]);

  for (const legacyKey of [
    'MONETAG_VERIFY_META_NAME',
    'MONETAG_VERIFY_META_CONTENT',
    'MONETAG_SCRIPT_SRC',
    'MONETAG_ZONE_ID',
    'MONETAG_CFASYNC',
  ]) {
    if (themeFunctions.includes(legacyKey) || compose.includes(legacyKey) || env.has(legacyKey)) {
      failures.push(`${legacyKey} is legacy and should not be part of the current Monetag contract.`);
    }
  }
}

function checkServiceWorkerGate() {
  requireIncludes('MU plugin Monetag service worker gate', adtechMuPlugin, [
    /\/sw\.js/,
    /MONETAG_ENABLE/,
    /MONETAG_SW_JS_BASE64/,
    /\/content\/monetag\/sw\.js/,
    /file_get_contents\(\$bundled_script_path\)/,
    /base64_decode\(\$encoded_script,\s*true\)/,
    /Content-Type:\s*application\/javascript/,
    /Service-Worker-Allowed:\s*\//,
    /Cache-Control:\s*no-store/,
  ]);

  requireIncludes('bundled Monetag service worker', bundledSw, [
    /self\.options/,
    /zoneId/,
    /importScripts\('https:\/\//,
  ]);
}

function checkPolicyPages() {
  const pageBySlug = new Map(pages.map((page) => [String(page.slug || ''), page]));
  const required = [
    profileSlug('about', 'about-kuchniatwist'),
    profileSlug('author', 'about-author'),
    'contact',
    profileSlug('privacy', 'privacy-policy'),
    profileSlug('cookies', 'cookie-policy'),
    profileSlug('advertising', 'advertising-and-consent'),
    profileSlug('editorial', 'editorial-policy'),
    profileSlug('terms', 'terms-and-conditions'),
    profileSlug('disclaimer', 'culinary-disclaimer'),
  ];

  for (const slug of required) {
    const page = pageBySlug.get(slug);
    if (!page) {
      failures.push(`Missing public page in content/pages.json: ${slug}`);
      continue;
    }

    const content = String(page.content || '').replace(/<[^>]+>/g, ' ').trim();
    if (content.length < 200) {
      failures.push(`Public page ${slug} needs real copy before monetization.`);
    }

    if (String(page.status || 'publish') !== 'publish') {
      failures.push(`Public page ${slug} must remain publish status.`);
    }
  }

  requireIncludes('theme robots policy', themeFunctions, [
    /function\s+kepoli_robots_content\s*\(/,
    /is_search\(\)\s*\|\|\s*is_404\(\)/,
    /return\s+'index,follow,max-image-preview:large'/,
  ]);
}

function checkDocs() {
  requireIncludes('docs/monetag-readiness.md', docs, [
    /MONETAG_ENABLE=0/,
    /KT_AD_MODE=baseline/,
    /KT_PRELANDER_ENABLE=0/,
    /KT_ACTION_AD_MIN_SECONDS=45/,
    /KT_ACTION_AD_MIN_SCROLL=35/,
    /MONETAG_INPAGE_PUSH_BASE64=/,
    /MONETAG_VIGNETTE_BASE64=/,
    /MONETAG_ONCLICK_BASE64=/,
    /MONETAG_PUSH_BASE64=/,
    /MONETAG_INPAGE_PUSH_MINUTES=0/,
    /MONETAG_VIGNETTE_MINUTES=0/,
    /MONETAG_ONCLICK_MINUTES=0/,
    /MONETAG_PUSH_MINUTES=0/,
    /MONETAG_INSTALL_CHECK=0/,
    /MONETAG_VERIFICATION=/,
    /MONETAG_SW_JS_BASE64=/,
    /sw\.js/,
    /content\/monetag\/sw\.js/,
    /ADSENSE_ENABLE=0/,
    /installation check/i,
    /In-Page Push/i,
    /Vignette Banner/i,
    /Popunder/i,
    /SmartLink/i,
    /utm_source=facebook/,
    /Revenue per 1,000 Facebook clicks/,
    /first payout/i,
    /frequency/i,
    /adult/i,
    /legal\/policy pages/i,
  ]);
}

function printSection(title, items) {
  if (items.length === 0) return;
  console.log(`\n${title}:`);
  for (const item of items) {
    console.log(`- ${item}`);
  }
}
