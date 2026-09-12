#!/usr/bin/env node
// WCAG contrast gate over the design tokens (launch checklist, doc 05: "Both themes pass WCAG AA
// contrast, verified independently — not by inversion").
//
// The pairs below are the foreground/background combinations the three surfaces actually draw —
// read from the templates, not imagined. Each carries the threshold its USE demands: 4.5:1 for
// text people read at body size, 3:1 for large text (≥ 18.66px bold or 24px regular) and for
// interface chrome — a rule, an icon, a focus ring, a filled control's edge against its ground.
// Light and dark are checked separately, because the palette is designed twice, not inverted.
//
// Exits non-zero on any failure, so it belongs in `verify:frontend` and CI.

import { readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const tokens = JSON.parse(readFileSync(join(dirname(fileURLToPath(import.meta.url)), '..', 'tokens', 'tokens.json'), 'utf8'));

const TEXT = 4.5;
const LARGE = 3;
const CHROME = 3;

/** [foreground, background, minimum ratio, what it is]. */
const pairs = [
  ['text.primary', 'surface.base', TEXT, 'body copy on the ground'],
  ['text.primary', 'surface.raised', TEXT, 'body copy on a card'],
  ['text.primary', 'surface.sunken', TEXT, 'input text'],
  ['text.muted', 'surface.base', TEXT, 'secondary copy on the ground'],
  ['text.muted', 'surface.raised', TEXT, 'secondary copy on a card'],
  // brand.primary is NOT a text colour at body size — the Modernist readme says so itself (its
  // accent-to-ground pair is tuned to ~3:1, "enough for icons, large text and interface chrome, not
  // for body copy"). Small accent text is brand.strong, the ramp's 700 step; the templates use
  // `text-brand-strong` for links, "See all", kickers and selected labels, and `text-brand` only
  // on icons, fills and display-size type.
  ['brand.strong', 'surface.base', TEXT, 'the accent as small text on the ground (links, "See all", kickers)'],
  ['brand.strong', 'surface.raised', TEXT, 'the accent as small text on a card'],
  ['brand.strong', 'brand.tint', TEXT, 'tag text on the accent tint'],
  ['status.success', 'surface.raised', TEXT, 'status pill text on a card'],
  ['status.warning', 'surface.raised', TEXT, 'status pill text on a card'],
  ['status.danger', 'surface.raised', TEXT, 'status pill text on a card'],
  ['status.info', 'surface.raised', TEXT, 'status pill text on a card'],
  ['text.onInverse', 'surface.inverse', TEXT, 'copy on the inverted band'],
  ['brand.onInverse', 'surface.inverse', TEXT, 'the accent as small text on the inverted band (a kicker)'],
  ['brand.primary', 'surface.inverse', CHROME, 'accent icons on the inverted band'],
  ['brand.primary', 'surface.base', CHROME, 'accent icons and the focus ring against the ground'],
  ['border.strong', 'surface.base', CHROME, 'the 2px rule against the ground'],
  ['border.strong', 'surface.raised', CHROME, 'the 2px rule inside a card'],
  ['brand.primary', 'surface.raised', CHROME, 'a filled control against a card'],
];

/**
 * Pairs that do not meet AA and are shipped anyway, each with the reason and who decided. They are
 * measured and PRINTED on every run so the shortfall never becomes invisible, but do not fail the
 * gate. Adding to this list is a founder decision, not a way past a red build.
 */
const waived = [
  ['brand.onPrimary', 'brand.primary', TEXT, 'a button label on the accent fill',
    'The brand accent is #ec3013 by the founder\'s design (Modernist). No label colour reaches 4.5:1 on '
    + 'it — the ground gives 3.76, ink gives 3.73 — and the fill is the product\'s primary action. '
    + 'Labels are 800-weight, which helps legibility but not the ratio. Meets the 3:1 large-text bar '
    + 'only. To pass AA the fill would have to move to the 700 step (#ae1800), a brand decision.'],
];

function srgbToLinear(c) {
  const v = c / 255;
  return v <= 0.04045 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4;
}

function luminance(hex) {
  const m = /^#([0-9a-f]{6})$/i.exec(hex.trim());
  if (!m) throw new Error(`not an opaque hex colour: ${hex}`);
  const n = parseInt(m[1], 16);
  return 0.2126 * srgbToLinear(n >> 16) + 0.7152 * srgbToLinear((n >> 8) & 255) + 0.0722 * srgbToLinear(n & 255);
}

function contrast(a, b) {
  const [l1, l2] = [luminance(a), luminance(b)].sort((x, y) => y - x);
  return (l1 + 0.05) / (l2 + 0.05);
}

let failures = 0;
for (const theme of ['light', 'dark']) {
  console.log(`\n${theme}`);
  for (const [fg, bg, min, what] of pairs) {
    const ratio = contrast(tokens.color[fg][theme], tokens.color[bg][theme]);
    const ok = ratio >= min;
    if (!ok) failures++;
    console.log(`  ${ok ? 'ok  ' : 'FAIL'} ${ratio.toFixed(2).padStart(5)} ≥ ${String(min).padEnd(3)} ${fg} on ${bg} — ${what}`);
  }
  for (const [fg, bg, min, what, why] of waived) {
    const ratio = contrast(tokens.color[fg][theme], tokens.color[bg][theme]);
    if (ratio >= min) continue; // a waiver that now passes is just a pass
    console.log(`  WAIVED ${ratio.toFixed(2).padStart(5)} < ${min} ${fg} on ${bg} — ${what}\n         ${why}`);
  }
}

console.log(failures === 0 ? '\ncontrast: every pair meets its threshold in both themes.' : `\ncontrast: ${failures} pair(s) below threshold.`);
process.exitCode = failures === 0 ? 0 : 1;
