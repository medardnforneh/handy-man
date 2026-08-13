#!/usr/bin/env node
/**
 * Which API operations does the app never call?
 *
 * "Declared, shipped, tested — and nothing invokes it" is the defect pattern this project keeps
 * producing: dead `FollowUpKind` cases, a provider signup flow that did not exist, address
 * creation without which no on-site job could be posted, a device registration whose absence left
 * push with no recipients at all. Every one was invisible to the test suite, because each half
 * worked perfectly on its own.
 *
 * A green backend test says nothing about whether a screen reaches it. This does.
 *
 * The check is a literal-path match, which is sound here because both the typed client and the
 * offline write queue address the API by its templated path exactly as the spec writes it
 * (`/engagements/{engagement}/check-in`). A dynamic string would be a false positive — if that
 * ever changes, this script needs to change with it.
 *
 * Run: `node tools/find-uncalled-endpoints.mjs`
 */
import { readFileSync, readdirSync, statSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');

const spec = readFileSync(join(root, 'openapi/openapi.yaml'), 'utf8');
const ops = [];
let path = null;
let method = null;
for (const line of spec.split('\n')) {
  let m;
  if ((m = line.match(/^ {2}(\/[^:]*):\s*$/))) { path = m[1]; method = null; }
  else if ((m = line.match(/^ {4}(get|post|put|patch|delete):\s*$/))) { method = m[1]; }
  else if ((m = line.match(/^ {6}operationId:\s*(\S+)/))) { ops.push({ id: m[1], path, method }); }
}

function walk(dir, out = []) {
  for (const entry of readdirSync(dir)) {
    const full = join(dir, entry);
    if (statSync(full).isDirectory()) {
      walk(full, out);
    } else if (full.endsWith('.ts') && !full.includes('generated')) {
      out.push(full);
    }
  }
  return out;
}

// The whole client, not just api.service.ts: auth lives in core/auth.service.ts, and scanning one
// file reported the OTP endpoints as uncalled.
const client = walk(join(root, 'mobile/src/app')).map((f) => readFileSync(f, 'utf8')).join('\n');
const missing = ops.filter((o) => !client.includes(`'${o.path}'`));

console.log(`operations in the spec: ${ops.length}`);
console.log(`never called by the client: ${missing.length}\n`);
for (const o of missing) {
  console.log('  ', o.method.toUpperCase().padEnd(6), o.path.padEnd(48), o.id);
}
console.log(
  '\nNot every one is a defect — the payment webhook is server-to-server, /notes is the P0-05\n'
  + 'reference slice, and worker assignment is an admin-panel function. Everything else is a\n'
  + 'surface the API can serve and the app cannot reach. docs/BUILD_STATE.md triages the list.',
);
