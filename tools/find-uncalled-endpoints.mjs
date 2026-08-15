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
// Reached by FOLLOWING a URL the server supplied, rather than by naming the path. The literal
// never appears in client code, so the check below cannot see it — and reported it as dead for
// weeks. `MessageResource` emits the media route as an absolute URL and the client fetches exactly
// that (see `ApiService.mediaObjectUrl`, which exists because an `<audio src>` cannot carry a
// Bearer token). Any endpoint the server hands out as a link belongs here, not in the report.
const REACHED_BY_LINK = new Set(['/media/{media}']);

/**
 * Endpoints the app is RIGHT not to call, each with the reason it is right.
 *
 * This list is deliberately hard to add to: every entry is an admission that something in the spec
 * has no user, and "we meant to" is not one of the reasons below. Anything that is merely unbuilt
 * belongs in the report, where it stays uncomfortable.
 */
const NOT_FOR_THE_APP = new Map([
  ['/webhooks/payments/{gateway}', 'server-to-server: the gateway calls it, never a client'],
  ['/notes', 'the P0-05 reference slice — a worked example, not a product surface'],
  ['/notes/{note}', 'the P0-05 reference slice — a worked example, not a product surface'],
  ['/engagements/{engagement}/assignments', 'admin-panel function (dispatching a company’s workers)'],
  ['/engagements/{engagement}/assignments/{assignment}', 'admin-panel function (dispatching a company’s workers)'],
  ['/provider/credits', 'redundant: /provider/earnings returns lead_credits in the same payload, and the earnings screen is the only reader'],
]);

const uncalled = ops.filter((o) => !client.includes(`'${o.path}'`) && !REACHED_BY_LINK.has(o.path));
const missing = uncalled.filter((o) => !NOT_FOR_THE_APP.has(o.path));
const excused = uncalled.filter((o) => NOT_FOR_THE_APP.has(o.path));

console.log(`operations in the spec: ${ops.length}`);
console.log(`never called by the client: ${missing.length}\n`);
for (const o of missing) {
  console.log('  ', o.method.toUpperCase().padEnd(6), o.path.padEnd(48), o.id);
}

if (missing.length === 0) {
  console.log('  every operation the app should reach, it reaches.\n');
}

console.log(`not for the app (${excused.length}):`);
for (const o of excused) {
  console.log('  ', o.path.padEnd(48), NOT_FOR_THE_APP.get(o.path));
}
console.log(
  '\nAn entry in the second list is a claim that the endpoint has no business in the client.\n'
  + 'Anything merely unbuilt belongs in the first one. docs/BUILD_STATE.md carries the history.',
);
