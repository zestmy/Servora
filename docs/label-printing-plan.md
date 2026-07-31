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

### PrintNode — built 2026-07-31

Shipped after v1, and it cost almost nothing above the transport layer, which is
the point decision 2 was making.

`PrintNodeClient` is the only place that knows PrintNode's wire format: HTTP Basic
with the API key as username and an empty password, `POST /printjobs` with
`contentType: pdf_base64`, `GET /printers` for the picker. `PrintNodeDriver` sends the
**PDF** — PrintNode has no browser, which is what the `pdf()` half of
`LabelRenderService` was always for, and why the label Blade had to stay inside
dompdf's CSS subset.

What changed above the driver: nothing, except three things that were latent gaps.

- `LabelPrintService` was hardcoding `'sent'` on every row. It now applies the
  driver's status — `sent` for browser (we handed it to a browser and cannot know
  more) and `queued` for PrintNode (accepted, not necessarily printed). Rows are
  written before the driver runs, because building them is what produces the
  document, so the status is applied afterwards.
- `label_print_batches` gained `driver` and `driver_job_id`. The job id is per batch,
  not per label: one batch is one document is one job.
- The print screens dispatched a browser print event unconditionally. PrintNode
  returns no document, and firing the event would open a dialog with nothing in it.

**Rotation goes through PrintNode's job options, not CSS.** dompdf cannot do
transforms, so the `rotate_90` flag becomes `options.rotate = 90` on the job.
`fit_to_page` is always false, for the same reason browser printing is 100% scale.

**The job names its paper.** PrintNode has no renderer of its own — it hands the PDF
to the Windows print driver on the client machine, and with no paper named that driver
uses its own default form. If the default is not the label stock, the page is rotated
or scaled to fit *before it ever reaches the printer*, and no amount of correct
millimetres upstream survives it. This is the same trap as browser printing's "Paper
size" dropdown (see Calibration below) and the same trap as the A6-vs-4×6 QR sheet:
**never let a print path pick the paper for you.** So `label_printers.printnode_paper`
holds a form name chosen from the ones that printer actually reports, sent as
`options.paper`. Nullable, and null means "accept the driver default" — correct when
the default is already the label stock, which is why it isn't forced. The choice is
per printer because paper names belong to one driver, and it is cleared automatically
when the remote printer changes for exactly that reason.

**A printer set to `printnode` never falls back to the browser.** That printer is
deliberately not attached to this PC, so falling back would send the label to whatever
local printer is default — or silently nowhere. Only an *unrecognised* driver value
falls back. The errors are written to be shown to the person standing at the printer.

**The API key is write-only in the UI.** It is never bound to a form field or rendered
into the DOM; the screen reports only whether one is set. An empty box on save means
"keep", not "delete" — otherwise an unrelated settings change would silently wipe the
credential. There's an explicit Remove button, and a Test button that calls
`/printers` so a wrong key is found in settings rather than at the printer.

**Job reconciliation** (added same day). `labels:reconcile-jobs` runs every ten
minutes and asks PrintNode what actually happened. Without it a PrintNode label sits
at `queued` forever and the log claims a label exists that may never have come out —
for a compliance record that is worse than useless, it is confidently wrong.

`LabelPrint::STATUSES` is now `sent` (browser handed it over, nothing reports back —
as much as can ever be known) → `queued` → `done` / `error` / `expired`. PrintNode
states in flight (`new`, `sent`, `queued`) are left alone to be asked about again.

Three things the reconciler has to get right, all tested:

- **It must never throw.** It runs unattended across every company, and one tenant's
  revoked key cannot be allowed to stop everyone else being reconciled. Failures are
  counted and logged; the command still exits zero, or the scheduler would shout every
  ten minutes about one bad key.
- **It runs with no authenticated user**, so `CompanyScope` would silently match
  nothing. Every query drops the scope and filters by company by hand.
- **It only looks back 7 days** and only at batches with rows still pending, so
  settled work drops out of the sweep instead of being re-asked forever.

The state response shape is parsed defensively — flat list or grouped-by-job both
work — because a reconciler that throws on an unexpected shape leaves every job stuck.

### Prepared by is mandatory

Enforced in both print screens *and* as an invariant in `LabelPrintService`. An audit
row that names nobody is the one thing this log exists to prevent: "who prepped this"
is the question an auditor asks, and a blank answer makes every other field academic.
Historical rows keep a nullable `employee_id`; the rule applies going forward.

