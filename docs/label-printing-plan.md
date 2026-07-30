# Label Printing Module — Plan

> Status: **phase 1 in progress**. Schema, models and services are built and tested;
> no UI yet. This doc captures the decisions taken during scoping so the build doesn't
> relitigate them. Drafted 2026-07-30, last updated 2026-07-30.

HACCP / food-safety labelling for raw materials, prep items and finished products.
Chef stands at a laptop in the outlet, taps an item or a print set, and labels come
off an attached label printer.

---

## 1. Decisions taken

| # | Area | Decision | Why |
|---|------|----------|-----|
| 1 | Transport | **Browser kiosk printing** — Chrome `--kiosk-printing`, no PrintNode | Always-on PC per outlet with the printer attached; PrintNode solves remote/unattended printing, which isn't the problem here. No subscription. Tablets + Bluetooth were evaluated and rejected — see below. |
| 2 | Driver design | Two-method driver interface; `browser` shipped, `printnode` stubbed | Keeps transport out of the architecture. PrintNode columns stay in the schema, nullable and unused. |
| 3 | Rendering | Single Blade layout → HTML for browser print, same Blade → dompdf for archive/preview | One source of truth, two output paths. See §5. |
| 4 | PrintNode accounts | Master child-account by default, nullable BYO API key | Deferred — not built in v1, but schema allows it. |
| 5 | Shelf life | Storage-state matrix, **defaults at category level**, per-item override | One row per ingredient category covers hundreds of items. Per-item only where it genuinely differs. |
| 6 | Staff attribution | Pick your name from the outlet's employee list, **no PIN** | Honour-system attribution is enough for HACCP audit; PIN management isn't worth the build. |
| 7 | Use-by rounding | **End of day 23:59**, company-configurable | Standard in most HACCP schemes, easiest to read. |
| 8 | v1 entry point | Dedicated Kitchen-mode label screen only | Production Order / GRN entry points deferred. |
| 9 | v1 scope | Stock templates **plus** a template designer | Forces layout to be structured JSON rather than hard-coded Blade. |
| 10 | Print sets | **Outlet-owned only** — chefs create their own | No company-wide sets, no cross-outlet sync. Simplest model. |
| 11 | Set print flow | **Always show a review screen** | Blind-printing a set burns labels on items nobody prepped today. |
| 12 | Roster link | **No link** to `RosterStation` | Keeps the label module independent of the roster module. |

### Rejected: tablets + Bluetooth printers

Considered and rejected 2026-07-30. The picture was kitchen staff carrying an Android
tablet or iPad and printing to a Bluetooth label printer. **A web app cannot do this.**

- **Web Bluetooth has never shipped in Safari**, and every iOS browser is WebKit
  underneath, so Chrome on iPad doesn't help. iPad is a hard blocker.
- **Chrome on Android supports Web Bluetooth, but BLE/GATT only** — it cannot speak
  Bluetooth Classic SPP, which many thermal label printers use. Where a printer does
  expose BLE, you're pushing raw ESC/POS or ZPL through a GATT characteristic, which
  throws away the PDF pipeline and makes every printer model a bespoke integration.
- **Bluetooth Classic holds one connection at a time.** Three chefs, three tablets, one
  printer means constant pairing fights. Wi-Fi printers queue jobs instead. This would
  be a support burden even if the browser could do it.

Alternatives that *would* work with tablets, if this is ever revisited:

| Option | Trade-off |
|---|---|
| Tablet UI + Wi-Fi printer + per-outlet bridge running PrintNode | Tablets stay pure browser, silent printing, real print confirmation. Needs a bridge host and a subscription. |
| Tablet UI + AirPrint / Mopria, OS print dialog | No hardware, no subscription. Print dialog every time, unreliable page sizing. |
| Native wrapper app (Capacitor / React Native) + vendor Bluetooth SDK | The experience originally pictured. Two app stores, review cycles, signing, per-vendor SDKs — a product surface of its own. |

**Migration path if chefs later demand tablets:** every outlet already has an always-on
POS or back-office PC, so the bridge host exists. Switching to the first option is a
PrintNode client install on a machine that's already running, plus flipping
`label_printers.driver` to `printnode` — no new hardware, and nothing above the
transport layer moves. This is the payoff for decision 2.

Note that tablets can still *browse* Servora — the expiring-today dashboard, print set
management, the audit log. They just can't be the device that prints.

