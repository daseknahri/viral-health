import { spawnSync } from 'node:child_process';

const args = parseArgs(process.argv.slice(2));
if (args.help || args.h) {
  printHelp();
  process.exit(0);
}

const liveUrl = typeof args.live === 'string' ? String(args.live).replace(/\/+$/, '') : '';
const checks = [
  {
    label: 'Content structure',
    command: ['node', 'scripts/verify-content.mjs'],
  },
  {
    label: 'Image coverage',
    command: ['node', 'scripts/image-status.mjs'],
  },
  {
    label: 'Rebrand hygiene',
    command: ['node', 'scripts/audit-rebrand.mjs'],
  },
  {
    label: 'Seed readiness',
    command: ['node', 'scripts/audit-seed-readiness.mjs'],
  },
  {
    label: 'Ezoic readiness',
    command: ['node', 'scripts/audit-ezoic-readiness.mjs'],
  },
  {
    label: 'Monetag readiness',
    command: ['node', 'scripts/audit-monetag-readiness.mjs'],
  },
  {
    label: 'Display ads readiness',
    command: ['node', 'scripts/audit-display-ads-readiness.mjs'],
  },
  {
    label: 'Ad operations readiness',
    command: ['node', 'scripts/audit-ad-ops-readiness.mjs'],
  },
  {
    label: 'Pre-lander readiness',
    command: ['node', 'scripts/audit-prelander-readiness.mjs'],
  },
  {
    label: 'Histats readiness',
    command: ['node', 'scripts/audit-histats-readiness.mjs'],
  },
  {
    label: 'Social Syndicator readiness',
    command: ['node', 'scripts/audit-social-syndicator-readiness.mjs'],
  },
];

if (liveUrl !== '') {
  checks.push(
    {
      label: 'Live Ezoic readiness',
      command: ['node', 'scripts/audit-ezoic-readiness.mjs', '--live', liveUrl],
    },
    {
      label: 'Live deploy markers',
      command: ['node', 'scripts/check-live-deploy.mjs', liveUrl],
    },
  );
}

let hasFailure = false;
for (const check of checks) {
  printHeading(check.label);
  const result = spawnSync(check.command[0], check.command.slice(1), {
    cwd: process.cwd(),
    encoding: 'utf8',
    stdio: 'pipe',
  });

  const stdout = (result.stdout || '').trim();
  const stderr = (result.stderr || '').trim();

  if (stdout !== '') {
    console.log(stdout);
  }
  if (stderr !== '') {
    console.error(stderr);
  }

  if (result.status !== 0) {
    hasFailure = true;
    console.error(`Result: FAILED (${result.status ?? 'unknown'})`);
  } else {
    console.log('Result: OK');
  }

  console.log('');
}

if (hasFailure) {
  console.error('Preflight failed. Fix the items above before pushing a launch change or monetization submission.');
  process.exit(1);
}

console.log('Preflight passed. The repo is ready for the next deploy or review step.');

function parseArgs(argv) {
  const parsed = {};

  for (let index = 0; index < argv.length; index += 1) {
    const item = argv[index];
    if (!item.startsWith('--')) {
      continue;
    }

    const key = item.slice(2);
    const next = argv[index + 1];
    if (next && !next.startsWith('--')) {
      parsed[key] = next;
      index += 1;
      continue;
    }

    parsed[key] = true;
  }

  return parsed;
}

function printHelp() {
  console.log(`Usage:
node scripts/preflight-launch.mjs
node scripts/preflight-launch.mjs --live https://kuchniatwist.pl

Runs the key launch checks in one pass.

Default checks:
- content structure
- image coverage
- rebrand hygiene
- seed readiness
- Ezoic readiness
- Monetag readiness
- Display ads readiness
- Ad operations readiness
- Pre-lander readiness
- Histats readiness
- Social Syndicator readiness

With --live:
- live Ezoic readiness
- live deploy marker check`);
}

function printHeading(label) {
  console.log(`=== ${label} ===`);
}
