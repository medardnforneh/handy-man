#!/usr/bin/env node
// Native API origin guard (build plan P5-01).
//
// A packaged Android/iOS app cannot use a relative `/api/v1` — its origin is the device, so a
// relative URL never leaves the phone. `NATIVE_API_ORIGIN` in environment.prod.ts is therefore the
// one address the native app has, and it CANNOT be derived: it has to be stated.
//
// It is still a placeholder, because no domain is registered yet. The failure mode if that reaches
// a store build is bad and quiet: the app installs, opens, renders, and every request goes nowhere.
// It looks like a working app talking to nothing — the same shape of bug as the native-transport
// defects, where the UI showed no error and simply served fixtures.
//
// So: a RELEASE build refuses to proceed while the placeholder is in place. Ordinary dev builds only
// warn, because failing them would block everyone on something that cannot be fixed until a domain
// exists. Set HM_RELEASE=1 in the store-build pipeline to turn the warning into a gate.

import { readFileSync } from 'node:fs';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');
const file = join(root, 'mobile/src/environments/environment.prod.ts');

// Known-bad values: the committed placeholder, and anything that plainly is not a deployed origin.
const PLACEHOLDERS = [/^https:\/\/app\.handyman\.cm\/?$/i];

const source = readFileSync(file, 'utf8');
const match = source.match(/const\s+NATIVE_API_ORIGIN\s*=\s*['"]([^'"]+)['"]/);

if (match === null) {
  console.error('native-origin: could not find NATIVE_API_ORIGIN in environment.prod.ts.');
  process.exit(1);
}

const origin = match[1];
const problems = [];

if (PLACEHOLDERS.some((p) => p.test(origin))) {
  problems.push(`NATIVE_API_ORIGIN is still the placeholder (${origin}) — no domain is registered yet.`);
}
if (!origin.startsWith('https://')) {
  // Cleartext is opt-in for local dev via capacitor.config.ts, never for a shipped build.
  problems.push(`NATIVE_API_ORIGIN must be https (got ${origin}).`);
}

if (problems.length === 0) {
  console.log(`native-origin: ok (${origin}).`);
  process.exit(0);
}

const releasing = process.env.HM_RELEASE === '1';
const label = releasing ? 'ERROR' : 'WARNING';
console.error(`native-origin: ${label}`);
for (const p of problems) {
  console.error(`  - ${p}`);
}
console.error('  A packaged app built with this will install, open, render — and reach nothing.');

if (releasing) {
  console.error('  Refusing to build a release. Set the real origin in environment.prod.ts.');
  process.exit(1);
}
console.error('  Not failing a dev build; set HM_RELEASE=1 to gate the store build on this.');
process.exit(0);