### Explicit non-goals for v1

Allergens, nutrition, QR traceability pages, barcodes on labels, Production Order and
GRN entry points, ZPL rendering, PrintNode, label-stock/consumable tracking, per-label
usage metering. The schema leaves room for all of them; none are built.

---

## 2. Existing ground

| Thing | Where | Note |
|---|---|---|
| Market List | `ingredients` (`is_prep = false`) | **No** shelf-life, storage, allergen or barcode columns. |
| Prep Items | `Recipe` (`is_prep = true`), mirrored to an `Ingredient` | Has `shelf_life_value`, `shelf_life_unit`, `storage_instruction`. |
| Production Recipes | `production_recipes` | Same three fields; linked to ingredients since 2026-07-29. |
| PDF | `barryvdh/laravel-dompdf` | Already a dependency. |
| QR | `chillerlan/php-qrcode` | Already a dependency. Not used in v1. |
| Kitchen mode | `resources/views/layouts/kitchen.blade.php` | The label screen lives here. |
| Naming clash | `OutletGroup` exists | Print sets are **`LabelSet`**, never "group", in code. |
| Naming clash | `RosterStation` exists (outlet-scoped stations) | Deliberately not reused — see decision 12. |

---

## 3. Data model

All tables company-scoped via `App\Scopes\CompanyScope` except where noted, following
[08-feature-playbook §1](08-feature-playbook.md#1-add-a-new-tenant-scoped-entity).

### `label_settings`
One row per company.

| Column | Notes |
|---|---|
| `company_id` | unique |
| `use_by_rounding` | enum `eod` \| `exact`, default `eod` |
| `default_template_id` | nullable |
| `footer_text` | nullable, printed on every label if the template has the token |
| `printnode_api_key` | nullable, **encrypted cast**. Unused in v1. |

> No precedent for stored third-party credentials exists in the repo yet. When this
> is finally used, it must be `encrypted` cast, never plain.

### `label_printers`
One row per physical printer. Outlet-scoped.

| Column | Notes |
|---|---|
| `company_id`, `outlet_id` | |
| `name` | "Chiller station Brother QL-820" |
| `driver` | enum `browser` \| `printnode`, default `browser` |
| `printnode_printer_id` | nullable. Unused in v1. |
| `default_template_id` | nullable |
| `width_mm`, `height_mm` | physical label stock loaded |
| `is_active` | |

### `label_templates`

| Column | Notes |
|---|---|
| `company_id` | |
| `name` | |
| `label_type` | enum — see §4 |
| `width_mm`, `height_mm` | |
| `engine` | enum `pdf`, default `pdf`. Room for `zpl`. |
| `layout` | **json** — positioned field list, see §5 |
| `is_default` | one default per (company, label_type) |

### `shelf_life_rules`
The storage-state matrix.

| Column | Notes |
|---|---|
| `company_id` | |
| `ruleable_type`, `ruleable_id` | morph: `IngredientCategory`, `RecipeCategory`, `Ingredient`, `Recipe`, `ProductionRecipe` |
| `storage_state` | enum — see §4 |
| `value`, `unit` | `unit` is one of `Recipe::SHELF_LIFE_UNITS` — minutes, hours, days, weeks, months |

Unique on `(ruleable_type, ruleable_id, storage_state)`.

**Resolution order** for an item + storage state, implemented in `ShelfLifeService`:

1. Rule on the item itself
2. Rule on the **Recipe behind a prep Ingredient** — a prep item is both a `Recipe`
   (where shelf life is edited today) and a mirrored `Ingredient`, so a label printed
   against the mirror must still find the rule set on the recipe
3. Rule on the item's category. `Ingredient` and `Recipe` both use `IngredientCategory`;
   `ProductionRecipe.category` is a bare string with no FK, so it borrows its linked
   `Ingredient`'s category
4. Legacy `shelf_life_value` / `shelf_life_unit` on `recipes` / `production_recipes`
5. Nothing — staff enters the use-by manually, and the row is flagged `manual_expiry`

The legacy fallback is deliberately narrow: it applies **only when the state being asked
about matches the item's own `storage_instruction`** (an unset instruction counts as
`chill`). A 3-day chill life says nothing about frozen, and guessing would put a wrong
date on a food-safety label.

Each resolution returns a `source` (`item`, `prep_recipe`, `category`, `legacy`,
`legacy_prep_recipe`) so the UI can show whether a life is inherited or set directly.

