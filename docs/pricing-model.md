# Pricing model

Agreed with Affandy on 2026-09-28. This replaces the flat Starter / Professional /
Enterprise plans (RM99 / 249 / 499). Legacy companies keep their grandfathered
top-plan subscription (ends 2037-12-31) and are not moved.

All prices are MYR per month.

## Suites (per outlet)

| Suite | Price | Contents |
|---|---|---|
| **Free** | RM0 | 1 outlet only. Costing hook: market list (150 items), recipes (30), prep items, supplier list, manual stock count, manual sales entry, basic food-cost reports. 2 users. No purchasing, no AI, **no add-ons**. |
| **Basic** | **RM180 / outlet** | Costing (unlimited), Purchasing (PR, PO, DO, GRN, invoices, credit notes, PO email, approvals, CPU, price alerts), Inventory (stock take, wastage, transfers, par levels), Sales & Reports (records, targets, all reports, scheduled reports, exports). 30 AI invoice scans per outlet per month. |
| **Full** | **RM400 / outlet** | Basic + all six add-ons below. HR is **not** included. |

A kitchen is not an outlet: Central Kitchen is priced per kitchen (below) on
either suite.

## Add-ons (per company, flat; paid suites only)

A Basic company may take **at most two** of these. Wanting a third means moving
to the Full suite — without the cap, Basic + all six undercuts Full from three
outlets up, so chains would never take Full.

| Add-on | Price | Contents |
|---|---|---|
| Food Safety Labels | RM80 | Labels, shelf life, print agent, staff label PWA |
| Learn SOP | RM100 | Courses, quizzes, paths, live sessions, certificates, SOP library |
| Audits & Compliance | RM60 | Audit forms, audits, corrective actions, schedules |
| Assets | RM50 | Register, counts, receipts, disposals |
| POS Sync | RM60 | Automatic sales import from the POS |
| AI Insights | RM80 | AI Analysis, WIP review insights, extra AI allowance |

## Priced separately (on Basic or Full; not counted in the two-add-on cap)

| Item | Price | Contents |
|---|---|---|
| **HR & Payroll** | **RM3 / employee**, minimum 10 employees (RM30) | Employees, staff PINs, roster, shifts, clock-in (kiosk, QR, phone), attendance, leave, OT claims, documents, service charge, compensation, payroll, payslips, EA forms, labour cost |
| **Central Kitchen** | **RM300 / kitchen** | Production orders, production recipes, CK inventory, transfers to outlets, yield analysis |
| AI credit pack | RM29 one-off | 100 extra AI scans / analyses |

## Assumed defaults — not yet confirmed

- Free limits: 150 items, 30 recipes, 2 users.
- Paid suites: unlimited users (PIN-only staff never count as users).
- Yearly billing: 2 months free (yearly = 10 × monthly).
- Volume: 10% off from the 6th outlet, 15% from the 11th; 20+ outlets quoted as Enterprise.
- Trial: 14 days of Full + every add-on, then falls to **Free** (read-only above
  the Free limits) instead of the whole account going read-only.

## Worked examples

| Company | Monthly |
|---|---|
| 1 outlet, Basic | RM180 |
| 1 outlet, Basic + Labels + HR (15 staff) | 180 + 80 + 45 = RM305 |
| 3 outlets, Full + HR (60 staff) | 1,200 + 180 = RM1,380 |
| 5 outlets, Full + Central Kitchen + HR (120 staff) | 2,000 + 300 + 360 = RM2,660 |

## How it is enforced

- `config/modules.php` — the catalogue (names, list prices, kinds) and the map
  from route name to module. Unmatched routes are core (on Free too).
- `App\Services\Entitlements` — resolves a company's modules: grandfathered /
  legacy plan / trialing → everything; no live subscription → Free; otherwise
  `plans.modules` + current `subscription_addons`.
- `EnforceModuleAccess` (`module` middleware) on the main app, staff portal,
  label PWA, training portal and both agent APIs. Managers are redirected to
  Billing; staff apps get a 403; agents and the kiosk a JSON 403.
- `SubscriptionService::syncAddons()` — the selling rules (no add-ons on Free,
  cap of two on Basic, nothing Full already includes, metered minimums).
  Admin › Subscriptions uses it until checkout sells add-ons.
- The Free / Basic / Full plans exist but are private (`is_public = false`)
  until checkout can charge per outlet.
