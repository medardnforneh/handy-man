#!/usr/bin/env node
// no-hard-coded-user-strings lint (build plan P0-16, doc 09).
//
// Every user-visible string must come from the shared i18n source — FR and EN are both
// first-class. A bare text node in an Angular template or a Blade view fails the build.
//
// Heuristic: strip comments, interpolations ({{…}}, {!!…!!}), Blade directives (@lang, @if…),
// and all HTML tags (so attribute values aren't scanned here). Whatever readable text remains is
// a hard-coded string. Translation calls (`| translate`, `__('…')`, `@lang`) live INSIDE the
// removed constructs, so a properly translated template leaves nothing behind.

import { readFileSync, readdirSync, statSync, existsSync } from 'node:fs';
import { join, extname, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');

const SCAN = [
  { dir: 'mobile/src/app', exts: ['.html'] },
  { dir: 'backend/resources/views', exts: ['.php'] },
];
const EXCLUDE = [/[\\/]node_modules[\\/]/, /[\\/]generated[\\/]/];

function walk(dir, exts, out) {
  if (!existsSync(dir)) return;
  for (const entry of readdirSync(dir)) {
    const full = join(dir, entry);
    if (EXCLUDE.some((re) => re.test(full))) continue;
    const st = statSync(full);
    if (st.isDirectory()) walk(full, exts, out);
    else if (exts.includes(extname(entry))) out.push(full);
  }
}

function stripToText(src) {
  return src
    .replace(/<style[\s\S]*?<\/style>/gi, ' ')  // <style> blocks (CSS is not user copy)
    .replace(/<script[\s\S]*?<\/script>/gi, ' ') // <script> blocks
    .replace(/@php[\s\S]*?@endphp/gi, ' ')  // @php blocks (code, not user copy — same as <script>)
    .replace(/<!--[\s\S]*?-->/g, ' ')       // HTML comments
    .replace(/\{\{--[\s\S]*?--\}\}/g, ' ')   // Blade comments
    .replace(/\{\{[\s\S]*?\}\}/g, ' ')       // {{ … }} interpolation (Angular + Blade)
    .replace(/\{!![\s\S]*?!!\}/g, ' ')       // {!! … !!} Blade raw
    // @lang('…'), @if(…), Blade @forelse ($a->b('c') as $d), Angular @for (x of f(); track y),
    // and Angular's two-word `@else if (…)` — without the optional `if`, only `@else` was stripped
    // and the condition was left behind to be read as user copy.
    // Parens nest, so match up to three levels rather than stopping at the first ')'.
    .replace(/@\w+(?:\s+if)?\s*\((?:[^()]|\((?:[^()]|\([^()]*\))*\))*\)/g, ' ')
    .replace(/@\w+/g, ' ')                   // bare @else, @endif …
    .replace(/<[^>]*>/g, ' ')                // all tags (drops attribute values too)
    .replace(/&[a-zA-Z]+;|&#\d+;/g, ' ');    // HTML entities (&nbsp; …) are markup, not copy
}

const violations = [];
for (const { dir, exts } of SCAN) {
  const files = [];
  walk(join(root, dir), exts, files);
  for (const file of files) {
    stripToText(readFileSync(file, 'utf8')).split('\n').forEach((line, i) => {
      // A run of 2+ letters left in a text node is a hard-coded user-visible string.
      if (/[A-Za-zÀ-ÿ]{2,}/.test(line)) {
        violations.push(`  ${file.slice(root.length + 1)}:${i + 1}  ${line.trim().slice(0, 80)}`);
      }
    });
  }
}

if (violations.length > 0) {
  console.error('Hard-coded user-visible strings found (use the i18n source):\n' + violations.join('\n'));
  process.exit(1);
}
console.log('no-bare-strings: clean.');
