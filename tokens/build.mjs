#!/usr/bin/env node
// Design-token build (build plan P0-11/13/14, doc 08).
//
// ONE source (tokens/tokens.json, semantic names) → every surface:
//   - tokens.css            semantic CSS custom properties (--hm-*), light + dark
//   - ionic-tokens.css      maps Ionic's --ion-* variables onto our --hm-* vars
//   - tailwind-tokens.cjs   a Tailwind theme preset referencing the same CSS vars
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

function buildTokensCss() {
  const light = colorVars('light');
  const dark = colorVars('dark');
  const scalars = [scalarVars('radius'), scalarVars('space')].filter(Boolean).join('\n');

  return `${header}
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

function buildIonicCss() {
  // Ionic reads its own --ion-* variables; point them at our semantic --hm-* vars so the whole
  // Ionic surface inherits the theme switch for free.
  return `${header}
:root {
  --ion-background-color: var(${cssVar('color', 'surface.base')});
  --ion-background-color-rgb: 255, 255, 255;
  --ion-text-color: var(${cssVar('color', 'text.primary')});
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

function buildTailwindPreset() {
  const ref = (group, name) => `var(${cssVar(group, name)})`;
  const preset = {
    theme: {
      extend: {
        colors: {
          surface: {
            DEFAULT: ref('color', 'surface.base'),
            raised: ref('color', 'surface.raised'),
            sunken: ref('color', 'surface.sunken'),
          },
          content: {
            DEFAULT: ref('color', 'text.primary'),
            muted: ref('color', 'text.muted'),
            inverse: ref('color', 'text.inverse'),
          },
          border: {
            subtle: ref('color', 'border.subtle'),
            strong: ref('color', 'border.strong'),
          },
          brand: {
            DEFAULT: ref('color', 'brand.primary'),
            contrast: ref('color', 'brand.onPrimary'),
          },
          success: ref('color', 'status.success'),
          warning: ref('color', 'status.warning'),
          danger: ref('color', 'status.danger'),
          info: ref('color', 'status.info'),
        },
        borderRadius: Object.fromEntries(
          Object.keys(tokens.radius ?? {}).map((k) => [k, `var(${cssVar('radius', k)})`]),
        ),
        spacing: Object.fromEntries(
          Object.keys(tokens.space ?? {}).map((k) => [k, `var(${cssVar('space', k)})`]),
        ),
      },
    },
  };

  return `// GENERATED from tokens/tokens.json — do not edit by hand. Run \`npm run tokens:build\`.\nmodule.exports = ${JSON.stringify(preset, null, 2)};\n`;
}

/**
 * The PWA install manifest (P5-01).
 *
 * It is generated here for the same reason every other surface is: a manifest cannot reference a
 * CSS variable, so its two colours would otherwise be the one place in the product where the brand
 * palette is copied by hand — and the splash screen would silently drift from the app. `theme_color`
 * is the brand green and `background_color` is the light surface, because the install splash shows
 * before any code runs and therefore before a theme preference can be read.
 */
function buildWebManifest(existing) {
  const icons = existing?.icons ?? [];
  return `${JSON.stringify({
    name: 'HandyMan',
    short_name: 'HandyMan',
    description: 'Find trusted help in Cameroon — on-site or remote.',
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

function write(path, content) {
  mkdirSync(dirname(path), { recursive: true });
  writeFileSync(path, content, 'utf8');
  console.log('  wrote ' + path.slice(root.length + 1));
}

const tokensCss = buildTokensCss();
const ionicCss = buildIonicCss();
const tailwindPreset = buildTailwindPreset();

// Shared generated copies (source of truth for the build artifacts).
write(join(__dirname, 'generated', 'tokens.css'), tokensCss);
write(join(__dirname, 'generated', 'ionic-tokens.css'), ionicCss);
write(join(__dirname, 'generated', 'tailwind-tokens.cjs'), tailwindPreset);

// Backend (Blade + Filament share Tailwind + the CSS vars).
write(join(root, 'backend', 'resources', 'css', 'tokens.css'), tokensCss);
write(join(root, 'backend', 'tailwind-tokens.cjs'), tailwindPreset);
// Also emit a directly-linkable copy so Blade can <link> it without a Vite build.
write(join(root, 'backend', 'public', 'css', 'tokens.css'), tokensCss);

// Mobile (Ionic app) — only if scaffolded.
if (existsSync(join(root, 'mobile'))) {
  write(join(root, 'mobile', 'src', 'theme', 'tokens.css'), tokensCss);
  write(join(root, 'mobile', 'src', 'theme', 'ionic-tokens.css'), ionicCss);

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
