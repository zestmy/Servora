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
| Asset List | `assets.index` | The catalogue — the Market List for assets. Name, code, category, unit, unit cost, brand/model, suppliers. Categories are managed on the same page. |
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
`ingredient_id`, which is what keeps it out of a food PO: both consolidation
paths in `PurchaseRequestService` already skip a line without an ingredient.
The consolidation preview **counts** them (`asset_line_count`) and says so on
screen, because a line that vanishes without a word is how a request gets
approved and then forgotten.

Buying the thing is then: approve the request → buy it → record what arrived
under Assets ▸ Receipts, which prefills from the request via
`/assets/movements/create?pr={id}`.

A full PR → PO → GRN → invoice path for assets was deliberately **not** built:
`asset_id` would have to thread through ~100 `ingredient_id` call sites in the
live procurement chain, including invoice matching and ingredient cost writes.
If it is ever wanted, it is its own piece of work with its own risk budget.

## Not in v1

- Depreciation. Value is at cost. `assets` has no `useful_life_months`; adding
  one plus an acquisition date per receipt line would be the start.
- Per-unit serial numbers, warranty expiry, service schedules.
- Asset transfers between outlets (a disposal at one and a receipt at the other
  works today, but it is two documents and nothing ties them together).
- PDF and Excel exports of the register and of a count.
