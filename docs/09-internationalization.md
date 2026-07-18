# 09 — Internationalization (FR / EN)

Cameroon is officially bilingual and genuinely split — roughly 80% Francophone, ~20%
Anglophone, and the Anglophone population is regionally concentrated (Northwest and Southwest
regions). This is not a cosmetic toggle. An Anglophone user handed a French-only interface reads
it as "this platform is not for me," and you lose the region. Both languages are first-class.

This doc is separate because i18n is a cross-cutting concern: it touches all four surfaces
(Blade, the Ionic app, Filament, and the comms templates), the **database**, search, and — because of
Law No. 2024/017 — legal compliance. Scattering `t('key')` calls through the UI without a plan
is how you ship a half-translated app.

## Three things people conflate

| Concept | Example | Where it lives |
|---|---|---|
| **UI language** | Button says "Book" vs "Réserver" | `users.locale` (`fr` \| `en`) |
| **Regional formatting** | `10 000 FCFA` vs `10,000 XAF`, date order, decimal comma | Derived: `fr-CM` / `en-CM` |
| **Communication language** | Which language the WhatsApp reminder is in | `users.comms_locale` — may differ from UI |

`comms_locale` is not redundant. A user may run the app in French but ask for reminders in
English, or vice versa. The follow-up system (doc 07) reads `comms_locale`, not `locale`.

```sql
ALTER TABLE users ADD COLUMN comms_locale text NOT NULL DEFAULT 'fr';  -- fr | en
-- locale already exists (doc 02). Both constrained to the supported set.
ALTER TABLE users ADD CONSTRAINT users_locale_supported CHECK (locale IN ('fr','en'));
ALTER TABLE users ADD CONSTRAINT users_comms_locale_supported CHECK (comms_locale IN ('fr','en'));
```

Default is `fr` (the majority), but **detect and offer** on first launch rather than assuming:
device locale on mobile, `Accept-Language` on web. Never silently lock someone to French because
that's the default — surface the choice, remember it.

## Two categories of translatable text — handle them differently

### 1. Static UI strings — standard i18n files

Interface chrome: buttons, labels, errors, empty states. Keyed translation files per surface,
all fed from **one source of truth** so a term is translated once, not four times.

```
i18n/
  fr.json   ┬──> Laravel lang/fr (Blade + Filament + API messages)
  en.json   ┴──> app i18n JSON (vue-i18n or ngx-translate) for the Ionic app
```

- **Laravel**: `lang/{fr,en}/*.php`, `__('...')`. Covers Blade, Filament, validation, API error
  bodies. Filament is localizable out of the box — set the panel locale from the admin's `locale`.
- **Ionic app**: `vue-i18n` (Vue) or `ngx-translate` (Angular), fed from the same keyed JSON.
  Locale driven by `users.locale`, overridable in-app, persisted via `@capacitor/preferences`.
- **Generate both from one keyed source in CI.** A key present in `fr` but missing in `en` fails
  the build. Untranslated keys must never reach a user as a raw `some.key.name`.

Rules:
- **No hard-coded user-visible string, anywhere.** Same lint posture as the no-literal-colour
  rule (doc 08). A bare string literal in a widget or a Blade view fails review.
- **Never concatenate translated fragments.** "You have " + n + " new offers" is unshippable —
  word order and pluralization differ. Use ICU MessageFormat with named placeholders and plural
  categories: `{count, plural, =0{Aucune offre} one{# offre} other{# offres}}`.
- **Design for the longer string.** French runs ~15–20% longer than English. Lay out and test
  against French; if it fits in French it fits in English, rarely the reverse.

### 2. Dynamic content — translated columns or a translations table

Data, not chrome. This is the part that's easy to forget and expensive to retrofit.

**Platform-controlled catalog** (you author it → paired columns, already the pattern in doc 02):

```sql
-- skills already has name_fr / name_en. Extend the SAME pattern to every catalog entity:
-- service categories, cancellation reasons, dispute categories, canned/quick-reply messages,
-- notification & WhatsApp/email template bodies, verification reject reasons, warranty terms.
```

For anything with more than a couple of translatable fields, prefer a translations table over an
ever-growing `_fr`/`_en` column pair:

