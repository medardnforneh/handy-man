# 11 — Deployment

> The production stack, as code, in `deploy/`. One box, in country (ADR 0001), everything in
> containers, TLS handled for you. Written 2026-09-13 because every launch-checklist item that was
> left — reconciliation clean for seven days, a real payout, template approval — needs a staging
> environment to happen on, and there was no way to stand one up.

## What runs where

One host, one `docker compose` project, two hostnames:

| Hostname | Serves | How |
|---|---|---|
| `handyman.cm` (`SITE_HOST`) | The public site, `/admin` (Filament), `/api/v1`, webhooks | Caddy → PHP-FPM |
| `app.handyman.cm` (`APP_HOST`) | The PWA, with `/api`, `/broadcasting`, `/storage` and the websocket on the **same origin** | Caddy → static + PHP-FPM + Reverb |

The app is built for one origin (`environment.prod.ts`: `/api/v1` relative, Reverb on the page's
own hostname over 443), which is why the PWA gets a hostname of its own rather than a path under
the site. Packaged Android/iOS builds point at `NATIVE_API_ORIGIN` = `https://app.handyman.cm`.

Services (`deploy/compose.yml`):

| Service | Image | Role |
|---|---|---|
| `web` | `handyman-web` (Caddy) | TLS from Let's Encrypt, HTTP/2+3, compression, static files, the PWA, FastCGI to `app`, websocket to `reverb` |
| `app` | `handyman-app` (PHP-FPM 8.3) | every HTTP request |
| `outbox` | same image | `outbox:relay` — the transactional-outbox daemon; **every** push, WhatsApp, deposit capture and broadcast rides it |
| `horizon` | same image | the Redis queue |
| `scheduler` | same image | `schedule:work` — the tick for `routes/console.php` |
| `reverb` | same image | the realtime server on 8080, internal only |
| `postgres` | `postgis/postgis:16-3.4` | the database; extensions enabled at first boot (`deploy/postgres/init.sql`) |
| `redis` | `redis:7` | cache, sessions, queue, Horizon |

One PHP image for all five PHP roles: the code that answers a request, relays the outbox, works
the queue and serves the socket is one build. Caddy's image carries a copy of `public/` at the
same path FPM sees it at, so FastCGI's `SCRIPT_FILENAME` lines up.

Uploads (job photos, reports, verification documents) live on the `storage` volume, local disk,
for v1. The verification disk is a separate Laravel disk by design (doc 04) and can move to an
in-country object store without touching anything else.

## First deployment

On a fresh Ubuntu box with Docker Engine + the compose plugin, as a user in the `docker` group:

```bash
git clone <repo> handyman && cd handyman
cp deploy/.env.production.example deploy/.env
$EDITOR deploy/.env          # hostnames, ACME email, APP_KEY, DB password, Reverb secret
deploy/deploy.sh --first
```

`--first` also seeds the staff roles and the bilingual trade catalogue (`DatabaseSeeder` — the
production-safe seeder; never `DemoSeeder`, which creates `admin@handyman.cm / password`), then
asks for the first superadmin's email and phone and prints a **one-time password**. Sign in at
`https://<SITE_HOST>/admin`; the panel insists on 2FA enrolment before showing anything (P1-09).

DNS for both hostnames must already point at the box: Caddy obtains the certificates on first
request and renews them itself. Ports 80 and 443 (TCP and UDP) open; nothing else.

## Every deployment after

```bash
git pull
deploy/deploy.sh
```

Builds the new images while the old ones keep serving, runs pending migrations (transactional on
Postgres — a failed one leaves the old schema), restarts every role on the new image (the
entrypoint re-warms config/route/view/event caches from the environment), tells Horizon to cycle,
and checks `/up`.

There is **no `.env` inside the image**. Every setting comes from `deploy/.env` through compose;
`config:cache` at container start is what makes that work. Change a value → `deploy/deploy.sh`
(or `docker compose … up -d` for a config-only change).

## Retiring an app build (the kill switch, P0-08)

Set `API_MIN_APP_VERSION` above the build you want gone and redeploy. Every request from an
older app gets 426 and the app stops on its "Update required" screen with the store / reload
action. Tested against a real build (launch checklist).

## Backups

The two things that cannot be rebuilt are the `pgdata` volume and the `storage` volume. A nightly
`pg_dump` and an rsync of the storage volume to a second location are the minimum; they are not
in this stack because where they go is a hosting decision (the ADR's in-country requirement
applies to backups too).

```bash
docker compose -f deploy/compose.yml --env-file deploy/.env exec -T postgres \
  pg_dump -U "$DB_USERNAME" -Fc "$DB_DATABASE" > "backup-$(date +%F).dump"
```

## Staging

The same stack with a second `deploy/.env` (`SITE_HOST=staging.handyman.cm`,
`APP_HOST=app-staging.handyman.cm`, `PAYMENTS_GATEWAY=cinetpay` pointed at the sandbox,
`WHATSAPP_SENDER=log`). That is the environment "reconciliation runs clean for 7 consecutive
days" and "payout tested with real money, and reversed" run on.

## Not yet done — honest list

- **WhatsApp** has its adapter (`WHATSAPP_SENDER=meta`, the Cloud API, doc 07 "The WhatsApp
  template") but has never spoken to Meta: it pends a Business portfolio, a System User token
  and the `handyman_follow_up` template approved in fr + en. Until then `log`.
- **SMS** goes through Twilio (`SMS_SENDER=twilio`) — the OTP (`OTP_SENDER=sms`), panic alerts
  and the ladder's last rung. It has never sent a real text: pends an account, a sender (the
  alphanumeric `HandyMan` or a number) and a first paid message to an MTN and an Orange number.
  Twilio is the expensive option per text; a Cameroonian aggregator, chosen for price once volume
  says so, is another class behind the same interface.
- **FCM** exchanges the Firebase service account for its hourly bearer itself
  (`FcmAccessToken`, cached 55 min, re-minted on a 401) and clears a registration token Google
  reports as gone. Never pushed to a real device: pends the Firebase project, the service-account
  JSON in `FCM_SERVICE_ACCOUNT_JSON`, and the Android app registering a token through
  `POST /devices` on a physical phone (launch checklist).
- **CinetPay operator codes** (`MTNCM` / `OMCM`) and the webhook token field order are to be
  confirmed against the live sandbox — the adapter says so in its own comments.
- **Object storage**: local disk for v1 (see above).
- **Monitoring**: container logs are JSON on stdout; nothing ships them anywhere yet.
