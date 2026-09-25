# Outlet Audits module

Scored checklist audits of an outlet — ROSE (Restaurant Operations System
Evaluation), halal, pre-opening — conducted on a phone or tablet, with
non-conformances tracked to corrective actions by outlet and owner.

**Not the activity trail.** `audit_logs`, `audit.view`, `config/audit.php` and
`App\Livewire\Audit\Index` are the record of who changed what. Everything in
this module is plural: `audits`, `audits.*`, `App\Livewire\Audits`.

## The shape

| Layer | Tables | What it is |
|---|---|---|
| Form | `audit_templates` → `audit_template_sections` → `audit_template_items` | The definition. Items are a two-level tree (heading → lettered sub-items); points live on leaves. |
| Audit | `audits` → `audit_sections` → `audit_lines` | A **copy** of the form taken when the audit starts, plus what the auditor found. The form is never read again. |
| Findings | `audit_findings` → `audit_finding_photos` | One per line marked NC. Created the moment the line is tapped, while still a draft, so photos have something to attach to; deleted if the tap is undone. |
| Actions | `corrective_actions` | What the outlet is doing about a finding and who owns it (an `Employee`). |

Findings and actions carry their own `company_id` / `outlet_id` so the
Corrective Actions summary never joins through audit lines.

### Item types

- `check` — OK / NC / N/A, worth `points`.
- `product` — a slot the auditor names on the day ("Toast 1: Kaya & butter"),
  scored through its child criteria. Findings under it are labelled with the
  product.
- `info` — an unscored number, time or note.

### Section scoring modes

- `area` — points pool into the audit total. Bar, Kitchen, Service…
- `penalty` — the section's lost points are subtracted from the total **after**
  the areas are pooled; its points are never in the pool. This is the "Main
  food safety / halal non-compliances" block on a ROSE form: critical items
  whose failure costs the whole audit. Any NC here is a **major** finding.

### The arithmetic (`AuditScoreService`)

Per section: `available = total − N/A`, `score = available − lost`,
`percent = score / available`. For the audit: sum the area sections'
`available` and `lost`, subtract the penalty sections' `lost` once more, and
divide. Cached on `audits` and `audit_sections`; nothing else writes those
columns.

## Status flow

```
draft ──submit──► submitted ──acknowledge──► acknowledged ──all actions verified──► closed
  ▲                                                                                   │
  └──────────────────────── reopen (audits.reopen; clears the sign-off) ◄─────────────┘
```

- **Submit** refuses while any scorable leaf has no result. "Mark the rest OK"
  on each section is the intended way through a 300-line form: auditors tick
  failures, not passes.
- **Acknowledge**: name, position and a drawn signature (canvas → PNG on the
  `local` disk, served through `audits.signature`).
- **Close** happens by itself when every finding is resolved and the audit is
  acknowledged (or the form does not require acknowledgement). It reverses if
  an action is un-verified.

Corrective action: `open → in_progress → done → verified`. `done` is the
outlet's claim, `verified` the auditor's; a finding resolves only when it has
at least one action and all of them are verified.

## Screens

| Route | Component | Gate |
|---|---|---|
| `/audits` | `Audits\Index` | `audits.view` |
| `/audits/start` | `Audits\Start` | `audits.conduct` |
| `/audits/{id}` | `Audits\Conduct` — the checklist while a draft, the record after | `audits.view` (every write re-checks `audits.conduct`) |
| `/audits/{id}/report` | `AuditReportController` — synchronous dompdf, finding photos as 160px thumbs | `audits.view` |
| `/audits/actions` | `Audits\Actions` — NC summary by outlet → owner designation | `audits.view` |
| `/audits/templates` | `Audits\Templates` | `audits.manage` |
| `/audits/templates/{id}` | `Audits\TemplateEdit` — the builder, one section at a time | `audits.manage` |
| `/audits/schedules` | `Audits\Schedules` — which outlet is due which form, and when | `audits.view` (edits `audits.manage`) |
| `/reports/audit-trend` | `Reports\Audits\AuditTrend` — score over time per outlet, per section, most-failed items, CSV | `reports.view` + `audits.view` |
| `/staff/actions` | `Staff\CorrectiveActions` — "Audit fixes" on the PIN-session Staff Portal | staff session |