Existing `recipes.shelf_life_value` and `production_recipes.shelf_life_value` migrate
in against the item's stated storage instruction, defaulting to `chill`. **Do not drop
the legacy columns** — other screens read them, and the prep item form still writes
to them.

### `label_sets`
Outlet-owned collections. "Chiller 1", "Sandwich Station", "Grill Station".

| Column | Notes |
|---|---|
| `company_id`, `outlet_id` | **both required** — no company-wide sets |
| `name`, `description` | |
| `sort_order`, `is_active` | |
| `created_by` | user id |

### `label_set_lines`

| Column | Notes |
|---|---|
| `label_set_id` | |
| `sort_order` | **physically meaningful** — see §6 |
| `labelable_type`, `labelable_id` | nullable morph: `Ingredient`, `Recipe`, `ProductionRecipe` |
| `custom_name` | nullable — freeform line with no linked item |
| `label_type`, `storage_state` | **per line**, not per set |
| `copies` | default copy count |
| `quantity`, `uom_id` | nullable, printed on the label if the template has the token |
| `template_id` | nullable override |
| `is_active` | |

Per-line label type and storage state matter: "Chiller 1" is realistically twelve
chill use-by labels and two thawed ones. A uniform set is useless for the mixed case.

Either `labelable_*` or `custom_name` must be set. Enforce in the model, not the DB.

### `label_print_batches`

| Column | Notes |
|---|---|
| `company_id`, `outlet_id` | |
| `label_set_id` | nullable — null for ad-hoc single prints |
| `employee_id` | who was picked from the staff list |
| `user_id` | who was logged in |
| `printed_at` | **one timestamp for the whole batch** |
| `item_count`, `label_count` | denormalised for the audit list |

### `label_prints`
The compliance record. One row per line printed, carrying a copies count.

| Column | Notes |
|---|---|
| `company_id`, `outlet_id` | |
| `batch_id` | FK to `label_print_batches` |
| `printer_id`, `template_id` | |
| `labelable_type`, `labelable_id` | nullable morph |
| `custom_name` | nullable |
| `label_type`, `storage_state` | |
| `start_at`, `end_at` | computed use-by |
| `manual_expiry` | bool — staff typed the date because no rule resolved |
| `copies` | |
| `payload` | **json snapshot of exactly what was printed** |
| `status` | `sent` under the browser driver — see §5 caveat |
| `resolved_at`, `resolved_by` | set when a chef closes the label off (phase 4) |
| `resolution` | `used` \| `wasted` \| `discarded` — see §8 phase 4 |
| `wastage_record_id` | nullable; set only for `wasted` |

`payload` is frozen at print time. **Never re-derive a past label from live data** — the
item's shelf life will have changed by the time an auditor asks about it.

---

## 4. Enums

**Storage states:** `ambient`, `chill`, `frozen`, `thawed`, `opened`, `cooked`

**Label types**, and the storage state each defaults to:

| Label type | Caption | Default storage state |
|---|---|---|
| `prep` | USE BY | `chill` |
| `oof` | DEFROSTED | `thawed` |
| `received` | RECEIVED | item's own default |
| `opened` | OPENED | `opened` |
| `dry_store` | DRY STORE | `ambient` |
| `custom` | *(free text)* | manual |

**Use-by computation:** `end_at = start_at + value/unit`. If `use_by_rounding = eod`,
round `end_at` to 23:59 of the resulting day. `start_at` is the batch timestamp,
resolved **once per batch**, never per line.

> **Safety rule: end-of-day rounding is never applied to `minutes` or `hours`.**
> Rounding a 4-hour life up to 23:59 *extends* it. On a food-safety label that is a
> hazard, not a formatting nicety. `ShelfLifeService::useBy()` enforces this regardless
> of the company's rounding setting, and it is covered by a test.

---

## 5. Rendering

The template designer forces layout to be structured data, so the renderer is a generic
token walker rather than a per-template Blade file.

### `layout` JSON shape

```json
{
  "fields": [
    { "token": "item.name",   "x": 2, "y": 2,  "w": 46, "h": 6,
      "font_size": 10, "weight": "bold", "align": "left" },
    { "token": "label.caption", "x": 2, "y": 9, "w": 20, "h": 4, "font_size": 7 },
    { "token": "date.end",    "x": 2, "y": 14, "w": 46, "h": 5, "font_size": 9 },
    { "token": "static",      "x": 2, "y": 20, "text": "Keep refrigerated" }
  ]
}
```