Both screens also clear the previous attempt's error before retrying — without that,
fixing the problem and pressing print again still showed the old complaint.

**Not built:** master/child account provisioning. The original scoping answer was
"support both", and this is the BYO-key half. Child accounts under an Integrator plan
are a separate piece of work — see open question 8.

**Unverified:** the live API round-trip. Every test fakes the HTTP boundary, because
there is no PrintNode account to test against. The wire format is written to PrintNode's
documented API but has never been exercised against the real service.

### Staff app on the company subdomain — built 2026-07-31

`https://{slug}.servora.com.my/labels` — a phone-shaped app kitchen staff reach with
a PIN, no Servora login. Print, Sets and Expiring, with bottom-tab navigation.

**One PIN per employee, not a shared door code.** Signing in identifies the person, so
"Prepared by" fills itself — the mandatory attribution stops being something a chef can
skip — and their outlet comes from their employee record, which is what makes the
outlet-scoped screens work at all on a subdomain that only resolves a *company*.

**Name first, then PIN.** PINs are bcrypt-hashed, so finding an employee from a PIN
alone would mean hashing against every employee in the company on every attempt. That
is slow by design and would push towards a fast hash instead — the wrong trade for a
4–6 digit secret. Picking a name makes it one check, and it is how every POS works.

**Sessions last until the PIN changes.** The session stores a fingerprint of the PIN
hash and re-checks it on every request. Changing or revoking a PIN, or deactivating the
employee, drops every session opened under the old one — no expiry needed. All four
paths are tested.

Attempts are throttled **per employee, not per IP**: a shared kitchen tablet is one IP
for everyone, so IP throttling would lock out the whole kitchen because one person
fumbled their PIN.

**Two routing traps, both of which would have silently broken it:**

1. `EnforceMainDomain` redirected every non-`/lms` path on a company subdomain to
   `/lms/login`. `/labels` is now allowed through explicitly.
2. The manager-facing `/labels` routes carry **no domain constraint**, so they match any
   host. Laravel matches in registration order, so they would have swallowed every
   subdomain request. `routes/labels-staff.php` is therefore `require`d at the **top of
   `routes/web.php`** — registration order is the only thing separating the two. It
   uses a `{companySlug}.<domain>` constraint in production and falls back to a
   `/labels-staff` prefix locally, where `APP_DOMAIN` is unset. Route names are
   identical either way.

**Livewire's update endpoint needed the subdomain middleware too.** This was a live
bug: the initial page load ran `company.subdomain` and bound `currentCompany`, but
every subsequent tap posts to `/livewire/update` — a route Livewire registers itself,
which does **not** inherit the page route group's middleware. So the second request
looked like a main-domain request, the employee list came back empty, and tapping a
name showed "Nobody has label access yet".

Fixed globally in `AppServiceProvider` via `Livewire::setUpdateRoute()`. This matters
beyond the staff app: `CompanyScope` falls back to `currentCompany` when there is no
authenticated user, so without it a Livewire request from any subdomain page would
apply **no company filter at all**. Components also fall back to the
`subdomain_company_id` the middleware stores in the session, so neither mechanism is a
single point of failure.

**No web user exists in this context**, which had knock-on effects worth remembering:

- `CompanyScope` resolves via `app('currentCompany')` on a subdomain, so it happens to
  work — but the staff components scope by hand anyway rather than depend on that.
- `LabelPrintService` took the company from `Auth::user()`. It now takes it from the
  **printer**, which always knows its own company and outlet.
- `previewUseBy()` gained an explicit company argument for the same reason.
- `label_prints.resolved_by` points at users. Staff have none, so
  `resolved_by_employee_id` was added — otherwise "who binned it" would be blank for
  precisely the people doing the binning.

**Installable as a PWA.** Manifest and service worker are served through Laravel
rather than as static files, because the app's base path differs by environment
(`/labels` behind a subdomain in production, `/labels-staff` locally) and both the
manifest's `start_url` and the worker's scope have to match wherever it is mounted.
A controller, not route closures — `deploy/update.sh` runs `route:cache`.

