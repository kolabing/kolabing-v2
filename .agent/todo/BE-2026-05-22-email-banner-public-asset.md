# BE-2026-05-22 · Email Banner Public Asset

**From**: `G1` in the launch list

The welcome email launch still needs the baked PNG banner at a stable public URL so Gmail iOS dark mode stops inverting the SVG treatment.

---

## Required file

- source asset: `community/kolabing/marketing/brand/logo-wordmark-banner-dark.png`
- dimensions: `1200x320`

Target public path:

- `https://kolabing.com/brand/logo-wordmark-banner-dark.png`

Equivalent stable public path is acceptable if final URL is shared back immediately.

---

## Requirements

- file is publicly reachable without auth
- `Content-Type: image/png`
- cacheable as a stable asset
- final URL shared back so the Postmark template can swap its `<img src>`

---

## Acceptance

- banner exists at a stable public URL
- opening the URL returns the PNG directly
- Daniel can replace both welcome-email headers with that URL without another mobile release

---

## Note

This is not a Flutter code task. Mobile is already ready to live with the branded logo state; the blocker is the public asset path.