Coordinates in **mm from the top-left of the label**.

### Tokens for v1

`item.name`, `item.code`, `label.caption`, `date.start`, `date.start_date`,
`date.start_time`, `date.end`, `date.end_date`, `date.end_time`, `staff.name`,
`outlet.name`, `company.logo`, `storage.instruction`, `quantity`, `batch.ref`, `static`

Deliberately absent: `allergens`, `nutrition`, `qr`, `barcode`.

### Two output paths, one Blade

```
LabelRenderService::html(array $labels): string   // browser driver + designer preview
LabelRenderService::pdf(array $labels): string    // archive, reprint, future PrintNode
```

Both render the **same** Blade partial per label. `html()` wraps it with
`@page { size: {W}mm {H}mm; margin: 0 }`; `pdf()` hands it to dompdf with a paper size
array of `[0, 0, W * 2.834645, H * 2.834645]` points and zero margins.

**Constraint:** the label Blade must stay inside dompdf's CSS subset — absolute
positioning and basic fonts only, no flexbox or grid — or the two paths diverge.
This is the price of a single source of truth and it is worth paying.

### Browser print flow

1. Livewire renders the batch to HTML
2. HTML goes into a hidden iframe
3. `iframe.contentWindow.print()`
4. Chrome, launched with `--kiosk-printing`, prints to the OS default printer with no dialog

**Copies are rendered as repeated pages**, not passed as a copy count — kiosk printing
won't honour a count.

**One print call per batch.** Firing twenty separate print jobs under kiosk printing
gives races, out-of-order output, and the dialog reappearing.

### Caveats of the browser driver

- **No print confirmation.** `status` records intent, not outcome. If the printer is out
  of labels, Servora believes it printed. The chef is standing there and will notice.
- **Default printer only.** Breaks if the laptop also has an A4 printer and someone
  changes the Windows default.
- **No printer online/offline visibility** for support.
- Opening normal Chrome instead of the shortcut brings the dialog back.
- **Driver page size must match the template mm exactly** or you get silent scaling and
  clipped text. Hence the calibration label in phase 3.

### Outlet laptop setup (one-time, per laptop)

```
chrome.exe --kiosk-printing --app=https://app.servora.com.my/labels
```

Plus: label printer set as the Windows default, and the label size set in the printer
driver's printing preferences.

---

## 6. Screens

Kitchen-mode layout, route prefix `/labels`.

| Route | Screen | Permission |
|---|---|---|
| `/labels` | Print — search, pinned favourites, recents, custom item | `labels.print` |
| `/labels/sets` | Print sets — list, create, drag-sort lines | `labels.print` |
| `/labels/sets/{set}/print` | Set review screen | `labels.print` |
| `/labels/templates` | Template list + designer | `labels.manage` |
| `/labels/shelf-life` | Shelf-life matrix bulk-edit grid | `labels.manage` |
| `/labels/printers` | Printer records per outlet | `labels.manage` |
| `/labels/log` | Print log / audit trail | `labels.view_log` |

### Print screen
Big tap targets — the chef has wet hands. Search across ingredients, prep items,
recipes and production recipes. Per-outlet pinned favourites and a recents strip.
Storage-state picker, copies, staff picker, custom freeform item.

### Set review screen
The set opens as a checklist: every line with a tick box and an editable copy count,
defaulted to **last-used per outlet** rather than the line's default. Chef unticks what
isn't being prepped, hits print, gets one document.

Sort order is physical — labels peel off the roll in print order and get applied walking
down the shelf. Drag-sort matters more than it sounds.

### Print log
Batches, not loose rows: *"Chiller 1 relabel — 14 items, 32 labels, 07:15, Aiman"*,
expandable to the individual lines with their frozen payloads.

---

## 7. Permissions

