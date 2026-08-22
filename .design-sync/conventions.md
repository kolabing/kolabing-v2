# Kolabing — how to build with this design system

**This is a foundations-only system: colour, type, shape, depth, and a small set
of CSS idioms. There are no importable components** — Kolabing's UI is
server-rendered Blade, so there is no compiled component library to ship. Build
your own components out of the tokens and classes below; do not invent a parallel
visual language beside them.

## Setup

Pure CSS — no provider, no theme object, no JS. Import the stylesheet once and
everything (self-hosted fonts included) is reachable from its `@import` closure:

```html
<link rel="stylesheet" href="_ds/kolabing/styles.css" />
```

Two rules that break the look if missed:

- **Never add a Google Fonts `<link>`.** Anton, Inter, Caveat, Playfair Display,
  DM Sans and Montserrat are already self-hosted as woff2 in `tokens/fonts.css`.
  An external font request is blocked and silently falls back to system sans.
- **Never add a dark mode.** Kolabing is a light-ground brand that uses
  full-bleed near-black bands for emphasis. Wrap a section in
  `.kb-section--dark` instead of inverting the page.

## The styling idiom

Plain semantic classes plus CSS custom properties. No utility framework, no
props, no theme strings. Two vocabularies, both real:

**Classes** — `kb-` prefix, BEM-ish `--modifier` suffixes:

| Family | Classes |
|---|---|
| Layout | `kb-container` (`--wide`), `kb-section` (`--tint`, `--dark`) |
| Headlines | `kb-display` (`--hero`), `kb-heading` (`--sm`), `kb-eyebrow` |
| Copy | `kb-lead`, `kb-body-muted`, `kb-meta`, `kb-script`, `kb-editorial`, `kb-mark` |
| Actions | `kb-btn` (`--primary`, `--accent`, `--ghost`, `--lg`) |
| Indicators | `kb-badge` (`--hot`, `--accent`), `kb-chip` |
| Surfaces | `kb-card` (`--raised`), `kb-rule`, `kb-divider` |
| Chrome | `kb-header` (`--plain`), `kb-nav`, `kb-footer`, `kb-wordmark`, `kb-logo-tilt` |
| App surface | `kb-product` |

**Tokens** — `--kb-` prefix. Style your own components against the semantic ones
(`--kb-ink`, `--kb-ink-muted`, `--kb-ink-subtle`, `--kb-bg`, `--kb-bg-tint`,
`--kb-bg-inverse`, `--kb-surface`, `--kb-accent`, `--kb-accent-ink`,
`--kb-accent-hot`, `--kb-border`, `--kb-cta-bg`, `--kb-cta-ink`) rather than the
raw ramp (`--kb-dark`, `--kb-yellow`, `--kb-orange`, `--kb-light`, `--kb-mid`).
Type: `--kb-font-display|body|accent|editorial|product|product-display`,
`--kb-text-hero|display-1|display-2|heading-1|heading-2|heading-3|lead|body|body-sm|ui|meta|micro`.
Shape and depth: `--kb-radius-pill|lg|md|sm|hairline`,
`--kb-shadow-sm|md|lg|on-dark|accent`, `--kb-space-1…9`.

## Where the truth lives

Read these before styling — they are short and they are authoritative:

- `_ds/kolabing/styles.css` and its imports (`tokens/palette.css`,
  `tokens/typography.css`, `tokens/layout.css`, `tokens/fonts.css`).
- `_ds/kolabing/guidelines/` — `brand.md` (voice), `color.md` (incl. contrast
  limits), `typography.md` (the five faces), `components.md` (every idiom).

## The one thing to get right

The primary CTA is **dark ground with yellow text** — inverted from the usual
convention. On a dark section the relationship flips and yellow becomes the fill.
Yellow is an accent, never a page or panel background.

```html
<section class="kb-section kb-section--dark">
  <div class="kb-container">
    <span class="kb-badge kb-badge--hot">Live in Barcelona</span>
    <h1 class="kb-display kb-display--hero">Fill your quiet nights</h1>
    <div class="kb-rule"></div>
    <p class="kb-lead">Post a Kolab and we surface communities that match your
      city, audience and goal.</p>
    <div style="display:flex; gap:var(--kb-space-3); margin-top:var(--kb-space-6)">
      <a class="kb-btn kb-btn--accent kb-btn--lg" href="#">Get started free</a>
      <a class="kb-btn kb-btn--ghost kb-btn--lg" href="#">See how it works</a>
    </div>
  </div>
</section>
```

Product nouns matter: a **Kolab** is a collaboration opportunity, **businesses**
post and pay, **communities** apply for free.
