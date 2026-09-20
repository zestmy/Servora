# Asset Listing — how the module is put together

Shipped 2026-09-20. This is the reference for anyone changing it; the reasoning
for individual decisions lives in the file that carries them, and this page says
where those files are and how they fit.

## What it answers

*What do we own, where is it, and what is it worth?* — for the things an outlet
**keeps** rather than consumes: utensils, smallwares, appliances, equipment,
furniture. Inventory & Recipes answers the same question for the things it uses
up, and the two are separate modules on purpose: different people, different
cadence (a chef counts the walk-in weekly, somebody in the office counts the
plates quarterly).

## The shape of it

| Screen | Route | What it is |
|---|---|---|
| Asset List | `assets.index` | The catalogue — the Market List for assets. Name, code, category, unit, unit cost, brand/model, photo, suppliers. Categories are managed on the same page. |
| Asset Register | `assets.register` | What is held and what it is worth, per outlet or across all of them, broken down by category. |
| Asset Records | `assets.records` | Counts, receipts and disposals in three tabs. |
| Asset Count | `assets.counts.create` / `.show` | The stock take for assets. |
| Receipt / Disposal | `assets.movements.create` / `.show` | One form, two document types. |

## The data model

```
asset_categories ──< assets >── asset_suppliers ──> suppliers
                       │
                       ├──< asset_count_lines >── asset_counts      (per outlet)
                       ├──< asset_movement_lines >── asset_movements (per outlet, receipt|disposal)
                       └──< purchase_request_lines.asset_id
```

**One row per item TYPE, not per physical unit.** An outlet counts 240 dinner
plates, not 240 tagged plates. A register that demanded a serial per unit would
be unusable for the 90% of an F&B asset list that is smallwares. If per-unit
tracking is ever needed for high-value equipment, it is a `serialised` flag on
`assets` plus a unit table — additive, and nothing here has to move.

**No balance column anywhere.** Quantity on hand is derived, never stored, so
there is nothing that can drift out of step with the documents behind it.

## The one piece of arithmetic

`App\Services\AssetOnHandService` is the only place that works out how much of an
asset an outlet holds. The register, the count sheet's "expected" column and the
receipt form's "on hand now" all read it, so they cannot disagree.

> An asset's quantity is what the last completed count **that listed it** found,
> plus every receipt and minus every disposal after that count.

Three things in that sentence are decisions, each argued at length in the class
comment and pinned by `tests/Feature/AssetOnHandTest.php`:

1. **A completed count is authoritative, not a variance report.** Ingredients
   have a purchase and consumption ledger to check a count against. Assets have
   no consumption — a plate is not used up, it is broken, and the breakage
   nobody wrote down is only ever found by counting. So the count *becomes* the
   baseline.
2. **The baseline is per asset, not per count.** A count may cover one category
   or one section of the outlet. An asset that was not on that sheet was not
   counted zero, it was not counted at all.
3. **A movement dated the same day as the count is broken by when it was
   entered** (`asset_movements.created_at` vs `asset_counts.updated_at`). Count
   in the morning, take a delivery in the afternoon, book it in: by date alone
   those cannot be ordered, and either fixed rule loses a real document
   silently.

## The photograph

One per asset, in `assets.image_path` — a column rather than a gallery table,
because the picture answers one question ("is this the one?") for somebody
telling two mixing bowls apart on a count sheet, and a second picture adds
nothing to that.

Stored on the **public** disk under `asset-photos/{company_id}/`, unlike the
employee photograph in the same tree: staff photos are identity documents and
sit on `local` behind an authorised route, while this is a picture of a knife,
served directly so a count sheet can draw forty of them without forty authorised
requests.

It shows on the asset list, on the register, on the count sheet, and in every
asset picker: the count form's, the receipt/disposal form's and the purchase
request's.

The register and the count sheet enlarge on tap; the asset list does not, and
the difference is deliberate. A list row has an edit button that opens the photo
full size in the modal, so the thumbnail is a doorway. The register has no way
in at all, and the count sheet is read by somebody standing in front of the
thing — 40px is not enough once you are down to which of two knives.

