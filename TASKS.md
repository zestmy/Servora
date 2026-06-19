# Tasks

## Active
<!-- Current work in progress -->

## Backlog
<!-- Planned but not started -->

## Done
<!-- Completed tasks, most recent first -->
- 2026-06-19 — Zeoniq Excel import: update upload guide to specify the exact report — Daily Summary Listing with Statistics + Session + Department sections
- 2026-06-19 — Fix secondary recipe UOM costing wrong value in recipe & prep-item forms (base==recipe case mis-costed via purchase_price instead of recipe-UOM cost; prep items via secondary UOM costed 0); resolve secondary UOM by chaining off recipe-UOM cost in UomService::convertCost. LMS SOP (screen + single/all PDF) now shows secondary recipe UOM as the main figure with primary recipe UOM in brackets for reference (RecipeLine::sopUomDisplay)
- 2026-06-19 — Propagate ingredient price changes to prep-item costs: IngredientObserver + PrepCostService recompute stored prep cost (recipes.cost_per_yield_unit + synced ingredient.current_cost) transitively whenever any cost field changes (manual edit, quick-edit, CSV import, GRN/PO receipt, doc review); `php artisan preps:recalculate-costs` backfills stale data
- 2026-05-16 — Fix drag-and-drop reorder causing multiple inputs to change; include index in wire:key for proper Livewire morphing
- 2026-05-16 — Auto-convert standard SI units (kg→g, L→ml) without manual conversion factor
- 2026-05-16 — Fix prep item form ignoring pack_size in cost calculation
- 2026-05-16 — Fix ingredient cost calculation ignoring pack_size when factor=1
- 2026-05-16 — Add bulk delete feature for recipes and prep items
- 2026-05-15 — Feature 20: Duty Roster with planned OT; weekly grid Mon-Sun, configurable stations per outlet, BOH/FOH section filter, flexible shift hours, auto OT calculation + manual override, full approval workflow (draft→submitted→approved/rejected), PDF export, email distribution, amendment tracking with reasons, revision numbering, user tracking (created by/last edited by), OT Claim integration (auto-creates pending claims on approval); 12 migrations, 8 models, 4 Livewire components, 2 services, 5 views
- 2026-05-13 — Feature 19: HR Documents with Google Drive API browser; file browser with grid/list view, folder navigation, inline preview, search; GoogleDriveService
- 2026-04-27 — Feature 18: Remove outlet switcher; ScopesToActiveOutlet uses availableOutletIds() whereIn scope; User::activeOutletId() no longer session-driven; outlet display removed from sidebar/profile
- 2026-04-27 — Feature 17: Persistent login (remember=true by default, SESSION_LIFETIME=43200)
- 2026-04-27 — Feature 16: HR Manager role with cross-outlet OT claim visibility; wildcard OT approver seeding; migration 000179
- 2026-04-27 — OT Claims Summary PDF: portrait A4, employee-aggregated rows (No., Name, Staff ID, Designation, OT Type, OT Hours, Total OT Hours)
- 2026-04-27 — PrepItemForm: fix RM0 cost for prep items as sub-ingredients; remove is_prep filter; self-reference prevention
- 2026-04-23 — Z-Report import: session priority logic (suppress all-day when sessions detected), gross→net back-calculation, per-session net preview in review UI
- 2026-04-23 — CI/CD: GitHub Actions auto-deploy on push to main via appleboy/ssh-action + VPS_SSH_KEY + GH_PAT secrets
- 2026-04-23 — Nav sidebar: "Scan Z-Report" CTA button (sky blue, permission-gated, auto-opens modal via ?scan=zreport)
- 2026-04-20 — Ingredients: filter for ingredients missing a UOM conversion factor
- 2026-04-20 — Price History summary: horizontally scrollable on mobile
- 2026-04-20 — Mobile: horizontally scrollable tables across settings + reports
- 2026-04-20 — Price Watcher review: fall back to ingredient.purchase_price when no supplier history
- 2026-04-20 — Price Watcher: back-fill a baseline iph row when none exists on import
