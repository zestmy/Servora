# Brand & corporate identity

Everything visual about Servora — the mark, the lockups, the two colours, the
typography — and where each one is allowed to be used.

The artwork is **generated, not hand-drawn**. One script owns every raster and
vector file:

```bash
php scripts/make-brand-assets.php
```

Edit a constant at the top of that script and re-run it, and the favicon, the
PWA icons, the marketing lockup and the reversed sidebar logo all move
together. Never retouch a PNG under `public/images/` by hand — the next run
overwrites it, and the change is lost with no record of what it was.

---

## The mark

An angular **S** cut from a single folded ribbon: a top bar, a diagonal, a
bottom bar. Three constants drive it (`T` bar thickness, `L` the top bar's
inset, `D` the diagonal's drift), bound by `2L + T + D = 1`. That identity is
what gives the mark 180° rotational symmetry, which is what keeps it balanced
at 16px. If you change one of the three, change another to keep the sum at 1.

The full-colour mark carries a top-left-to-bottom-right gradient (brand-500 →
brand-700) and a fold: the diagonal face darkened by 11%. The fold is
deliberately faint. Past roughly 20% the diagonal stops reading as a face of
the ribbon and becomes a stripe across the letter, and the S splits into three
shapes.

---

## Colours

| Role | Token | Hex | Used for |
|------|-------|-----|----------|
| Primary Blue | `brand-600` | `#2563EB` | buttons, links, active nav, the accent everywhere |
| Deep Navy | `navy-900` | `#0B1F3B` | the wordmark, dark ground the logo reverses out of, PWA theme colour |

Both scales live in [`tailwind.config.js`](../tailwind.config.js), which is the
only place a brand hex should be written. Contrast, measured:

| Pair | Ratio | Verdict |
|------|-------|---------|
| white on `brand-600` | 5.17:1 | ✓ body-size filled buttons |
| white on `brand-700` | 6.70:1 | ✓ hover / active fill |
| white on `brand-500` | 3.68:1 | ✗ large text only |
| `brand-400` on `gray-900` | 6.98:1 | ✓ dark sidebar text |
| white on `navy-900` | 16.49:1 | ✓ anything |

Two things deliberately did **not** turn blue when the brand did:

- **Chart series** (`chart-1` teal, `chart-2` violet). A series painted in the
  accent reads as chrome rather than as data, and blue against violet is
  precisely the pair deuteranopes cannot separate.
- **Categorical palettes** that already contain a blue — the supplier
  breakdown in [`PurchaseSupplierBreakdown`](../app/Services/PurchaseSupplierBreakdown.php)
  and the wastage categories in the reports hub. Their colours answer "which
  series", not "whose product".

Dark chrome is still `gray-900` in most of the app. `navy-900` is the
destination, not a finished migration: use it for new dark surfaces, and
migrate a surface when you are already editing that file.

---

## Typography

- **Interface** — Figtree, loaded from fonts.bunny.net in each layout.
- **Logo lockups** — Outfit Bold for the wordmark, Outfit Regular for the
  tagline, vendored at [`resources/fonts/outfit/`](../resources/fonts/outfit)
  under the SIL Open Font License (the licence ships beside the fonts). It is
  used *only* by the asset generator, never served to a browser — the wordmark
  is artwork, not text, so the lockup is identical on every machine.

The tagline is **AI-Powered F&B Operations**. It is set in the artwork; if it
changes, change `TAGLINE` in the generator and re-run.

---

## The files

| File | What it is |
|------|------------|
| `images/servora-logo-black.png` | Primary lockup — colour mark, navy wordmark. Light backgrounds. |
| `images/servora-logo-white.png` | Reversed lockup — colour mark, white wordmark. Dark backgrounds. |
| `images/servora-logo-mono.png` | One-colour navy. Single-plate print, etching, fax. |
| `images/servora-logo-mono-white.png` | One-colour white. Photography, dark print. |
| `images/servora-logo-stacked.png` | Mark above the type, for square-ish spaces. |
| `images/servora-logo-stacked-white.png` | The same, reversed. |
| `images/servora-mark.png` / `.svg` | The mark alone, no plate. Where the name is already on screen. |
| `images/servora-mark-white.png` | The mark alone, flat white. |
| `images/servora-icon.png` / `.svg` | App icon — white mark on the blue squircle. |
| `images/icons/servora-icon-{16…1024}.png` | The icon at every delivered size. |
| `favicon.png`, `favicon.ico` | Browser chrome. The `.ico` packs 16/32/48. |
| `servora-maskable-512.png` | Android maskable: full-bleed, glyph inside the safe zone. |

`servora-logo-black.png` and `-white.png` keep their names from the previous
identity because every layout in the app already points at them, and the names
still describe the wordmark's colour. "Black" means *for light backgrounds*.

### Which one to reach for

- Anywhere the surface is white or near-white → `servora-logo-black.png`.
- The dark sidebar, the marketing footer, a navy card → `servora-logo-white.png`.
- Under ~24px of height, or beside text that already says Servora → the mark
  alone, or `servora-icon.svg`.
- Prefer the `.svg` where an `<img>` can be vector. It is about a kilobyte and
  stays sharp at any size; both SVGs are generated from the same constants as
  the PNGs, so they cannot drift.

### Clear space and minimum size

Keep clear space equal to the mark's bar thickness (about a quarter of the
mark's height) on every side. The horizontal lockup stops being legible below
24px of height — below that, use the mark or the icon, never a shrunken
lockup.

Don't: recolour the mark outside the two brand colours, stretch it (the aspect
is 1.15:1), add a shadow or outline, put the full-colour mark on a mid-blue
background, or rebuild the lockup by setting the wordmark yourself — the
spacing is computed, not eyeballed.

---

## Company logos are a different thing

Customer logos uploaded per company are handled by
[`CompanyLogo`](../app/Services/Branding/CompanyLogo.php) and the
[`brand-mark`](../resources/views/components/brand-mark.blade.php) component,
which measures the artwork and decides whether it needs a contrasting chip.
None of the rules above apply to those — we do not control that artwork.
