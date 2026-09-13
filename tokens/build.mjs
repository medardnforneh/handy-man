#!/usr/bin/env node
// Design-token build (build plan P0-11/13/14, doc 08).
//
// ONE source (tokens/tokens.json, semantic names) → every surface:
//   - tokens.css            semantic CSS custom properties (--hm-*), light + dark
//   - ionic-tokens.css      maps Ionic's --ion-* variables onto our --hm-* vars
//   - tailwind-theme.css    a Tailwind v4 @theme block referencing the same CSS vars
//
// All surfaces consume the SAME --hm-* variables, so a token change here flips Blade, the Ionic
// app and Filament at once. Light is the default (:root); dark applies via the system preference
// AND an explicit [data-theme="dark"] override, with [data-theme="light"] forcing light back.

import { readFileSync, writeFileSync, mkdirSync, existsSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const root = join(__dirname, '..');
const tokens = JSON.parse(readFileSync(join(__dirname, 'tokens.json'), 'utf8'));

const cssVar = (group, name) => `--hm-${group}-${name.replace(/\./g, '-')}`;

function colorVars(theme) {
  return Object.entries(tokens.color)
    .map(([name, val]) => `  ${cssVar('color', name)}: ${val[theme]};`)
    .join('\n');
}

function scalarVars(group) {
  return Object.entries(tokens[group] ?? {})
    .map(([name, val]) => `  ${cssVar(group, name)}: ${val};`)
    .join('\n');
}

const header = '/* GENERATED from tokens/tokens.json — do not edit by hand. Run `npm run tokens:build`. */\n';

/**
 * The typeface, self-hosted.
 *
 * The redesign is set entirely in Plus Jakarta Sans. It is served from OUR origin rather than
 * fonts.googleapis.com for two reasons that both matter here: the app is an installable PWA that has
 * to render offline, and a third-party font request is one more thing to wait for on the networks
 * this product is built for. Two variable-weight files (200–800; latin covers French's accents,
 * latin-ext its œ) total ~30kB with `font-display: swap`, so text is never invisible while it
 * loads. The directory differs per surface because each serves its assets from a different place,
 * so the generator takes it as an argument rather than guessing.
 */
function fontFace(fontDir) {
  const face = (file, range) => `@font-face {
  font-family: "Plus Jakarta Sans";
  font-style: normal;
  font-weight: 200 800;
  font-display: swap;
  src: url("${fontDir}/${file}") format("woff2");
  unicode-range: ${range};
}`;

  return [
    face('plus-jakarta-sans-latin.woff2', 'U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+0304, U+0308, U+0329, U+2000-206F, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD'),
    face('plus-jakarta-sans-latin-ext.woff2', 'U+0100-02BA, U+02BD-02C5, U+02C7-02CC, U+02CE-02D7, U+02DD-02FF, U+0304, U+0308, U+0329, U+1D00-1DBF, U+1E00-1E9F, U+1EF2-1EFF, U+2020, U+20A0-20AB, U+20AD-20C0, U+2113, U+2C60-2C7F, U+A720-A7FF'),
  ].join('\n') + '\n';
}

function buildTokensCss(fontDir) {
  const light = colorVars('light');
  const dark = colorVars('dark');
  const scalars = [
    scalarVars('font'),
    scalarVars('radius'),
    scalarVars('rule'),
    scalarVars('space'),
    scalarVars('shadow'),
    scalarVars('text'),
    scalarVars('leading'),
    scalarVars('tracking'),
  ].filter(Boolean).join('\n');

  return `${header}
${fontFace(fontDir)}
:root {
${light}
${scalars}
}

/* Follow the system when no explicit theme is chosen. */
@media (prefers-color-scheme: dark) {
  :root:not([data-theme="light"]) {
${dark}
  }
}

/* Explicit overrides always win (persisted per device via Capacitor Preferences). */
:root[data-theme="dark"] {
${dark}
}

:root[data-theme="light"] {
${light}
}
`;
}

const rgb = (hex) => hex.replace('#', '').match(/../g).map((h) => parseInt(h, 16)).join(', ');

function buildIonicCss() {
  // Ionic reads its own --ion-* variables; point them at our semantic --hm-* vars so the whole
  // Ionic surface inherits the theme switch for free. The -rgb pairs (Ionic mixes translucent
  // overlays from them) cannot be variables, so they are derived from the same tokens here.
  return `${header}
:root {
  --ion-background-color: var(${cssVar('color', 'surface.base')});
  --ion-background-color-rgb: ${rgb(tokens.color['surface.base'].dark)};
  --ion-text-color: var(${cssVar('color', 'text.primary')});
  --ion-text-color-rgb: ${rgb(tokens.color['text.primary'].dark)};
  --ion-toolbar-background: var(${cssVar('color', 'surface.base')});
  --ion-item-background: var(${cssVar('color', 'surface.raised')});
  --ion-tab-bar-background: var(${cssVar('color', 'surface.sunken')});
  --ion-placeholder-color: var(${cssVar('color', 'text.muted')});
  --ion-card-background: var(${cssVar('color', 'surface.raised')});
  --ion-border-color: var(${cssVar('color', 'border.subtle')});
  --ion-color-step-150: var(${cssVar('color', 'surface.sunken')});

  --ion-color-primary: var(${cssVar('color', 'brand.primary')});
  --ion-color-primary-contrast: var(${cssVar('color', 'brand.onPrimary')});

  --ion-color-success: var(${cssVar('color', 'status.success')});
  --ion-color-warning: var(${cssVar('color', 'status.warning')});
  --ion-color-danger: var(${cssVar('color', 'status.danger')});
  --ion-color-medium: var(${cssVar('color', 'text.muted')});
}
`;
}

/**
 * The Tailwind theme, as a v4 `@theme` block.
 *
 * This used to emit a v3-style JS preset (`module.exports = { theme: { extend: … } }`), which was
 * doubly dead: nothing imported it, and the project is on Tailwind v4, where configuration is
 * CSS-first and a JS preset is not read at all. So the one file whose whole job was carrying the
 * palette into Tailwind was the wrong shape for the Tailwind actually installed.
 *
 * Every value points at the `--hm-*` variable rather than copying its literal, so a utility like
 * `bg-surface-raised` follows the light/dark switch for free — the variables are what change with
 * the theme, and Tailwind never needs to know there are two palettes.
 */
function buildTailwindTheme() {
  const ref = (group, name) => `var(${cssVar(group, name)})`;
  const lines = [];

  const color = (name, group, token) => lines.push(`  --color-${name}: ${ref(group, token)};`);
  color('surface', 'color', 'surface.base');
  color('surface-raised', 'color', 'surface.raised');
  color('surface-sunken', 'color', 'surface.sunken');
  color('surface-step', 'color', 'surface.step');
  color('surface-rail', 'color', 'surface.rail');
  color('surface-inverse', 'color', 'surface.inverse');
  color('content', 'color', 'text.primary');
  color('content-secondary', 'color', 'text.secondary');
  color('content-tertiary', 'color', 'text.tertiary');
  color('content-muted', 'color', 'text.muted');
  color('content-faint', 'color', 'text.faint');
  color('content-inverse', 'color', 'text.onInverse');
  color('edge-soft', 'color', 'border.soft');
  color('edge', 'color', 'border.subtle');
  color('edge-strong', 'color', 'border.strong');
  color('edge-inverse', 'color', 'border.onInverse');
  color('brand', 'color', 'brand.primary');
  color('brand-strong', 'color', 'brand.strong');
  color('brand-tint', 'color', 'brand.tint');
  color('brand-track', 'color', 'brand.track');
  color('brand-contrast', 'color', 'brand.onPrimary');
  color('brand-on-inverse', 'color', 'brand.onInverse');
  color('success', 'color', 'status.success');
  color('warning', 'color', 'status.warning');
  color('danger', 'color', 'status.danger');
  color('info', 'color', 'status.info');
  color('success-tint', 'color', 'status.successTint');
  color('warning-tint', 'color', 'status.warningTint');
  color('danger-tint', 'color', 'status.dangerTint');
  color('info-tint', 'color', 'status.infoTint');

  // The typeface. Every surface's `font-sans` is the token: the site through this block, the app
  // through Ionic's --ion-font-family, which theme/variables.scss points at --font-sans.
  lines.push('');
  lines.push(`  --font-sans: ${ref('font', 'sans')};`);

  lines.push('');
  for (const k of Object.keys(tokens.radius ?? {})) lines.push(`  --radius-${k}: ${ref('radius', k)};`);
  lines.push('');
  for (const k of Object.keys(tokens.shadow ?? {})) lines.push(`  --shadow-${k}: ${ref('shadow', k)};`);
  lines.push('');
  for (const k of Object.keys(tokens.text ?? {})) lines.push(`  --text-${k}: ${ref('text', k)};`);
  lines.push('');
  for (const k of Object.keys(tokens.leading ?? {})) lines.push(`  --leading-${k}: ${ref('leading', k)};`);
  lines.push('');
  for (const k of Object.keys(tokens.tracking ?? {})) lines.push(`  --tracking-${k}: ${ref('tracking', k)};`);

  return `${header}
/* Tailwind v4 reads its theme from CSS. Import this after the tailwindcss import. */
@theme {
${lines.join('\n')}
}
`;
}

/**
 * The PWA install manifest (P5-01).
 *
 * It is generated here for the same reason every other surface is: a manifest cannot reference a
 * CSS variable, so its two colours would otherwise be the one place in the product where the brand
 * palette is copied by hand — and the splash screen would silently drift from the app. `theme_color`
 * is the brand green and `background_color` the page ground, because the install splash shows
 * before any code runs and therefore before a theme preference can be read.
 */
function buildWebManifest(existing) {
  const icons = existing?.icons ?? [];
  return `${JSON.stringify({
    name: 'HandyMan',
    short_name: 'HandyMan',
    description: 'Find trusted help near you — on-site or remote.',
    display: 'standalone',
    orientation: 'portrait',
    scope: './',
    start_url: './',
    theme_color: tokens.color['brand.primary'].light,
    background_color: tokens.color['surface.base'].light,
    categories: ['business', 'productivity'],
    icons,
  }, null, 2)}\n`;
}

/**
 * The Filament palette, as PHP.
 *
 * Filament builds its colour ramps in PHP at boot, from literal hex — it cannot reference a CSS
 * variable the way Blade, Tailwind and Ionic all can. So the admin panel was the one surface in the
 * product where the brand palette had been copied by hand into a provider, which is exactly the
 * drift this generator exists to prevent: a change here reached three surfaces and quietly missed
 * the fourth. The panel is dark like every other surface (Filament's dark mode is forced on).
 */
function buildFilamentPhp() {
  const pick = (name) => tokens.color[name].light;
  const palette = {
    primary: pick('brand.primary'),
    info: pick('status.info'),
    success: pick('status.success'),
    warning: pick('status.warning'),
    danger: pick('status.danger'),
  };
  const lines = Object.entries(palette)
    .map(([k, v]) => `        '${k}' => '${v}',`)
    .join('\n');

  return `<?php

// GENERATED from tokens/tokens.json — do not edit by hand. Run \`npm run tokens:build\`.

declare(strict_types=1);

return [
    'colors' => [
${lines}
    ],
];
`;
}

function write(path, content) {
  mkdirSync(dirname(path), { recursive: true });
  writeFileSync(path, content, 'utf8');
  console.log('  wrote ' + path.slice(root.length + 1));
}

const tokensCss = buildTokensCss();
const ionicCss = buildIonicCss();
const tailwindTheme = buildTailwindTheme();

// Shared generated copies (source of truth for the build artifacts).
write(join(__dirname, 'generated', 'tokens.css'), tokensCss);
write(join(__dirname, 'generated', 'ionic-tokens.css'), ionicCss);
write(join(__dirname, 'generated', 'tailwind-theme.css'), tailwindTheme);

// Backend (Blade + Filament share Tailwind + the CSS vars).
// Absolute, because the Vite copy and the directly-linked copy both resolve against public/.
write(join(root, 'backend', 'resources', 'css', 'tokens.css'), buildTokensCss('/fonts'));
write(join(root, 'backend', 'resources', 'css', 'tailwind-theme.css'), tailwindTheme);
// Also emit a directly-linkable copy so Blade can <link> it without a Vite build.
write(join(root, 'backend', 'public', 'css', 'tokens.css'), buildTokensCss('/fonts'));
// Filament resolves its ramps in PHP and cannot read a CSS variable — see buildFilamentPhp.
write(join(root, 'backend', 'config', 'tokens.php'), buildFilamentPhp());

// Mobile (Ionic app) — only if scaffolded.
if (existsSync(join(root, 'mobile'))) {
  // Root-relative: Angular's CSS pipeline resolves a relative url() against the importing file (src/
  // global.scss, not this one) and fails the build; a root-relative path it leaves alone, and the
  // app is served from a root on every platform (ng serve, the PWA, Capacitor's https://localhost).
  write(join(root, 'mobile', 'src', 'theme', 'tokens.css'), buildTokensCss('/assets/fonts'));
  write(join(root, 'mobile', 'src', 'theme', 'ionic-tokens.css'), ionicCss);
  // The app writes its layout in the same utility vocabulary as the marketing site, so it needs the
  // same @theme mapping. One generated file, two surfaces: `bg-surface-raised` cannot come to mean
  // different things in the app and on the web.
  write(join(root, 'mobile', 'src', 'theme', 'tailwind-theme.css'), tailwindTheme);

  // The install manifest keeps its generated icon list; only the identity and colours come from here.
  const manifestPath = join(root, 'mobile', 'public', 'manifest.webmanifest');
  if (existsSync(manifestPath)) {
    write(manifestPath, buildWebManifest(JSON.parse(readFileSync(manifestPath, 'utf8'))));
  }

  // index.html's `theme-color` metas are the one place the palette CANNOT be a variable — a meta
  // tag takes a literal, and the browser reads it before any stylesheet. They are therefore the one
  // place brand colour can silently drift, and the no-literal-colour lint does not reach
  // `src/index.html`. So assert them here instead of trusting a comment.
  const indexPath = join(root, 'mobile', 'src', 'index.html');
  if (existsSync(indexPath)) {
    const html = readFileSync(indexPath, 'utf8');
    for (const [scheme, expected] of [['light', tokens.color['brand.primary'].light], ['dark', tokens.color['brand.primary'].dark]]) {
      const found = new RegExp(`prefers-color-scheme:\\s*${scheme}\\)"\\s*content="([^"]+)"`).exec(html)?.[1];
      if (found !== expected) {
        console.error(`index.html theme-color (${scheme}) is ${found ?? 'missing'}, expected the brand token ${expected}`);
        process.exitCode = 1;
      }
    }
  }
}

console.log('tokens build complete.');
