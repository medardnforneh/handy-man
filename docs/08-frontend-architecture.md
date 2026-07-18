# 08 — Frontend architecture (decision record)

Optimising for, in order: **the builder ships in what they master**, **one codebase across web
and mobile**, **UI/UX quality**, **performance on the devices and networks that exist here**,
**SEO as an acquisition channel**.

> **Revision history.** This doc has flipped twice as constraints surfaced. v1 said Inertia +
> React. v2 said Flutter everywhere (best raw performance on cheap Android). **v3 — this one —
> chooses Ionic + Capacitor, because the builder masters Laravel and web tech and does not know
> Dart.** That single fact outweighs the benchmark deltas: a shipped Ionic app beats a
> better-on-paper Flutter app that is still being learned eight months from now. Flutter's
> reasoning is preserved below as the documented alternative, with an explicit switch trigger.

## Decision

| Surface | Stack | Runs on | Why |
|---|---|---|---|
| Public discovery | **Blade + Alpine + Tailwind** | Web | SEO. Server-rendered HTML crawlers can read |
| **The app** | **Ionic + Capacitor** (Vue or Angular + TypeScript) | **Web (PWA) + Android + iOS** | One web codebase, wrapped native. The builder's home turf |
| Admin | **Filament 5** | Web only | Unmatched for review queues; never on mobile |
| Realtime | **Laravel Reverb** | — | Ephemeral events; never the source of truth |

**The entire stack is now Laravel + web technology.** Blade, Filament, a Laravel API, and an
Ionic app that is HTML/CSS/TS under the hood. No new language anywhere. The only genuinely new
thing to learn is Capacitor's native-bridge plugins (camera, geolocation, push) — days of
learning, not months, and the web layer is unchanged from what the builder already knows.

## Why Ionic + Capacitor

**Capacitor** (the modern successor to Cordova, by the Ionic team) wraps a web app in a native
shell with a system WebView and bridges to native device APIs. **Ionic** is its UI component
library. You build one web app; Capacitor packages it for the stores.

The reasons it wins *here specifically* — not in the abstract:

1. **It's the builder's mastered stack.** The largest risk on a solo build of this size is not
   shipping before momentum runs out. Building in a known stack is the single biggest lever on
   that risk. Every other framework spends weeks-to-months on a learning curve that buys a
   performance margin most screens never need.
2. **One codebase, web + mobile, genuinely.** Unlike React Native (which reuses logic but makes
   you rewrite the UI in native components), Capacitor reuses the *actual web UI*. The PWA and
   the store apps are the same code.
3. **It collapses cleanly into the access model** (doc 10): one app, both sections always
   visible, navigation never gated. That's plain web routing — no native complexity.
4. **Lowest upgrade friction of the cross-platform options** — the web layer is independent of
   the native build system, so most changes ship without touching Xcode/Android Studio.

## The one real cost — WebView performance, and exactly where it lands

Capacitor's runtime *is* the device's WebView. On a $70 Tecno that's an older, slower Chromium
on a weak CPU. The honest performance ranking for raw interaction is Flutter, then React Native,
then Capacitor — and the Capacitor gap is real, not rhetorical.

**But locate the cost precisely before pricing it in.** For this product it concentrates in one
place: the **engagement workspace** (doc 06) — live chat, typing indicators, voice notes,
optimistic sends — on the weakest devices, will feel less buttery than native. Everywhere else —
discovery, booking, forms, dashboards, provider CRM, quote building, the whole customer journey —
Capacitor is entirely fine. Burger King, Sworkit, and many others ship exactly that shape to
millions.

Mitigations for the one hot surface:

- Virtualize the message list (only render visible rows) — the standard fix, framework-agnostic.
- Keep the workspace DOM lean; avoid heavy reactive watchers on the chat view.
- Reconcile over REST on reconnect (doc 06) — the socket is an accelerant, not the data path.
- Record voice notes via the Capacitor native plugin, not a WebView MediaRecorder polyfill.
- Test the workspace on a real low-end Android early (doc 05), not at the end. If it proves
  genuinely inadequate *after* real optimisation, the switch trigger below exists for a reason —
  but do not pre-emptively switch on a benchmark. Measure on the actual screen.

