import crypto from 'node:crypto';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const repoRoot = path.resolve(__dirname, '..');
const liveUrl = (process.argv[2] || process.env.SITE_URL || 'https://health.ibnbatoutaweb.com').replace(/\/+$/, '');
const siteProfile = readJsonFile('content/site-profile.json', {});
const categories = readJsonFile('content/categories.json', []);
const posts = readJsonFile('content/posts.json', []);

const versionFiles = [
  'seed/bootstrap.php',
  'content/site-profile.json',
  'content/categories.json',
  'content/pages.json',
  'content/posts.json',
  'content/image-plan.json',
];

function localSeedVersion() {
  const hash = crypto.createHash('sha256');

  for (const relativeFile of versionFiles) {
    const absoluteFile = path.join(repoRoot, relativeFile);
    const seedPath = `/${relativeFile.replace(/\\/g, '/')}`;

    if (!fs.existsSync(absoluteFile)) {
      hash.update(`${seedPath}|missing`);
      continue;
    }

    hash.update(seedPath);
    hash.update(fs.readFileSync(absoluteFile));
  }

  return `seed-${hash.digest('hex').slice(0, 16)}`;
}

function extractMetaByName(html) {
  const meta = new Map();
  const tags = html.match(/<meta\s+[^>]*>/gi) || [];

  for (const tag of tags) {
    const nameMatch = tag.match(/\bname=["']([^"']+)["']/i);
    const contentMatch = tag.match(/\bcontent=["']([^"']*)["']/i);

    if (!nameMatch || !contentMatch) {
      continue;
    }

    meta.set(nameMatch[1].toLowerCase(), contentMatch[1]);
  }

  return meta;
}

async function fetchHtml(url) {
  const response = await fetch(url, {
    headers: {
      'user-agent': 'FoodBlogLiveDeployCheck/1.0',
    },
    redirect: 'follow',
  });

  if (!response.ok) {
    throw new Error(`Request failed for ${url}: ${response.status} ${response.statusText}`);
  }

  return response.text();
}

function readJsonFile(relativePath, fallback) {
  const absoluteFile = path.join(repoRoot, relativePath);
  if (!fs.existsSync(absoluteFile)) return fallback;

  try {
    return JSON.parse(fs.readFileSync(absoluteFile, 'utf8'));
  } catch {
    return fallback;
  }
}

function profileValue(keys, fallback = '') {
  let value = siteProfile;
  for (const key of keys) {
    if (!value || typeof value !== 'object' || !(key in value)) return fallback;
    value = value[key];
  }
  return value;
}

function expectedRequiredPublicMarkers() {
  const markers = [
    profileValue(['brand', 'name'], ''),
    profileValue(['writer', 'name'], ''),
    categories[0]?.name || '',
  ];

  return markers.map((marker) => String(marker || '').trim()).filter(Boolean);
}

function expectedAnyPostMarkers() {
  return posts
    .map((post) => String(post?.title || '').trim())
    .filter(Boolean);
}

function containsMarker(html, marker) {
  return html.toLowerCase().includes(marker.toLowerCase());
}

async function main() {
  const expectedVersion = localSeedVersion();
  const html = await fetchHtml(liveUrl);
  const meta = extractMetaByName(html);
  const liveTarget = meta.get('kepoli-seed-target') || '';
  const liveCurrent = meta.get('kepoli-seed-current') || '';

  console.log(`Live URL: ${liveUrl}`);
  console.log(`Local target: ${expectedVersion}`);
  console.log(`Live target: ${liveTarget || '(missing)'}`);
  console.log(`Live current: ${liveCurrent || '(missing)'}`);

  if (!liveTarget) {
    if (html.includes('contact@kepoli.com') || /\bkepoli\.com\b/i.test(html)) {
      throw new Error('Live site is missing deploy fingerprint meta and still exposes old Kepoli public identity.');
    }

    const requiredMarkers = expectedRequiredPublicMarkers();
    const missingMarkers = requiredMarkers.filter((marker) => !containsMarker(html, marker));
    if (missingMarkers.length > 0) {
      throw new Error(`Live site is missing deploy fingerprint meta and expected public markers: ${missingMarkers.join(', ')}`);
    }

    const postMarkers = expectedAnyPostMarkers();
    const matchedPostMarker = postMarkers.find((marker) => containsMarker(html, marker));
    if (postMarkers.length > 0 && !matchedPostMarker) {
      throw new Error('Live site is missing deploy fingerprint meta and none of the current content-pack post titles appear on the homepage.');
    }

    console.log('Deploy fingerprint meta is disabled, but public homepage markers match the current site profile.');
    return;
  }

  if (liveTarget !== expectedVersion) {
    throw new Error('Live target version does not match the current repo hash. Production deployment is stale.');
  }

  if (!liveCurrent) {
    throw new Error('Live site is missing kepoli-seed-current meta. The seed status cannot be confirmed.');
  }

  if (liveCurrent !== liveTarget) {
    throw new Error('Live code is current, but the seeded content version is behind. The deploy landed without completing the seed update.');
  }

  console.log('Live deploy matches the current repo build and seed version.');
}

main().catch((error) => {
  console.error(error.message);
  process.exit(1);
});
