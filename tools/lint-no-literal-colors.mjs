#!/usr/bin/env node
// no-literal-colour lint (build plan P0-14, doc 08).
//
// Components must reference SEMANTIC tokens (var(--hm-*) / Tailwind token classes / Ionic vars),
// never a literal colour — otherwise dark mode becomes a thousand overrides. A hard-coded hex or
// rgb()/hsl() in an authored component fails the build.
//
// Only AUTHORED component code is scanned; generated token files and framework theme config
// (where the palette legitimately originates) are excluded.

import { readFileSync, readdirSync, statSync, existsSync } from 'node:fs';
import { join, extname, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');

const SCAN = [
  { dir: 'mobile/src/app', exts: ['.ts', '.html', '.scss', '.css'] },
  { dir: 'backend/resources/views', exts: ['.php', '.html'] },
];

// Files/dirs where literal colours are allowed (generated or framework-owned).
const EXCLUDE = [
  /[\\/]tokens\.css$/,
  /[\\/]ionic-tokens\.css$/,
  /[\\/]generated[\\/]/,
  /[\\/]node_modules[\\/]/,
];

const HEX = /#[0-9a-fA-F]{3}(?:[0-9a-fA-F]{3}(?:[0-9a-fA-F]{2})?)?\b/;
const FUNC = /\b(?:rgba?|hsla?)\s*\(/;

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

const violations = [];
for (const { dir, exts } of SCAN) {
  const files = [];
  walk(join(root, dir), exts, files);
  for (const file of files) {
    readFileSync(file, 'utf8').split('\n').forEach((line, i) => {
      if (HEX.test(line) || FUNC.test(line)) {
        violations.push(`  ${file.slice(root.length + 1)}:${i + 1}  ${line.trim()}`);
      }
    });
  }
}

if (violations.length > 0) {
  console.error('Literal colours found (use semantic tokens instead):\n' + violations.join('\n'));
  process.exit(1);
}
console.log('no-literal-colours: clean.');