Three new Spatie permissions, added per
[08-feature-playbook §8](08-feature-playbook.md#8-add-a-new-role-or-permission):

- `labels.print` — chefs and kitchen staff
- `labels.manage` — templates, printers, shelf-life matrix
- `labels.view_log` — audit trail

Company admin gets all three.

---

## 8. Phasing

**Phase 1 — foundation** — *complete, 2026-07-30*

6 migrations, 8 models, `ShelfLifeService`, `LabelRenderService`, `LabelDriver`
interface + `BrowserDriver`, `LabelTemplateService`, and 6 stock templates (70×40mm
since `2026_07_30_000008`; originally seeded at 50×25 as a placeholder)
(one per label type, `custom` included — the plan originally said 5 before `custom` was
added to the type list). Templates are seeded for existing companies by migration and
lazily via `LabelTemplateService::ensureDefaults()` for new ones.

Screens, all gated on `labels.manage` with a "Labels" sidebar group:

| Route | Component |
|---|---|
| `/labels/shelf-life` | `Labels\ShelfLifeGrid` — matrix editor across categories and the three item types |
| `/labels/printers` | `Labels\Printers` — per-outlet printer records |
| `/labels/settings` | `Labels\Settings` — rounding, footer, fallback template, kiosk setup |

Two behaviours worth not regressing:

- **Clearing a cell deletes the rule; it does not store zero.** A zero-length shelf
  life prints a use-by equal to the prep time, which is worse than no rule at all.
  Blank and `0` are treated identically.
- **The settings screen has no PrintNode API key field.** Nothing reads it under the
  browser driver, and a field for a credential that does nothing invites someone to
  paste a real key into it. It gets a UI when a PrintNode driver ships.

The grid's state property is `$cells`, **not** `$rules`. `$rules` collides with
Livewire's conventional validation-rules property, and a loader named `hydrateRules`
collides with Livewire's `hydrate{Property}` lifecycle hook — Livewire invokes those
externally, so a private method of that name throws `BadMethodCallException`.

**Phase 2 — printing** — *complete, 2026-07-30*

`LabelPrintService` plus four screens:

| Route | Component | Permission |
|---|---|---|
| `/labels` | `Labels\PrintScreen` — search, queue, print | `labels.print` |
| `/labels/sets` | `Labels\Sets` — set CRUD + ordered line editor | `labels.print` |
| `/labels/sets/{set}/print` | `Labels\SetPrint` — review checklist | `labels.print` |
| `/labels/log` | `Labels\PrintLog` — audit trail | `labels.view_log` |

`LabelPrintService::print()` resolves `now()` **once** for the batch, computes each
line's use-by, writes a `label_prints` row carrying a frozen payload, and hands the
whole batch to the driver as one document. A chef-typed `end_at` always wins over a
resolved rule — it is only ever entered because nothing resolved, and overriding it
would silently discard the input.

Browser printing works by dispatching the rendered HTML to a `label-print` window
event; the page drops it into a hidden iframe and calls `print()`. `frame.onload` is
assigned **before** `srcdoc`, because setting `srcdoc` can resolve immediately for a
small document and the handler would otherwise never fire.

`DriverFactory` resolves a printer's transport and falls back to the browser driver
for an unimplemented value rather than throwing — a chef mid-shift should get a label
out of a misconfigured printer record, not a stack trace.

Changing a set line's label type re-points its storage state to that type's default.
Without it, switching a line to Defrost keeps a chilled state and prints the wrong date.

Note that `Recipe` and `Ingredient` **uppercase `name` on save** (`Recipe.php:60`), so
printed names and frozen payloads are uppercase. That is existing behaviour, not the
label module's doing.

**Phase 3 — designer + calibration** — *complete, 2026-07-31*

| Route | Component |
|---|---|
| `/labels/templates` | `Labels\Templates` — list, create, duplicate, set default |
| `/labels/templates/{template}/design` | `Labels\TemplateDesigner` — mm canvas + live preview |
| Calibrate button | on `/labels/printers`, per printer |

Positioning is **numeric, not drag-and-drop**: a label is 70mm across and the
difference between 3mm and 4mm matters, which is finicky to hit by dragging, and
numbers are exact so a layout can be read off and reproduced. Nudge buttons cover
"just move it a bit". The preview is the real renderer with sample data, not a mock-up.

The designer flags any field hanging off the label **in red and refuses to be quiet
about it** — anything past the page edge makes the browser paginate, and every extra
page is a wasted physical label. That bug cost real stock before it was caught.

Guard rails on the template list: exactly one default per label type (enforced on
write, not by index — two defaults resolve arbitrarily at print time and the chef
would never know which won); the last template of a type can't be deleted; deleting a
default hands the flag to a survivor.

### Printer setup, learned the hard way

Three separate things had to be right before a label printed correctly on the Deli
DL-888 (203dpi, 106mm max width):

1. **Paper size in the driver must be a real 70 × 40 mm form.** The DL-888's default
   preset is `2 x 4` inches = 50.8 × 101.6mm. Chrome laid the 70 × 40 page onto that
   sheet, which is 2.54 of our labels long — hence content spanning three labels — and
   **rotated it to best-fit**, which is where the mystery rotation came from. Neither
   was the app's doing. Create the form in the driver, or via Windows Print Server
   Properties → Forms, then select it in Chrome's Paper size dropdown.
2. **Margins: None, Scale: 100.** "Fit to printable area" defeats exact-mm sizing.
3. **The printer must learn the label gap** — hold the feed button until it
   self-measures, or it feeds a default length per job.

`rotate_90` on the printer record exists for stock that is genuinely portrait-fed. It
was **not** the fix here; the paper size was. Leave it off unless the driver only
offers the label the other way up.

**Phase 4 — closing the loop** — *complete, 2026-07-30*

`/labels/expiring` (`Labels\Expiring`, `labels.print`) reads unresolved labels off
`label_prints` into Expired / Today / Tomorrow buckets. `LabelExpiryService` closes
each one off.

Migration `2026_07_30_000007` adds `resolved_at`, `resolved_by`, `resolution` and
`wastage_record_id` to `label_prints`. Without somewhere to record that a chef has
dealt with a label, the list grows forever and last month's expired labels sit at the
top of it — which is how a compliance screen gets ignored.

**Three resolutions, deliberately distinct:**

| Resolution | Meaning |
|---|---|
| `used` | Consumed normally. Nothing to cost. |
| `wasted` | Binned **and** costed into a wastage record. |
| `discarded` | Binned but **not** costed — nothing priceable behind it. |

Keeping `discarded` separate from `wasted` is what keeps the wastage figures honest:
everything counted as wasted has a real cost behind it, and uncosted bin events stay
visible rather than being folded in at zero.

**Costing is the awkward part.** `wastage_record_lines` requires `quantity`, `uom_id`,
`unit_cost` and `total_cost`, all NOT NULL, and a label carries none of them. So:

- A label is costable only when it links to something priceable. Ingredients cost via
  `UomService::convertCost` against `recipeUom ?: baseUom`; recipes via
  `cost_per_yield_unit` and `yield_uom_id`; a `ProductionRecipe` costs against its
  mirrored ingredient. Freeform labels and labels whose item has since been deleted
  are not costable, and the row offers **Discard** instead of **Wasted**.
- The chef supplies the quantity at the point of binning. Inventing one — defaulting to
  1, or costing at zero — would quietly corrupt the cost report, so
  `markWasted()` with a zero or unpriceable line falls back to `discarded` rather
  than writing a wrong number.

Wasted lines append to **one wastage record per outlet per day**
(`WST-LBL-{Ymd}-{outlet}`) rather than one record per label. A chiller clear-out would
otherwise produce fifteen separate records and make the wastage report unreadable.

The `uom_id` guard in `costingFor()` is defensive rather than load-bearing today:
`recipes.yield_uom_id` and `ingredients.base_uom_id` are both NOT NULL, so neither
path can currently yield a null UOM.

---

## 9. Open questions

Blocking phase 3:

1. ~~**Which physical label sizes** ship as designer presets?~~ **Answered 2026-07-30:
   70 × 40 mm.** Stock templates re-cut to that size by `2026_07_30_000008`, which only
   touches templates still carrying the exact original layout so an edited one survives.
   The extra room over 50×25 bought a two-line item name, a use-by at 18pt instead of
   11, and the `footer` token — which `LabelPrintService` had been emitting all along
   with no stock template positioning it.
2. **Which printer make and model** are outlets buying? Brother QL and Zebra have
   different unprintable margins; the calibration tool needs to know its target.

Not blocking:

3. **Label language** — English only, or do items need a second BM name field?
4. **Allergens and QR traceability** — confirmed deferred, or actually dropped? The
   marketing pitch leads with both.
5. **Metering** — add-on module with a `UsageRecord` per label, or bundled into plans?
   Cheap now, awkward to retrofit.
6. **Selling label rolls** — the "43% cheaper" positioning implies a consumables
   business. In or out?
7. **Central Kitchen** — when CK produces for an outlet, does the label print at CK
   carrying the *destination* outlet's name? The data exists as of commit 8479538.
