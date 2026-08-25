# design-sync notes — kolabing-v2

Findings from the sync runs. Read before the next `/design-sync`; add to it
whenever something is learned about this repo.

## 2026-08-17 — first sync (foundations only)

### Why the standard converter does not run here

`kolabing-v2` is a Laravel 12 backend API. Its UI is **76 server-rendered Blade
templates**, and `resources/views/components/` holds exactly one file (a layout
wrapper). There is no JS/TS component library, no `dist/`, no Storybook, and no
React or Vue anywhere. `package.json` carries only Vite + Tailwind + axios.

Claude Design renders compiled React from `_ds_bundle.js`, so there is nothing in
this repo to bundle — and reimplementing Blade markup as React would violate the
skill's core rule (ship what the customer built, never a reimplementation).

Neither documented shape applies: `shape: "package"` needs a bundlable component
entry, `shape: "storybook"` needs a `.storybook/` config. The config records
`shape: "foundations"` for this hand-authored layout.

**Consequence:** the synced project ships tokens, self-hosted fonts, guidelines
and three rendered preview sheets — no `_ds_bundle.js`, no `components/`. The
design agent gets the Kolabing *look* and builds its own components against it.

`_ds_sync.json` is deliberately omitted. The anchor's recipe is built from
component render hashes; with no components there is nothing to anchor, so every
re-sync re-derives from source. That is the honest state, not a bug.

### Palette drift between surfaces (worth fixing in the product)

The product ships **two cuts of the same palette** and they are not aliases:

| Surface | Yellow | Ink | Paper |
|---|---|---|---|
| `welcome.blade.php` (homepage) | `#FFE28C` | `#0D1114` | `#ffffff` |
| `marketing-page.blade.php` + `webapp/layout.blade.php` | `#FFD560` | `#1B1F1C` | `#FDFBF7` |

`<meta name="theme-color">` adds a third dark, `#0D1216`. The sync treats the
homepage values as canonical and exposes the product cut as
`--kb-primary` / `--kb-off-black` / `--kb-off-white`. Converging these in the
product would remove a real inconsistency.

### `--purple` is orange

`welcome.blade.php` defines `--purple: #ff6114`. It is an orange and always has
been. The bundle renames it `--kb-orange`. Expect the old name when reading repo
CSS.

### No reusable class vocabulary exists in the source

The homepage's ~60 CSS classes are all page-specific (`.hero-badge`, `.matcher`,
`.phone-frame`, `.manifesto-bubble`, …). Nothing is shared between the homepage,
the marketing pages, and the web app. The `kb-*` layer in `styles.css` is new —
it generalises the patterns those classes repeat, and every value in it is lifted
from the source. This is the one place the bundle is not a 1:1 copy.

### Both Blade surfaces load Tailwind from the CDN

`marketing-page.blade.php` and `webapp/layout.blade.php` both pull
`cdn.tailwindcss.com` with an inline `tailwind.config`. The repo's actual Vite +
Tailwind v4 pipeline (`resources/css/app.css`) is therefore not what the pages
use, and its `@theme` block holds only `--font-sans: Instrument Sans` — a face no
shipping page requests. Not a sync problem, but it means the repo's *build* is
not the source of truth for the look; the inline configs are.

### Fonts are self-hosted in the bundle

Six families / 15 latin-subset woff2 faces (Anton, Inter, Caveat, Playfair
Display, DM Sans, Montserrat), fetched once from the Google Fonts `css2` endpoint
that the Blade layouts already request and rewritten to relative URLs in
`tokens/fonts.css`. External font requests are blocked in rendered designs, so
self-hosting is required, not an optimisation.

### Verification method

The Chrome extension was not connected (`tabs_context_mcp` returned "Browser
extension is not connected"), so previews were verified by rendering the bundle
over a local HTTP server with headless Chrome and reading the screenshots. All
three sheets rendered correctly with every face loading. If the extension is
available on a future run, prefer it.

### Project target

Reused the pre-existing, **empty** `Kolabing Design System` project
(`97e56a75-16a3-4edf-98fb-9570e659eab4`) rather than creating a duplicate — it
carried the exact name this skill proposes and contained no files, so there was
nothing to overwrite. Now pinned in `config.json`.