Uploads go through `RejectsUnpreviewableUploads` and `ImageStorageService`, like
every other upload in the product: HEIC is converted on arrival so an iPhone
sending originals works, anything the browser cannot draw is refused **as it
lands** rather than at save (the preview calls `temporaryUrl()`, which throws on
those and takes the whole form down with it), and what is stored is
auto-oriented, stripped of metadata and capped at 1600px.

Removing a photo is **deferred to Save**, unlike `EmployeeForm` where it applies
immediately — this is a modal with a Cancel beside it, and a picture of a knife
is not something anybody needs gone within the second. Replacing one deletes the
file it replaced, but only after the row is safely on the new path.

## The printed count sheet

`assets.counts.count-sheet` → `AssetCountSheetController` → `pdf.asset-count-sheet`.
Built like the ingredient count sheet, with the one thing that sheet has no use
for: **a photograph per row**. That is why this is worth printing rather than
reading names off a tablet.

Grouped by top-level category in the same order the form builds the sheet, so
the paper and the screen walk the outlet the same way. **No expected quantities
are printed** — a sheet that says what it expects gets that number written back
onto it. The variance is worked out afterwards, against what was found.

Photos are embedded as data URIs through `PdfImage::thumb()`, capped at 160px:
dompdf fetches nothing over the network, and there is one photo per row, which
is how a count sheet blows a memory limit. The fitted width and height are
worked out in the controller because dompdf has no `object-fit` — an `<img>`
given both dimensions stretches to them, and a squashed photo on a sheet whose
job is telling two similar objects apart is the one thing it must not do.

Measured on 200 rows, each with its own 1600px photo: **15.7 s and 162 MB cold,
3.1 s warm**, producing a 1.1 MB PDF. `PdfImage` caches per file + mtime, so
only the first print after a photo changes pays the decode. That is inside the
prod box's 256 MB / 60 s, but it is the number to re-measure before this export
grows — a catalogue two or three times that size would want queueing, the way
the SOP exports already are.

## Permissions

Nine abilities under the `assets` module in `config/permissions.php`, split the
way Ingredients and Inventory are split: `view` / `manage` / `cost` / `delete`,
then `counts.record|delete|reopen` and `movements.record|delete`.

`assets.cost` is separate from `assets.manage` for the reason `ingredients.cost`
is: renaming a thing is housekeeping, repricing it moves the value of everything
the register reports.

Existing installs were backfilled from the nearest equivalent ability — see
`2026_09_20_000008_add_asset_module_permissions` for the mapping and why.

**Form routes need the record ability**, matching the stock-take routes:
`assets.view` reads the list and the register but does not open a document for
editing, so the records list hides the links it cannot follow.

## Purchasing

An asset can be asked for on an ordinary purchase request — same screen, same
approver, same queue as a request for flour. The line carries `asset_id` and no
`ingredient_id`. That used to be what kept it out of a purchase order
entirely; it is now simply how a line says which of the two things it is.
Both the direct PR → PO conversion and CPU consolidation carry it.
The consolidation preview **counts** them (`asset_line_count`) and says so on
screen, because a line that vanishes without a word is how a request gets
approved and then forgotten.

### Assets on a purchase order — phase one (2026-09-21)

**This supersedes the decision recorded above.** An asset line now carries
from a request onto a purchase order: `purchase_order_lines.ingredient_id` is
nullable and `asset_id` sits beside it, exactly as on the request line. The
order form, the split-by-supplier path, the activity trail and the PO PDF all
handle both kinds.

Three things were only found by testing, and are worth knowing before touching
this again:

- **Nothing may key a line on `ingredient_id` alone.** Every asset line has a
  null one, so `keyBy('ingredient_id')` silently collapses them into a single
  bucket — an order with two assets then adjusts and audits as though it had
  one. `PurchaseOrderLine::lineKey()` is what to use.
- **`lookupSupplierInfo()` must never see an asset.** It reads
  `supplier_ingredients`, which an asset has no row in, and returns the
  supplier's defaults — so re-pricing an asset line replaced its UOM with null
  and its cost with zero. Simply choosing a supplier destroyed the line.