## The alternative, kept with its trigger: Flutter

Flutter remains the better choice on pure device performance, because it ships its own renderer
and sidesteps the WebView entirely — it draws identical pixels on any Android skin, and its
memory footprint is smaller (~14MB vs ~33MB Android delta in published comparisons; treat as
directional, the sourcing is weak). On Transsion hardware (TECNO/Infinix/itel, ~48% of African
shipments, 81% of shipments sub-$200) that rendering consistency is a correctness property.

**None of that was ever the deciding factor. The deciding factor is who's building it.** The
benchmarks are also mutually contradictory (one source: Flutter cold-start ~250ms vs RN ~350ms;
another: RN ~200ms *faster*) — the gap is inside measurement noise. Do not switch stacks over
noise.

**Switch from Ionic to Flutter only if all three hold:**

1. The workspace on a real low-end Android is inadequate *after* genuine web optimisation — not
   suspected from a blog benchmark, measured on the device.
2. Mobile-app (not mobile-web) usage is proven to dominate, so the native-performance ceiling is
   actually the thing users hit.
3. There is budget (time or a hire) to absorb a Dart/Flutter learning curve without stalling the
   roadmap.

Fewer than three → stay on Ionic. Instrument #2 from launch so the decision is data, not vibes.

## Why not Livewire for the app (retained)

Every Livewire interaction is a server round-trip. There is no AWS region in Central/West
Africa; nearest is Cape Town (`af-south-1`), ~100–150ms RTT from Yaoundé before the mobile leg —
call it 400–700ms per interaction on 3G. A typing indicator over Livewire is a request per
keystroke. Wrong architecture for the workspace. The Ionic app talks to the API and holds its
own state; Livewire is correct for exactly one thing — **Filament** — where it's excellent.

## Why not put admin in the app

The instinct to unify "web client + admin" optimises the wrong seam. Client and admin share
almost no UI. Admin is verification review queues, dispute adjudication, and reconciliation
exceptions — table-heavy CRUD with filters, bulk actions, and audit, which Filament delivers in
days. And **admin holds a different threat model**: it can view national-ID documents and move
money. Keeping it a separate Filament deployment means those capabilities are physically not in
the app untrusted users hold — defense in depth for the most sensitive data in the system. Admin
is desk work; nobody adjudicates a dispute on a phone. Web-only, behind a `superadmin`/staff
role. See doc 10.

## Design tokens and theming (light + dark)

One source, three outputs. This is what makes dark mode a config change, not a project.

```
tokens.json ──┬──> tailwind.config.js   (Blade + Filament)
              ├──> Ionic CSS variables  (the app: --ion-color-* + custom props)
              └──> filament theme overrides
```

Generated in CI. A token change updates every surface, or the build fails. **Ionic has
first-class theming via CSS custom properties and ships light/dark support** — map its variables
from the same `tokens.json`, don't hand-maintain a parallel palette.

### Semantic tokens only

```jsonc
// ✅ semantic — one ramp swap flips the whole app
"surface.base","surface.raised","surface.sunken",
"text.primary","text.muted","text.inverse",
"border.subtle","border.strong",
"brand.primary","brand.onPrimary",
"status.success","status.warning","status.danger","status.info"
// ❌ literal — dark mode becomes a thousand overrides
"gray.700","blue.500"
```

Never reference a literal colour in a component. Enforce with a lint rule.

### Dark mode rules

1. **Design both themes. Never invert.** Verify contrast independently per theme (WCAG AA: 4.5:1
   body, 3:1 large).
2. **Customer-facing views follow the system**, with a manual override persisted on the device
   (Capacitor Preferences).