`Audits\FindingActions` is nested under each finding on the summary.

The conduct screen hydrates only the current section's lines and saves on
every tap. It is used with a tablet in one hand: 44px targets, tap-again to
undo, a camera button on each NC (`capture="environment"`, six photos per
finding, compressed through `ImageStorageService`).

## Abilities

`audits.view`, `audits.conduct`, `audits.manage`, `audits.actions.manage`,
`audits.reopen`, `audits.delete` — registered in `config/permissions.php`,
backfilled by `2026_09_26_000004_add_audits_module_permissions` from the
nearest stock-take / training / reporting abilities, granted to founders in
`CompanyRegistrationService`.

## The ROSE starter

`App\Support\Audits\RoseTemplate::install()` — five sections, ~300 bilingual
items transcribed from a printed ROSE form with brand-specific product names
made generic. Installed on demand from Audit Forms; a company edits it as its
own. Section totals: 140 (penalty), 282, 248, 157, 20.

## Phase 3: schedules, trend, staff portal

- **`audit_schedules`**: one row per (form, outlet) that recurs, with a
  frequency and `next_due_on`. Starting an audit from the row (Schedule ▸
  Start now, or `/audits/start?schedule=`) stamps `audits.audit_schedule_id`
  and rolls the due date forward **from the due date, not from today**, and
  keeps rolling until it lands in the future. Changing the form or outlet on
  the Start screen makes it an ad-hoc audit and leaves the plan alone. The
  Audits list carries a due strip (overdue / due within 14 days). Nothing is
  emailed: there is no user notification channel in the product.
- **Trend report** reads only the cached `score_percent` columns and the
  findings table, never the lines, and excludes drafts. Month bucketing is done
  in PHP so it runs on SQLite in tests.
- **Staff Portal "Audit fixes"** lists the actions owned by the signed-in
  employee (and the rest of the outlet's for context). They can mark theirs
  in progress or done with a note and a photo of the fix. **Verify is not
  offered there** — the two-step close exists so the person who did the work
  is not the person who signs it off.
- **The PDF prints only the findings.** Page one is facts, total score,
  section table and sign-off; the following pages are each NC with its
  photos and actions. Passed and N/A lines are on screen, not on paper.

## Decisions

- **Copy, don't reference.** Editing a form must never change a score already
  given or a report opened two years later. `audits.template_version` exists
  for filtering only.
- **Sync PDF, not the queued builder.** One audit with a few dozen 160px thumbs
  sits well inside the 256M / 60s php-fpm limits; the SOP handbook needed the
  queue because it is 200 recipes at full resolution. If audits outgrow this,
  `AuditReportController` is the only file that moves.
- **Online required while conducting.** Every tap is a request, so a dropped
  connection loses one tap, not an hour. True offline capture is a different
  architecture (local store + sync) and is not built.
- **Owners are Employees, not Users.** The summary groups by designation
  (Manager, Chef, Shift Officer), which is an HR fact. A later phase can let
  the owner update their actions from the staff portal on the PIN session.

## Tests

`tests/Feature/AuditPhase3Test.php` — schedules (roll-forward rules, ad-hoc
guard, due strip, paused rows), trend report (averages, most-failed, gate),
staff portal (own vs others, done with note and photo, no verify).

`tests/Feature/AuditModuleTest.php` — 17 tests: ROSE install, builder,
snapshot-on-start, the arithmetic incl. N/A and penalty, NC ↔ finding ↔ photo
lifecycle, product-slot labelling, submit guard, reopen, acknowledgement and
closure, action ownership, the summary grouping, permissions, PDF, tenancy.
