#!/usr/bin/env node
/**
 * Bring the whole stack up for local testing, in the one order that works.
 *
 * Every step here is something that has cost real time when done by memory: the portable Postgres
 * is not a service and a fresh session usually has nothing on 5433; the API must be on :8100 and
 * the app on :4200 (the app's dev environment points at the former, and other ports have host
 * permissions this machine does not grant); and `ng serve` must run the DEVELOPMENT configuration,
 * because a production build points at a same-origin `/api/v1` that is not there and every screen
 * quietly degrades to its fixtures.
 *
 * Run: `npm run dev` from the repo root. Ctrl-C stops the servers; Postgres is left running,
 * because other things on this machine may be using it.
 */
import { spawn, spawnSync } from 'node:child_process';
import { existsSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { homedir } from 'node:os';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');
const backend = join(root, 'backend');
const mobile = join(root, 'mobile');
const scoop = join(homedir(), 'scoop');
const pg = join(scoop, 'apps', 'postgresql16', 'current');

const API_PORT = 8100;
const APP_PORT = 4200;
const DB_PORT = 5433;

function log(step, message) {
  console.log(`\x1b[36m[${step}]\x1b[0m ${message}`);
}

function fail(message) {
  console.error(`\x1b[31m[fail]\x1b[0m ${message}`);
  process.exit(1);
}

/** Scoop's shims are often missing from a fresh shell's PATH — put them on for the children. */
const env = { ...process.env, PATH: `${join(scoop, 'shims')};${process.env.PATH ?? ''}` };

function run(command, args, options = {}) {
  return spawnSync(command, args, { env, encoding: 'utf8', shell: true, ...options });
}

// --- 1. Postgres -----------------------------------------------------------
log('db', `checking PostgreSQL on :${DB_PORT}`);
const ready = run(join(pg, 'bin', 'pg_isready.exe'), ['-p', String(DB_PORT), '-q']);
if (ready.status !== 0) {
  if (!existsSync(pg)) {
    fail(`PostgreSQL 16 not found at ${pg}. See docs/BUILD_STATE.md for how this machine is set up.`);
  }
  log('db', 'not running — starting it (portable install, not a Windows service)');
  const started = run(join(pg, 'bin', 'pg_ctl.exe'), [
    '-D', `"${join(pg, 'data')}"`,
    '-l', `"${join(pg, 'data', 'logfile.log')}"`,
    '-o', `"-p ${DB_PORT}"`,
    'start',
  ]);
  if (started.status !== 0) {
    fail(`could not start PostgreSQL:\n${started.stderr || started.stdout}`);
  }
} else {
  log('db', 'already up');
}

// --- 2. Migrate + seed -----------------------------------------------------
// `--seed` is not used: DatabaseSeeder is the production-safe one (staff roles + the real skill
// taxonomy). The demo data a person needs to click through the product is two extra seeders.
if (process.argv.includes('--fresh')) {
  log('db', 'migrate:fresh + demo seed (this DROPS the local database)');
  const migrated = run('php', ['artisan', 'migrate:fresh', '--force'], { cwd: backend, stdio: 'inherit' });
  if (migrated.status !== 0) fail('migration failed');
  for (const seeder of ['DemoSeeder', 'DemoCoverageSeeder']) {
    log('db', `seeding ${seeder}`);
    const seeded = run('php', ['artisan', 'db:seed', '--force', '--class', `Database\\\\Seeders\\\\${seeder}`], { cwd: backend, stdio: 'inherit' });
    if (seeded.status !== 0) fail(`${seeder} failed`);
  }
} else {
  log('db', 'applying any pending migrations (pass --fresh to reset and reseed)');
  run('php', ['artisan', 'migrate', '--force'], { cwd: backend, stdio: 'inherit' });
}

// --- 3. The two servers ----------------------------------------------------
const children = [];

function serve(name, command, args, cwd) {
  log(name, `${command} ${args.join(' ')}`);
  const child = spawn(command, args, { cwd, env, shell: true, stdio: 'inherit' });
  child.on('exit', (code) => {
    if (code !== 0 && code !== null) console.error(`\x1b[31m[${name}] exited with ${code}\x1b[0m`);
  });
  children.push(child);
}

serve('api', 'php', ['artisan', 'serve', '--host=127.0.0.1', `--port=${API_PORT}`], backend);
// `ng serve` defaults to the development configuration — which is what points the app at :8100.
serve('app', 'npx', ['ng', 'serve', '--host=127.0.0.1', `--port=${APP_PORT}`], mobile);

console.log(`
\x1b[32mUp.\x1b[0m
  App            http://localhost:${APP_PORT}
  Public site    http://localhost:${API_PORT}
  Admin panel    http://localhost:${API_PORT}/admin

  Sign in to the app with any of the seeded phone numbers — the code appears on the verify
  screen itself (local only). See docs/LOCAL_TESTING.md for who is who and what to click.

Ctrl-C to stop.
`);

for (const signal of ['SIGINT', 'SIGTERM']) {
  process.on(signal, () => {
    for (const child of children) child.kill();
    process.exit(0);
  });
}
