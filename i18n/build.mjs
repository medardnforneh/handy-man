#!/usr/bin/env node
// i18n build + parity gate (build plan P0-15).
//
// ONE keyed source (i18n/source/{en,fr}.json, flat dot-keyed) → two surfaces:
//   - Laravel:  backend/lang/{en,fr}.json          (flat keys, used by __('nav.customer'))
//   - Angular:  mobile/src/assets/i18n/{en,fr}.json (nested, used by ngx-translate)
//
// Parity is enforced: a key present in one language but missing in the other FAILS the build, so
// an untranslated key can never reach a user as a raw `some.key`. Run with --check in CI to gate
// without writing files.

import { readFileSync, writeFileSync, mkdirSync, existsSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const root = join(__dirname, '..');
const LOCALES = ['en', 'fr'];
const checkOnly = process.argv.includes('--check');

function loadSource(locale) {
  const path = join(__dirname, 'source', `${locale}.json`);
  return JSON.parse(readFileSync(path, 'utf8'));
}

function assertParity(dicts) {
  const keySets = Object.fromEntries(
    LOCALES.map((l) => [l, new Set(Object.keys(dicts[l]))]),
  );
  const all = new Set(LOCALES.flatMap((l) => [...keySets[l]]));

  const problems = [];
  for (const key of all) {
    for (const locale of LOCALES) {
      if (!keySets[locale].has(key)) {
        problems.push(`  missing in ${locale}: ${key}`);
      } else if (String(dicts[locale][key]).trim() === '') {
        problems.push(`  empty in ${locale}: ${key}`);
      }
    }
  }

  if (problems.length > 0) {
    console.error('i18n parity check FAILED:\n' + problems.join('\n'));
    process.exit(1);
  }
  console.log(`i18n parity OK — ${all.size} keys × ${LOCALES.length} locales.`);
}

// Expand flat "a.b.c" keys into nested objects for ngx-translate.
function nest(flat) {
  const out = {};
  for (const [key, value] of Object.entries(flat)) {
    const parts = key.split('.');
    let node = out;
    for (let i = 0; i < parts.length - 1; i++) {
      node[parts[i]] ??= {};
      node = node[parts[i]];
    }
    node[parts.at(-1)] = value;
  }
  return out;
}

function writeJson(path, data) {
  mkdirSync(dirname(path), { recursive: true });
  writeFileSync(path, JSON.stringify(data, null, 2) + '\n', 'utf8');
  console.log(`  wrote ${path.replace(root + '\\', '').replace(root + '/', '')}`);
}

const dicts = Object.fromEntries(LOCALES.map((l) => [l, loadSource(l)]));
assertParity(dicts);

if (checkOnly) process.exit(0);

/**
 * The shared source uses ngx-translate's `{{name}}` placeholders. Laravel's translator only
 * substitutes `:name`, so a parameterised string rendered from Blade would otherwise print the
 * raw `{{name}}` to the user — which it did, on the public services pages. Convert for the Laravel
 * output only; the Angular file keeps the `{{name}}` form it expects.
 */
function toLaravelPlaceholders(flat) {
  return Object.fromEntries(
    Object.entries(flat).map(([key, value]) => [
      key,
      typeof value === 'string' ? value.replace(/\{\{\s*(\w+)\s*\}\}/g, ':$1') : value,
    ]),
  );
}

for (const locale of LOCALES) {
  // Laravel flat JSON translations.
  writeJson(join(root, 'backend', 'lang', `${locale}.json`), toLaravelPlaceholders(dicts[locale]));

  // Angular nested JSON for ngx-translate (only if the app exists yet).
  const mobileI18n = join(root, 'mobile', 'src', 'assets', 'i18n');
  if (existsSync(join(root, 'mobile'))) {
    writeJson(join(mobileI18n, `${locale}.json`), nest(dicts[locale]));
  }
}

console.log('i18n build complete.');