- **An asset's supplier lives in `asset_suppliers`.** `PoSplitService` drops
  any line it cannot put under a supplier, so the ingredient-only lookup made
  an asset vanish from a split order while its money stayed on the header.

### Receiving an ordered asset — phase two (2026-09-21)

An asset now rides the delivery order and the GRN beside the ingredients it
was ordered with, and **parts company at the moment of receiving**:

- an ingredient becomes a `PurchaseRecord` line — the inventory receipt;
- an asset becomes an `AssetMovement` receipt, the same document somebody
  would otherwise key by hand under Assets ▸ Receipts, so `AssetOnHandService`
  remains the only thing that decides what an outlet holds.

`AssetReceiptFromGrnService` does the second half. The receipt is keyed on
`asset_movements.goods_received_note_id`, so one GRN can only ever produce one
receipt — the form already refuses a GRN that is not pending, but the register
is what an outlet is audited against, so the property is held in the service
too rather than only in a screen.

**`purchase_record_lines.ingredient_id` is deliberately still `NOT NULL`.**
That table means *stock arrived*, and an asset is not stock. Leaving the
column strict means the database itself refuses an asset if a future change
ever routes one there by mistake. Do not relax it.

Two more totals-and-matching traps, the same family as phase one's:

- **The GRN total and the stock total are different numbers.** The GRN is
  worth what the supplier delivered, assets included — the invoice is
  generated from it and they billed for the mixer. The purchase record counts
  ingredients only, or an asset shows up as money spent on food.
- **A GRN line matches its DO and PO line on the asset when it has one.**
  `where('ingredient_id', null)` matches no SQL row at all, so an asset's
  delivered quantity was silently never written back; worse, a Collection's
  `firstWhere(..., null)` matches the *first* null row, so on a delivery of
  two assets both credited the same order line and the other never showed as
  received.

A delivery of nothing but assets writes **no** purchase record at all — an
empty one would read as a purchase of nothing.

The invoice auto-generated from the GRN **does** carry asset lines. Its header
total is summed from every GRN line, so excluding them from the lines alone
would produce an invoice that does not add up to itself.

### Consolidation — phase three (2026-09-21)

CPU consolidation carries asset lines too. It was the last place where the
same request behaved differently depending on the route it took: converting a
request directly ordered the mixer, consolidating the identical request
dropped it.

There are **three separate merge points** in `PurchaseRequestService` and all
three had the same flaw — `consolidate()`, `consolidationPreviewWithCosts()`
(what the screen renders) and `consolidateFromCustomized()` (what runs when
that preview is confirmed). Each grouped lines on `ingredient_id`, which is
null on every asset, so a consolidation covering a mixer and an oven merged
them into one order line: the quantities added together and one of the two
stopped existing. `PurchaseRequestService::mergeKey()` is what to group on.

**Assets price out of `asset_suppliers`, not `supplier_ingredients`.** The
rest of the service reads the latter; asking it about an asset returns
nothing and every asset consolidates at zero. There is an `assetCosts()`
helper for the service and an `asset_cost_lookup` handed to the screen, so
moving an asset to another supplier on the preview re-prices it instead of
keeping the first supplier's figure.

The preview still counts asset lines, but the notice now says what happens
*after* the order rather than claiming they are left out of it: they are
ordered like anything else and received into the register rather than into
stock.

**Still not threaded through**: credit notes for assets. `CreditNoteLine` has
no `asset()` relation, and the credit-note PDF deliberately does not
eager-load one — so returning a faulty mixer to a supplier is not supported.

## Not in v1

- Depreciation. Value is at cost. `assets` has no `useful_life_months`; adding
  one plus an acquisition date per receipt line would be the start.
- Per-unit serial numbers, warranty expiry, service schedules.
- Asset transfers between outlets (a disposal at one and a receipt at the other
  works today, but it is two documents and nothing ties them together).
- Excel exports, and a PDF of the register (the count sheet has one — see above).