The service worker is **deliberately cautious**: non-GET requests are never
intercepted, navigations are network-first with a plain offline notice, and no page is
ever cached. A label app must not serve a stale shelf life or let someone believe they
printed while offline. Livewire posts to `/livewire/update`, which sits outside the
worker's scope, so interactions never touch it at all.

Icons are generated by a script rather than committed as opaque binaries, so the shape
stays editable. Includes a maskable variant with the glyph inside the safe zone, since
Android crops to its own shape.

**The shell is pinned with `position: fixed; inset: 0`, not sized with a viewport
unit.** Two earlier attempts failed on a real phone: a `fixed bottom-0` nav sat at
different heights depending on whether the page scrolled, and `100dvh` still left a
strip of background below the bar. Anchoring to the edges asks the browser where its
viewport actually is rather than computing a height and hoping it agrees. The header
and nav are ordinary flex children of that shell and the middle region scrolls, so the
nav has nothing to drift relative to.

**Safe areas matter here.** `viewport-fit=cover` plus a translucent status bar means
the page starts at y=0 and runs *under* the clock and battery — and Android 15 draws
PWAs edge-to-edge regardless. The header fills that strip (which looks deliberate) but
its content is pushed down by `env(safe-area-inset-top)`. Screens with no header take
the inset themselves. This was a live bug: the first build only handled the bottom
inset, so the title sat under the phone's clock.

**Branding follows the LMS.** Company logo and `brand_name` are resolved exactly as
`layouts/lms.blade.php` does, so a company that has branded its training portal is
branded here with no extra setup. The header logo sits on a white pill because most
logos are dark artwork that would vanish against the indigo bar. The manifest's `name`
is branded for the install prompt, but `short_name` stays "Labels" — a home screen
truncates at roughly twelve characters.

**QR codes take a chef straight to a set.** Managers get a QR per set on
`/labels/sets`, plus a printable sheet at `/labels/set-qr-sheet` in two sizes:

- **4 × 6 in (default)** — 101.6 × 152.4mm, the common airway-bill stock. One set per
  label, peel and stick.
- **A6** — 105 × 148mm. Also sold as airway-bill stock, and **not** the same as 4 × 6:
  different size, different aspect ratio.
- **A4** — four cut-out cards to a page for setting up a whole outlet at once.

**Page sizes are declared in explicit millimetres, never a CSS keyword.** This was a
live bug: the sheet asked for `size: A6` while the printer held 4 × 6 stock, so the
browser rotated and shrank the page to fit and produced a postage-stamp label in the
corner of a blank one. Naming the millimetres means the page is exactly the media and
there is nothing to reconcile. The margin and QR size scale from the page dimensions,
so adding a size needs no new numbers.

The same trap as the 70 × 40 labels, in a different disguise — see the printer setup
notes below. If output is scaled or rotated, the paper size in the print dialog is the
first thing to check, then Margins (None) and Scale (100).

Error correction is level Q, because a sticker on stainless steel gets smudged and
partly peeled. "Powered by Servora" appears on each A6 label and once at the foot of an
A4 sheet, rather than repeated on every cut-out card.

**Each card carries the target storage temperature**, boxed and heavy — the label lives
on the unit door, so the useful fact is what that unit is supposed to be holding, read
against the thermometer. An item count told nobody anything.

**Which temperature prints is set per set**, on the set edit dialog: a toggle to show
it at all, plus a choice of states. Leaving every box unticked keeps the original
behaviour — derive from whatever the set's items use — which is what every existing set
does and what new ones default to.

The explicit choice exists because deriving is a good default and a poor rule: a
chiller door is a chiller door even when the set holds one frozen item, and the label
on that door should say 0-4°C rather than listing both and leaving staff to work out
which applies to the unit in front of them.

Either way the list is ordered by `ShelfLifeRule::STORAGE_STATES`, so two sets never
disagree about the order, and unrecognised states are dropped on save.

Ranges start from `ShelfLifeRule::STORAGE_TEMPERATURES` — the standard HACCP figures —
and each company can override any of them on the Label Settings screen. Free text, so
it can be worded the way a given auditor expects.

**Only genuine overrides are stored.** A field left blank, or typed back to match the
standard, is not persisted: storing a copy would freeze that company on today's wording
and a later correction to the shared figure would silently never reach them. There's a
Reset all that clears every override.

