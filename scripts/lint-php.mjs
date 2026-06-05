import { spawnSync } from 'node:child_process';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';

// Parse-checks every PHP file in the repo with `php -l`. This is the only check
// that catches a fatal parse error before WordPress tries to load a plugin on
// activation. PHP is not required to be installed: if no runtime is found the
// check prints a skip notice and exits 0, so preflight stays green on machines
// (and CI) without PHP. To enable it, put `php` on PATH, set PHP_BIN to a php
// binary, or drop a portable build at %TEMP%/php-lint/php.exe (Windows).

const root = process.cwd();
const SKIP_DIRS = new Set(['vendor', 'node_modules', '.git']);

const php = findPhp();
if (php === '') {
  console.log('PHP lint skipped: no PHP runtime found.');
  console.log('Enable it by putting `php` on PATH or setting PHP_BIN to a php binary.');
  process.exit(0);
}

const files = collectPhpFiles(root);
if (files.length === 0) {
  console.log('PHP lint: no PHP files found.');
  process.exit(0);
}

const failures = [];
for (const file of files) {
  const result = spawnSync(php, ['-l', file], { encoding: 'utf8' });
  if (result.status !== 0) {
    const message = `${(result.stdout || '').trim()}\n${(result.stderr || '').trim()}`.trim();
    failures.push({ file: path.relative(root, file), message });
  }
}

if (failures.length === 0) {
  console.log(`PHP lint OK: ${files.length} file${files.length === 1 ? '' : 's'} parsed clean (${path.basename(php)}).`);
  process.exit(0);
}

console.log(`PHP lint found ${failures.length} parse error${failures.length === 1 ? '' : 's'}:`);
for (const failure of failures) {
  console.log(`- ${failure.file}`);
  console.log(failure.message);
}
process.exit(1);

function findPhp() {
  const candidates = [];
  if (process.env.PHP_BIN) {
    candidates.push(process.env.PHP_BIN);
  }
  candidates.push('php');
  candidates.push(path.join(os.tmpdir(), 'php-lint', process.platform === 'win32' ? 'php.exe' : 'php'));

  for (const candidate of candidates) {
    const result = spawnSync(candidate, ['--version'], { encoding: 'utf8' });
    if (!result.error && result.status === 0) {
      return candidate;
    }
  }

  return '';
}

function collectPhpFiles(dir) {
  const found = [];
  for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
    if (entry.isDirectory()) {
      if (SKIP_DIRS.has(entry.name)) {
        continue;
      }
      found.push(...collectPhpFiles(path.join(dir, entry.name)));
    } else if (entry.isFile() && entry.name.endsWith('.php')) {
      found.push(path.join(dir, entry.name));
    }
  }

  return found;
}
