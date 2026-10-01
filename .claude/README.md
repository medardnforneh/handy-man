# Claude Code on the web — container setup

Two files, one job: make a fresh cloud container able to run this project's gates.

| File | What it is |
|---|---|
| `hooks/install-deps.sh` | All the work. Runnable on its own. |
| `hooks/session-start.sh` | The `SessionStart` hook — guards on "is this a web session", then calls the installer. |
| `settings.json` | Registers the hook. |

## The environment setup script

Paste this one line into the cloud environment's **setup script** (the cloud environment menu in
the session's title bar → Edit):

```bash
bash .claude/hooks/install-deps.sh
```

Same implementation as the hook, so the two cannot drift. It is optional — the hook already runs on
every session — but the setup script runs once per container rather than once per session, so
putting it in both makes a resumed session start instantly.

## What a web session can and cannot do

The installer prints this on every session. Short version:

**Works** — the root gates (`npm run verify:frontend`, `check:uncalled`, `i18n:build`,
`tokens:build`; the root `package.json` has no dependencies at all), the Ionic app (`build`,
`test:ci`, `lint`, `api:generate`), the Vite asset build, and **Postgres 16 + PostGIS** on 5432
with `handyman_test` ready — the installer installs PostGIS and starts the cluster, so the
testing floor's "real Postgres, never SQLite" is satisfiable here.

**Does not work** — `composer install`, and therefore Pint, PHPStan and Pest. Not a Composer
problem: this session's proxy gates GitHub to the repositories attached to the session, and every
route to a third-party package is refused.

```
api.github.com/repos/…/zipball/…   403   "GitHub access to this repository is not enabled"
codeload.github.com/…              403   same
github.com/…/archive/…             403   same
github.com/…/info/refs             401   realm="ccr-gitengine"   (so --prefer-source too)
gitlab.com/…/archive/…             200   ← so it is GitHub specifically, not archives
repo.packagist.org (metadata)      200
```

`GITHUB_TOKEN` is the literal sentinel `proxy-injected`, which Composer reads as a credential —
which is why the failure reads as *"Could not authenticate against github.com"* rather than as a
refusal. Clearing it does not help; the 403 is unconditional. Nor does a warm cache:
`--prefer-source` writes git mirrors **into** the cache rather than reading packages out of it, so
an attempt costs ten minutes and about 5.5 GB and still fails. That is why the installer **probes
first** with a single HTTP request and skips in under a second.

### To fix it

Allow a full Composer mirror host under **Network access** in the same environment settings
(non-GitHub mirrors are currently refused at the tunnel with `CONNECT 403`), then:

```bash
composer config -g repos.packagist composer https://<mirror>/composer/
```

That takes GitHub out of the loop for metadata and dists alike. A broader network access *level*
on its own probably will not help — the GitHub refusal cites the session's **repository** scope,
not a domain allowlist.

Until then the PHP gates are CI's, which is where they run on every push anyway
(`.github/workflows/ci.yml`).

## Notes

- **Synchronous** on purpose: nothing in the session can race a half-finished install. The cost is
  a slower start on a cold container (the PostGIS install); it is cached afterwards, and a warm run
  is about two seconds. Adding `echo '{"async": true, "asyncTimeout": 600000}'` as the first line
  of `session-start.sh` trades that back for a race.
- **Never fails the caller.** A session that will not start because a mirror was slow is worse than
  one that starts and says what is missing, so every step warns and carries on. `set -e` is
  deliberately absent.
- **Web only.** A local machine has its own Postgres, `vendor/` and `node_modules`, and re-running
  an installer there would be slower and might disagree with what the developer set up on purpose.
- `CHROME_BIN` is globbed, not pinned: `npm run test:ci` needs it, and without it Karma dies with a
  spawn `ENOTDIR` that reads like a test failure. A base-image browser bump must not break it.
- `DB_PORT` is exported as 5432. `phpunit.xml` pins 5433 (the dev machine runs PG16 there because
  PG13 owns 5432), and those `<env>` entries do not force — so a real environment variable wins,
  exactly as it does in CI.