A company can also define a range for a state that has no standard one — `opened` ships
without a figure, because an opened item might belong in a chiller or on a dry-store
shelf and inventing one on a food-safety label would be worse than leaving it off.

Two things this depended on:

- **The URL is built by hand, not with `route()`.** These are generated in the manager
  app on the *main domain*, where the subdomain route defaults aren't bound — `route()`
  would emit a main-domain link that sends staff to a login they cannot use.
- **Scanning while signed out has to come back to the set.** The middleware stores the
  intended URL and sign-in honours it, or the QR would be pointless: the chef would
  land on the default screen and have to find the set by hand anyway. The stored URL is
  validated on use — same host *and* inside the staff app's own path — so it can never
  become an open redirect. Only GETs are remembered; replaying a bounced POST after
  sign-in would be a surprise.

**Staff can add and remove items in a set** from the set screen, behind an "Edit items"
toggle. Originally read-only on the reasoning that managers build sets and editing one
on a phone mid-shift was nobody's idea of fun — but staff are the ones standing at the
station when they notice something missing, so they now can.

Editing is deliberately gated behind the toggle rather than always-on: a mis-tap on a
list built for gloved fingers should not reorganise a station. The screen states
plainly that changes apply to the whole outlet, not just today, and removal asks for
confirmation. New lines append to the end, since order is physical and something added
mid-shift is added to the end of the walk. Whole sets are still manager-only — this is
items, not sets.

Adding or removing a line keeps the checklist state in step. Without that, a removed
line's stale entry would linger and be counted on the next print, and a newly added
line would look ticked but be skipped.

Four tabs: Print, Sets, Expiring, Log. The staff log is read-only and scoped to the
member's own outlet — it shows the whole outlet's activity, not just their own, because
the question being answered is usually about a label someone else printed.

Managers administer access at `/labels/staff-access`: issue a random PIN (shown **once**,
stored hashed, unrecoverable by design), set one manually, or revoke. Staff without an
outlet cannot be given access, because there would be no printer, no sets and nothing
to expire. Staff can change their own PIN in the app.

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

**Text is shrunk to fit its field, not clipped.** A long value used to wrap past the
bottom of its own box and land on top of the field below — a 27-character staff name
needed 5.7mm in a 4mm box and collided with the footer, which is exactly what a real
print showed. Neither renderer saves you: `overflow: hidden` needs honouring on an
absolutely positioned box, and dompdf ignores it outright.

Truncating was the alternative and is worse on a food-safety label — half an item name
is a label nobody can act on — so `fitFontSize()` steps the size down until the wrapped
height fits, with a 5pt floor below which 203dpi thermal stops being readable anyway.
The metric estimates Helvetica's advance width and is deliberately pessimistic, erring
towards slightly small rather than slightly overlapping.

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

**Grouping by print set** *(added 2026-07-31)*. Both expiring screens can restack the
same rows under the set the labels were printed from — "Bar Chiller", "Grill Station" —
because that is the station a chef physically walks to. No schema change: the batch has
carried `label_set_id` since the print service was written.

The set is read off the **batch**, not the item, and the distinction is the whole point.
It answers *where was this labelled*, not *where does this item belong*. An item that
also happens to be in the Bar Chiller set but was printed ad-hoc did not come off that
run, so it appears under **Not from a set** rather than being claimed by a set it was
never printed with. That group is ordered by urgency along with the rest instead of
being parked at the bottom, because ad-hoc prints are the ones most easily forgotten.

Both groupings are built from the *same* three bucket queries, so the toggle only
restacks — it can never hide a row. On a food-safety screen that property is worth more
than the tidier query a single ordered fetch would allow. The manager screen adds a set
filter; the staff app deliberately does not, because on a phone the grouping already is
the filter and a second control competes for the same thumb.

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
8. **PrintNode account model** — BYO key is built. Do you also want master/child
   provisioning, where Servora holds an Integrator plan and creates a child account
   per company? That removes tenant onboarding friction and lets you bill for it, but
   you carry the per-printer cost and it needs an account-lifecycle flow (create on
   subscribe, suspend on cancel). The original scoping answer was "support both";
   only the BYO half exists today.
9. ~~**Job reconciliation**~~ — **Built 2026-07-31**, `labels:reconcile-jobs` every ten
   minutes. A webhook would be lower-latency than polling if volume ever makes the
   sweep expensive.
