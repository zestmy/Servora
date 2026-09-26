# Brand & corporate identity

Everything visual about Servora — the mark, the lockups, the colours — and
where each one is allowed to be used.

The logo artwork is **supplied by the designer, not generated**. The files
under `public/images/` are the masters: do not redraw them, recolour them, or
rebuild a lockup by setting the wordmark yourself. When the identity changes,
new files come in and replace these.

The only generated brand files are the two formats that cannot be shipped as a
plain PNG, both derived from the supplied app icon:

```bash
php scripts/make-favicons.php     # favicon.ico + the maskable PWA icon
```

Re-run that after replacing `public/images/servora-icon.png`.

---

## Colours

| Role | Token | Hex | Used for |
|------|-------|-----|----------|
| Primary Blue | `brand-600` | `#0962EF` | buttons, links, active nav, the accent everywhere |
| Deep Navy | `navy-900` | `#0B1F3B` | dark ground the logo reverses out of, PWA theme colour |

Primary Blue is the blue of the logo artwork. The rest of the `brand` scale is
built around it — hue held at 217, lightness stepped — so every step is the
same blue. Both scales live in [`tailwind.config.js`](../tailwind.config.js),
which is the only place a brand hex should be written.

Contrast, measured:

| Pair | Ratio | Verdict |
|------|-------|---------|
| white on `brand-600` | 5.24:1 | ✓ body-size filled buttons |
| white on `brand-700` | 6.91:1 | ✓ hover / active fill |
| white on `brand-500` | 4.33:1 | ✗ short of AA — large text only |
| `brand-400` on `gray-900` | 5.33:1 | ✓ dark sidebar text |
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

## The files

| File | What it is |
|------|------------|
| `images/servora-logo-black.png` | Blue gradient mark, dark wordmark. **Light backgrounds.** |
| `images/servora-logo-white.png` | Blue mark, white wordmark. **Dark backgrounds** — it is invisible on white, which is correct, not a broken file. |
| `images/servora-logo-blue.png` | All-blue lockup, for light backgrounds where the dark version reads too heavy. |
| `images/servora-icon.png` | App icon — blue mark in a white disc on the blue plate, SERVORA wordmark beneath. |
| `favicon.png` | The app icon again, at the path browsers and the manifests point to. |
| `favicon.ico` | Derived. Packs 16/32/48. |
| `servora-maskable-512.png` | Derived. Android maskable: full-bleed plate, glyph inside the safe zone. |
| `clock-app/staff-portal.png` | Staff Portal app icon (STAFF). |
| `clock-app/kiosk.png` | Clock-in kiosk app icon (KIOSK). |
| `labels-app/label-icon.png` | Labels app icon (LABEL). Source for the sizes `scripts/make-label-app-icons.php` derives. |

All three lockups are 300 × 60; the icon is 300 × 300. Layouts size them by
height (`h-8`, `h-11`) with width auto, so the aspect carries itself.

`servora-logo-black.png` and `-white.png` keep these names because every layout
in the app already points at them. The names describe the **wordmark's**
colour, not the file's purpose: "black" means *for light backgrounds*.

### Which one to reach for

- Surface is white or near-white → `servora-logo-black.png`.
- The dark sidebar, the marketing footer, a navy card → `servora-logo-white.png`.
- Somewhere the dark lockup reads too heavy on light → `servora-logo-blue.png`.
- Too small for a lockup, or beside text that already says Servora →
  `servora-icon.png`.

### Clear space and minimum size

Keep clear space of at least the mark's own width on every side. The
horizontal lockup carries a tagline at a fifth of the wordmark's size, so it
stops being legible below about 24px of height — under that use the icon, never
a shrunken lockup.

Don't: recolour the artwork, stretch it (the lockups are 5:1), add a shadow or
outline, or place the dark lockup on a dark ground.

---

## Typography

Interface type is **Figtree**, loaded from fonts.bunny.net in each layout. The
wordmark in the lockups is artwork, not live text, so no webfont is involved in
rendering the logo and it looks identical everywhere.

The tagline set in the artwork is **AI-Powered Restaurant Operations**. The
marketing copy matches it; if the artwork's tagline changes, the copy in
`layouts/marketing.blade.php`, the marketing home eyebrow and `manifest.json`
need to move with it.

---

## Company logos are a different thing

Customer logos uploaded per company are handled by
[`CompanyLogo`](../app/Services/Branding/CompanyLogo.php) and the
[`brand-mark`](../resources/views/components/brand-mark.blade.php) component,
which measures the artwork and decides whether it needs a contrasting chip.
None of the rules above apply to those — we do not control that artwork.