3. **Provider working views default to LIGHT.** Dark surfaces lose contrast and reflect glare in
   direct equatorial sun, and provider work happens outdoors. Dark is an explicit opt-in there.
   Test on a real device, outside, at midday.
4. **Dark mode saves no battery for most users.** The OLED power win doesn't apply to the LCD
   panels on most sub-$200 Transsion devices. Comfort feature, not efficiency — don't market it
   as battery-saving.
5. **Never dim, tint, or invert media.** Before/after photos are dispute evidence — true
   luminance, neutral surround, both themes.
6. **Status colours keep meaning across themes.** Adjust luminance, never hue.
7. **Filament ships dark mode** — theme it from the same tokens so admin doesn't drift.

## Design constraints (framework-independent)

Decisive for UI/UX quality, specific to this market:

- **Large tap targets** — 48px min, 56px primary. Providers have dirty hands, sometimes gloves.
- **High contrast** — provider views used in direct sun. Test outdoors.
- **One-handed reach** — primary actions in the bottom third. Ionic tab bars sit there naturally.
- **French-first, English parity** — `locale` defaults to `fr`. Both official. Design for the
  longer string (French runs ~15–20% longer). See doc 09.
- **Offline as a first-class state, not an error toast** — cached data, queued writes with
  idempotency keys, a clear queued/synced indicator. A check-in in a basement must work. Use
  Capacitor Preferences / SQLite for the queue.
- **Voice notes** — first-class in the workspace, via the native recorder plugin.
- **Skeletons, never spinners** — on a 700ms network a spinner reads as broken.
- **Use Ionic's platform-adaptive mode** — it renders Material on Android and iOS styling on
  iOS automatically. Theme it hard so it carries the brand rather than looking like stock Ionic;
  generic is a trust problem when brokering physical access to homes.

## Native capabilities via Capacitor plugins

The app needs these bridged — all have official or well-maintained Capacitor plugins:

| Need | Plugin | Notes |
|---|---|---|
| Camera / photo | `@capacitor/camera` | before/after evidence, ID capture |
| Geolocation | `@capacitor/geolocation` | check-in; foreground for most flows |
| **Background location** | community plugin | **only where a job is active** — see doc 10 permission note |
| Push | `@capacitor/push-notifications` + FCM | via the outbox |
| Secure storage | `@capacitor/preferences` + Keychain/Keystore | refresh token (doc 04) |
| Local DB / offline queue | `@capacitor/sqlite` (community) | write queue with idempotency keys |
| File upload | presigned direct-to-S3 | API never proxies media bytes |

**Permission-manifest note:** because customer and provider live in one app (doc 10), the binary
declares camera/location permissions a pure customer may never exercise. Gate the actual
capability at runtime and request the permission *only when the user first hits the action that
needs it* (first check-in, first ID upload) — never up front. This is how Uber/Bolt/Grab ship a
single app; it's a known, minor store-review note, not a blocker.

## Stack summary for Claude Code

```
Backend     Laravel 13 · PHP 8.3 · PostgreSQL 16 + PostGIS · Redis · Horizon · Reverb
Admin       Filament 5 (Livewire) — admin only, web only, never mobile
Public web  Blade + Alpine 3 + Tailwind — SEO surface, minimal JS, fully accessible
The app     Ionic 8 + Capacitor 6 · Vue 3 or Angular + TypeScript
            → PWA (web) + Android + iOS from ONE codebase
            Capacitor plugins: camera, geolocation, push, preferences, sqlite
            Pinia/NgRx state · TanStack Query equivalent for server cache
Contracts   OpenAPI 3.1 → TypeScript client, generated in CI. Never hand-written.
Tokens      tokens.json → Tailwind + Ionic CSS vars + Filament theme. Semantic names only.
```

**Framework within Ionic:** Vue or Angular, builder's choice — both are first-class in Ionic.
Vue is lighter and closer to the Blade/Alpine mental model already in use; Angular is more
structured for a large app. Pick one and commit; do not mix.