```sql
translations (
  id uuid PK,
  translatable_type text NOT NULL,     -- 'skill' | 'service_category' | 'message_template' ...
  translatable_id uuid NOT NULL,
  locale text NOT NULL CHECK (locale IN ('fr','en')),
  field text NOT NULL,                 -- 'name' | 'description' | 'body'
  value text NOT NULL,
  UNIQUE (translatable_type, translatable_id, locale, field)
);
```

Either is fine; be consistent. Paired columns are simpler to query and index for a fixed
2-language, few-field case (skills); the table wins once fields multiply (templates).

**User-authored free text** (provider bios, job descriptions, quote line labels, chat
messages) — you do **not** translate these automatically. But you must **tag the language** so
the app can label and search correctly:

```sql
ALTER TABLE provider_profiles ADD COLUMN bio_language text;   -- fr | en | null (detected/declared)
ALTER TABLE jobs             ADD COLUMN description_language text;
```

- Show an on-demand "Translate" affordance (deferred to a future MT integration — doc 01 lists
  translation as a future enhancement; do not build it in v1). Until then, just label:
  "Written in English."
- **Postgres full-text search is language-specific.** `to_tsvector('french', ...)` and
  `to_tsvector('english', ...)` stem differently. Index provider/job text with the config that
  matches its `*_language`, or you get bad recall. This is the concrete reason to store the tag
  now even before any translate button exists.

## Comms templates (doc 07) are bilingual by construction

Every follow-up / notification / WhatsApp template exists in both languages, selected by
`comms_locale`:

- **WhatsApp Business API templates must be submitted and approved per language.** That doubles
  the approval queue — start early (doc 07 already flags the lead time; this makes it 2× the
  templates).
- SMS: watch length. French templates are longer and may cross the 160-char boundary into a
  second billed segment. Keep both variants under one segment where possible.
- Legal/consent copy: see below — the language rule there is stricter.

## Legal — consent language is not optional (Law No. 2024/017)

Consent must be informed. A consent captured against French text shown to a user operating in
English is on shaky ground. So:

- `consents` (doc 02/04) must record the **locale the policy was presented in**:

```sql
ALTER TABLE consents ADD COLUMN presented_locale text NOT NULL DEFAULT 'fr'
  CHECK (presented_locale IN ('fr','en'));
```

- Terms, privacy policy, and every consent prompt exist and are versioned in **both** languages.
  `policy_version` (doc 02) is shared across languages; the rendered text differs by locale.
- If you only have the legal text reviewed in one language at launch, you are only compliant for
  users in that language. Have the lawyer review both.

## Filament (admin)

- Admin UI localizable per admin's `locale` — Filament supports this natively.
- But **admin-authored content is bilingual data**: when an admin edits a skill name, a dispute
  category, or a message template, they must fill **both** language fields. Use Filament's
  translatable field pattern (e.g. a translatable form component) so a missing translation is a
  validation error, not a silent gap that surfaces as a raw key to an Anglophone user.

## Formatting

- **Money**: XAF has no minor unit in practice, but is stored at scale 0 (doc 03/CLAUDE.md).
  Display: French `10 000 FCFA` (space thousands sep, "FCFA" is the colloquial local name);
  English `10,000 XAF`. Localize the *format*, not the amount. Never a float, ever (unchanged).
- **Dates/times**: locale-formatted; store UTC, render local (Africa/Douala, WAT, UTC+1, no DST).
- **Never format numbers or dates by string concatenation.** Use the platform formatter
  (`Intl.NumberFormat`/`Intl.DateTimeFormat` in TS, `Number`/`Carbon` localization in PHP).

## Testing

- **Pseudo-localization / missing-key CI gate**: build fails if any UI key lacks an `fr` or `en`
  value.
- **Layout in French** for the key screens (booking, workspace, quote card, panic) — the long
  strings are the ones that overflow.
- A round-trip test: user sets `locale=en`, `comms_locale=fr` → UI renders English, the review
  reminder arrives in French.
- Search test: a French job description is found by a French stemmed query; an English one by an
  English query.
- Consent test: switching locale re-renders the consent text and records the new
  `presented_locale`.
