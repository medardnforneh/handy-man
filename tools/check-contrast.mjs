#!/usr/bin/env node
// WCAG contrast gate over the design tokens (launch checklist, doc 05: "Both themes pass WCAG AA
// contrast, verified independently — not by inversion").
//
// The pairs below are the foreground/background combinations the surfaces actually draw — read
// from the handoff README's "Contrast note" and the templates, not imagined. Each carries the
// threshold its USE demands: 4.5:1 for text people read at body size, 3:1 for large text
// (≥ 18.66px bold or 24px regular) and for interface chrome — a line, an icon, a focus ring, a
// filled control's edge against its ground. Both theme slots are checked; today they hold the same
// dark palette (the redesign is dark-only), so the second run is a guard against the day a light
// theme is designed and somebody forgets this gate.
//
// Translucent tokens (`rgba(…)`) are composited over their background before measuring, which is
// what the eye sees. Exits non-zero on any failure, so it belongs in `verify:frontend` and CI.

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
  ['text.primary', 'surface.sunken', TEXT, 'text on an inset chip / the tab bar'],
  ['text.secondary', 'surface.raised', TEXT, 'an unread preview on a card'],
  ['text.tertiary', 'surface.base', TEXT, 'marketing body copy on the ground'],
  ['text.muted', 'surface.base', TEXT, 'labels and metadata on the ground'],
  ['text.muted', 'surface.raised', TEXT, 'labels and metadata on a card (the README\'s ~5.6:1)'],
  ['text.muted', 'surface.sunken', TEXT, 'a label on an inset chip'],
  // The README: muted-dark is "used ONLY for uppercase micro-labels at 10–11.5px/800 in table
  // headers" and measures ~3.0:1 — the large-text/chrome bar, never sentence copy.
  ['text.faint', 'surface.rail', LARGE, 'table column headers (micro-labels only, never copy)'],
  // The accent is small text in this design (eyebrows, "Filter", "All jobs"): 12:1 on the ground.
  ['brand.primary', 'surface.base', TEXT, 'the accent as small text on the ground'],
  ['brand.primary', 'surface.raised', TEXT, 'the accent as small text on a card'],
  ['brand.primary', 'brand.tint', TEXT, 'accent text on the accent tint (the "Quote" button, pills)'],
  ['brand.strong', 'surface.base', TEXT, 'a hovered link'],
  ['status.success', 'status.successTint', TEXT, 'a completed / paid pill'],
  ['status.warning', 'status.warningTint', TEXT, 'an in-progress / submitted pill'],
  ['status.danger', 'status.dangerTint', TEXT, 'an exception / returned pill'],
  ['status.info', 'status.infoTint', TEXT, 'an open / engaged pill'],
  ['status.warning', 'surface.raised', TEXT, 'the "Need you" counter, a rating'],
  ['status.danger', 'surface.raised', TEXT, '"Log out", an exception title'],
  // "Ink on the accent fill is always #0B0F0E; white on #25E08C fails."
  ['brand.onPrimary', 'brand.primary', TEXT, 'a button label on the accent fill'],
  ['text.onInverse', 'surface.inverse', TEXT, 'copy on the accent field (the CTA panel, the primary job card)'],
  ['brand.primary', 'surface.raised', CHROME, 'accent icons and a focus ring against a card'],
  ['border.strong', 'surface.raised', 1.2, 'the secondary button border (a hairline, drawn not read)'],
];

/**
 * Pairs that do not meet AA and are shipped anyway, each with the reason and who decided. They are
 * measured and PRINTED on every run so the shortfall never becomes invisible, but do not fail the
 * gate. Adding to this list is a founder decision, not a way past a red build.
 */
const waived = [];

function srgbToLinear(c) {
  const v = c / 255;
  return v <= 0.04045 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4;
}

/** Parse `#rrggbb` or `rgba(r,g,b,a)` into [r, g, b, a]. */
function parse(value) {
  const hex = /^#([0-9a-f]{6})$/i.exec(value.trim());
  if (hex) {
    const n = parseInt(hex[1], 16);
    return [n >> 16, (n >> 8) & 255, n & 255, 1];
  }
  const rgba = /^rgba?\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*(?:,\s*([\d.]+)\s*)?\)$/i.exec(value.trim());
  if (rgba) return [Number(rgba[1]), Number(rgba[2]), Number(rgba[3]), rgba[4] === undefined ? 1 : Number(rgba[4])];
  throw new Error(`not a colour this gate can measure: ${value}`);
}

/** Composite a (possibly translucent) colour over an opaque one. */
function over(fg, bg) {
  const [r, g, b, a] = fg;
  return [r * a + bg[0] * (1 - a), g * a + bg[1] * (1 - a), b * a + bg[2] * (1 - a), 1];
}

function luminance([r, g, b]) {
  return 0.2126 * srgbToLinear(r) + 0.7152 * srgbToLinear(g) + 0.0722 * srgbToLinear(b);
}

/** Every translucent background sits on the page ground; a translucent foreground on its background. */
function contrast(fgValue, bgValue, groundValue) {
  const ground = parse(groundValue);
  const bg = over(parse(bgValue), ground);
  const fg = over(parse(fgValue), bg);
  const [l1, l2] = [luminance(fg), luminance(bg)].sort((x, y) => y - x);
  return (l1 + 0.05) / (l2 + 0.05);
}

let failures = 0;
for (const theme of ['light', 'dark']) {
  console.log(`\n${theme}`);
  const ground = tokens.color['surface.base'][theme];
  for (const [fg, bg, min, what] of pairs) {
    const ratio = contrast(tokens.color[fg][theme], tokens.color[bg][theme], ground);
    const ok = ratio >= min;
    if (!ok) failures++;
    console.log(`  ${ok ? 'ok  ' : 'FAIL'} ${ratio.toFixed(2).padStart(5)} ≥ ${String(min).padEnd(3)} ${fg} on ${bg} — ${what}`);
  }
  for (const [fg, bg, min, what, why] of waived) {
    const ratio = contrast(tokens.color[fg][theme], tokens.color[bg][theme], ground);
    if (ratio >= min) continue; // a waiver that now passes is just a pass
    console.log(`  WAIVED ${ratio.toFixed(2).padStart(5)} < ${min} ${fg} on ${bg} — ${what}\n         ${why}`);
  }
}

console.log(failures === 0 ? '\ncontrast: every pair meets its threshold in both themes.' : `\ncontrast: ${failures} pair(s) below threshold.`);
process.exitCode = failures === 0 ? 0 : 1;
