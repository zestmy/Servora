# Roles & Permissions — Audit and Revamp Proposal

**Date:** 2026-08-08 · **Status:** Proposal, not approved · **Author:** Claude (audit of `main` @ 36667c7)

---

## 1. What exists today

Three separate systems decide what a user can do:

| Layer | Storage | Scope | Edited from |
|---|---|---|---|
| **Spatie permissions** (33) | `permissions`, `role_has_permissions`, `model_has_permissions` | teams mode, `team_id` = active `company_id` | Settings › Users modal (23 of 33 only) |
| **Roles** (12) | `roles`, `model_has_roles` | role definitions are **global**, assignments are per-company | Admin › Role Templates (System Admin only) |
| **Capability flags** (7) | `company_user` pivot + cached columns on `users` | per-company | Settings › Users modal |
| **Scope** | `outlet_user`, `kitchen_users` pivots | per-company | Settings › Users modal |

Enforcement is `can:x` route middleware (175 of 251 route lines), `$user->can()` inside Livewire
components, `@can` in Blade (13 sites), `'permission' =>` keys on 59 nav items, and
`hasCapability()` for the 7 flags.

**Roles in the DB:** Super Admin, System Admin *(system-level, `Gate::before` bypass for Super Admin)*;
Company Admin, Business Manager, Operations Manager, Branch Manager, Outlet Manager, Chef,
Purchasing, Finance, HR Manager, Staff *(assignable)*. Twelve rows, and that is all — an earlier
draft of this line claimed a thirteenth orphaned `Manager` role, which does not exist; see the
correction under F8.

---

## 2. Findings

### F1 — 10 of 33 permissions cannot be granted or revoked from any screen · **Critical**

`Settings\Users::MODULES` is a 23-entry PHP const that acts as the allowlist for *both* the Users
modal and the Role Templates editor (`RoleTemplates::EDITABLE_PERMS = SettingsUsers::MODULES`).
Everything outside it is invisible to both screens.

Unmanageable today:

```
hr.payroll            hr.payroll.approve     hr.leave        hr.leave.approve
hr.compensation.approve   labels.print       labels.manage   labels.view_log
roster.view           users.manage (flag-only, see F5)
```

**Payroll — the most sensitive module in the product — has no grant/revoke path.** The only way to
change who can run or approve a payroll run is to write a migration. Same for leave approval and
the entire label PWA.

### F2 — the permission catalogue lives in two places that drift · **Critical**

`permissions` table = what is enforced. `Settings\Users::MODULES` = what is manageable. Nothing
keeps them in step, and nothing fails when they diverge. Adding a permission today means: write a
migration, edit a Livewire const, and remember that Role Templates reads that same const. F1 is
the accumulated result of that three-step ritual being done partially, four times.

### F3 — `.view` means full write · **High**

There is no read-only anything. `sales.view` grants create, edit and closure. `purchasing.view`
grants PO creation, GRN receipt, invoices, credit notes, stock transfers and consolidation
(19 routes). `inventory.view` grants stock takes, wastage, staff meals, prep items and transfers.

A "Finance" user who exists to *read the numbers* has the same write rights over sales and
purchasing as the clerk entering them.

### F4 — delete is one global switch · **High**

`can_delete_records` is a single boolean, checked in Sales, Purchasing (3 sites incl. PO
rollback), Inventory, HR Clock Events and Overtime Claims. You cannot let a branch manager void a
wastage record without also letting them delete purchase orders and clock-in punches.

### F5 — two parallel authority systems that overlap · **High**

`can_manage_users` writes **both** a pivot flag *and* the `users.manage` Spatie permission
(`Users.php:406`). The route is gated on the permission; the Settings index and
`canBypassLock()` gate on the flag. Two sources of truth for one decision.

Worse, `hasCapability()` reads `$this->{$capability}` — the **cached column on `users`**, not the
pivot. The pivot is documented as the truth; the cache only refreshes on company switch or an
explicit `refreshCapabilityCache()`. Reads and writes travel different paths.

### F6 — assigning a role copies its permissions, so role edits can only ever add · **High**

`syncAccessLevel()` merges the role's permissions into the user's **direct** permissions:

```php
$valid = array_merge($valid, array_intersect($rolePerms, array_keys(self::MODULES)));
$user->syncPermissions(array_values(array_unique($valid)));
```

Remove a module from a role template afterwards and every existing holder keeps it as a direct
grant. The Role Templates screen tells the admin the change *"applies to every user holding this
role, in all companies"* — **for removals that is false.** Only additions propagate.

### F7 — roles are global across all tenants · **Medium-High**

`roles` and `role_has_permissions` have no `company_id`. Editing "Chef" changes it for every
company on the platform simultaneously. Only System Admin can do it, which is why it hasn't
caused an incident — but it also means **no company can define a role that fits its own
structure.** A 3-outlet café and a 40-outlet group get the same ten roles.

### F8 — the role catalogue has drifted · **Medium** — *partly wrong, see correction*

> **Correction (Phase 5).** The first bullet was wrong. I read the `Manager` role out of the
> 2026-03 migration file rather than the `roles` table, and asserted it was still there. It is
> not: neither dev nor production has a role named `Manager` — both hold exactly the 12 listed
> above. It had already been removed. The remaining bullets stand, but they are cosmetic.

- ~~`Manager` (from the original 2026-03 migration) is orphaned — superseded by Branch/Operations
  Manager, never deleted, still assignable in the DB.~~ **Does not exist. Audit error.**
- The leave migration grants `hr.leave` to **"Area Manager"**, a role that does not exist. That
  grant silently no-oped.
- `display_name` and `description` are `NULL` on **all 12 rows**, so the editable-label feature
  the Role Templates screen was built for has never been used; every screen falls back to the
  hardcoded const descriptions.

### F9 — Settings is one permission covering ~40 pages · **Medium**

`settings.view` gates 11 routes directly and ~14 of the ~20 tiles on the settings index. Grant
someone access to maintain tax rates and they also get suppliers, outlets, departments, pay
components, statutory rates, price classes, API keys and report subscriptions.

### F10 — permission changes are not audited · **Medium**

`User::class` is in `config/audit.php`, so name/email and the cached capability *columns* are
logged. But `model_has_roles`, `model_has_permissions` and `role_has_permissions` are pivot
tables with no Eloquent observer — **who granted payroll access, and when, is not recorded
anywhere.**

### F11 — capability flags are suggested by role but never re-synced · **Low**

`ROLE_CAPABILITIES` seeds the checkboxes when a role is picked, then the two drift independently
forever. `updatedAccessRole()` also silently force-enables `can_manage_users` if the role carries
`users.manage`, but never disables it.

### F12 — `updatedAllOutlets()` references a property that does not exist · **Low**

`Users.php:156` reads `$this->allOutlets`; the component declares `$outletMode`. Dead hook.

---

## 3. Proposed model

### 3.1 One registry, three layers

**Registry** — `config/permissions.php` becomes the single source of truth for what abilities
exist. Modules × abilities, not a flat list:

```php
'purchasing' => [
    'label' => 'Purchasing',
    'group' => 'Operations',
    'abilities' => [
        'view'     => 'View orders, requests and suppliers',
        'create'   => 'Raise PO, PR, transfers',
        'edit'     => 'Amend existing documents',
        'delete'   => 'Delete or roll back documents',
        'approve'  => 'Approve purchase orders',
        'request'  => 'Approve purchase requests',
        'receive'  => 'Receive goods (GRN)',
        'invoice'  => 'Supplier invoices and credit notes',
    ],
],
```

Permission name stays `{module}.{ability}` → `purchasing.approve`. **Spatie stays. `can:` middleware
stays. `@can` stays.** Only the granularity and the location of the catalogue change.

**Three layers, cleanly separated:**

1. **Role** — a named bundle of abilities. *Per-company*, editable by the company admin.
2. **Overrides** — per-user `allow` / **`deny`** on top of the role. Deny does not exist today;
   without it "Branch Manager, but not delete" is unexpressible.
3. **Scope** — which outlets and kitchens those abilities apply to. Already exists; promote it to
   a first-class third section instead of being tangled with capability flags.

```
effective = (role abilities ∪ allow-overrides) − deny-overrides, applied within scope
```

### 3.2 Capability flags fold into the registry

| Flag today | Becomes |
|---|---|
| `can_approve_po` | `purchasing.approve` |
| `can_approve_pr` | `purchasing.request` |
| `can_receive_grn` | `purchasing.receive` |
| `can_manage_invoices` | `purchasing.invoice` |
| `can_manage_users` | `users.manage` (already a permission — removes the dual write) |
| `can_delete_records` | **splits** → `sales.delete`, `purchasing.delete`, `inventory.delete`, `hr.clock.delete`, `hr.claims.delete` |
| `can_view_all_outlets` | **stays** — it is data *scope*, not a capability. Moves next to the outlet picker where it belongs. |

Six of seven flags disappear. F5 dissolves; F4 becomes five independent switches.

### 3.3 Per-company roles — **APPROVED 2026-08-08**

**No schema change required.** `roles.team_id` already exists, with a
`roles_team_name_guard_unique (team_id, name, guard_name)` index, and Spatie resolves roles as
`team_id IS NULL OR team_id = getPermissionsTeamId()` (`Models/Role::findByParam`). All 12
current roles have `team_id = NULL`, i.e. they are already global presets.

So the tiering falls out of the existing schema:

| `team_id` | Meaning | Editable by |
|---|---|---|
| `NULL` | System preset — visible to every company | Super Admin only (Admin › Role Templates) |
| `<company_id>` | That company's custom role — invisible to all others | That company's admin |

A company admin can **use** a preset unchanged, **clone** a preset into an editable copy, or
**build** one from scratch. F7 fixed, and every company gets a role catalogue matching its own
structure.

**Footgun this introduces — resolve roles by ID, never by name.** The unique index is on
`(team_id, name, guard_name)`, so a company *may* create its own "Chef" alongside the global
"Chef". `findByParam()` ends in `->first()`, so `findByName('Chef')` would then return an
ordering-dependent row. Two consequences for the build:

1. All role resolution moves to **role ID**. `syncRoles(['Branch Manager'])` in
   `Users::syncAccessLevel()` is name-based and must change.
2. `Settings\Users::rolePermMap()` and `RoleTemplates::assignableRoleRows()` both do
   `DB::table('roles')->whereIn('name', …)` with **no team filter**. Once custom roles exist,
   `rolePermMap()` groups by `role_name` and would silently **merge two different roles'
   permission sets**. Both queries must become team-aware.
3. Either forbid custom roles from taking a preset's name, or accept collisions and rely
   entirely on IDs. *(Recommend: soft-block the name in the UI with "there is already a
   Chef role — call this one something else", and rely on IDs regardless.)*

### 3.4 Stop copying role permissions into direct permissions

Store only genuine overrides, typed allow/deny. Role edits then actually propagate — including
removals — and the users list can honestly render **"Branch Manager · 2 changes"**. F6 fixed.

---

## 4. Proposed UI — Settings › Roles & Access

Simple by default, detailed on demand. Progressive disclosure is what reconciles "simplified,
easy" with "detailed to the extent we can enable/disable parts of a module".

**Tab 1 — Roles.** List with live user counts. Opening one shows a **matrix**: rows = modules
(grouped Operations / People / Reporting / Admin), columns = View · Create · Edit · Delete ·
Approve. Each row header is tri-state (All / Some / None) and collapses the module's
ability-level detail behind a chevron — so the common case is one click per module, and the
detail is one click deeper. Column headers toggle down the column. Search filters rows. A live
"this role can reach N screens" counter under the matrix.

**Tab 2 — Users.** List plus a drawer with three clearly separated sections:

- **Role** — one select. *Ninety percent of admins stop here.*
- **Fine-tuning** — collapsed by default, and when open shows **only deltas from the role**, each
  as a three-way allow / inherit / deny control. Adding or removing one ability is two clicks and
  reads as a sentence.
- **Scope** — outlets, kitchens, all-outlets. Unchanged in behaviour, better placed.

**Tab 3 — Effective access.** Pick a user, see the fully resolved matrix with a *why* on every
cell: `from role` / `added for this user` / `removed for this user` / `blocked — no outlet scope`.
This is the answer to "why can't Ali see payroll?", which today requires a DB query.

---

## 5. Guardrails so F1 and F2 cannot recur

- `php artisan permissions:sync` — reconciles the `permissions` table to the registry.
- **A test that fails the build** when a route's `can:` string, a Blade `@can`, or a nav
  `'permission' =>` key names an ability not in the registry — and when a registry ability is
  never enforced anywhere.
- The nav array declares `module` + `ability` and resolves through the registry, so a nav item
  cannot point at a permission that does not exist.
- Add role/permission pivot changes to the audit log (F10).

---

## 6. Phasing

| Phase | Work | Risk | Outcome |
|---|---|---|---|
| **0** | Registry + `permissions:sync` + drift test. Point the existing UI at the registry instead of the const. | None — no behaviour change | **The 10 orphan permissions become manageable immediately.** F1, F2 closed |
| **1** | Split capability flags into permissions. Dual-read (`can()` OR legacy flag) during transition; migration copies flags → permissions. | Low | F4, F5 closed |
| **2** ✅ | **DONE 2026-08-09.** Settings › Roles & Access — three tabs. Role Guide modal retired. | Low | Usability; access is now explainable |
| **3** | `company_id` on roles; allow/deny overrides; stop copying role perms into direct. Backfill: derive each user's current direct set into overrides so effective access is byte-identical on deploy. | **Medium** — the backfill is the risky step | F6, F7 closed |
| **4** ✅ | **4a+4b+4c DONE (Purchasing, Inventory, HR).** Writes split out of `purchasing.view`, which is now genuinely read-only; 6 new abilities, backfilled so nobody lost access. Sales/Recipes/Ingredients deferred — not F3 examples. | Medium | F3 closed where it mattered |
| **5** ✅ | **DONE 2026-08-10.** `settings.view` split into 10 page abilities and retired; role/permission changes audited; F8 corrected — the `Manager` orphan never existed. | Low | F9, F10 closed; F8 corrected |

Phase 0 alone fixes the two critical findings and is a day's work. Each later phase ships and
deploys independently.

### Phase 0 — what shipped, and the two things it deliberately did not do

Built: `config/permissions.php`, `app/Helpers/PermissionRegistry.php`,
`app/Console/Commands/SyncPermissions.php`, `tests/Feature/PermissionRegistryTest.php`, and both
admin screens repointed off the const and regrouped by module.

**Deployment note: `config/permissions.php` is a config file and production caches config.**
`php artisan config:cache` must run on deploy or the registry will not be seen. The standard
deploy command already does this; a `git pull` alone would not.

Two known consequences to carry into later phases:

1. **F6 now spans 31 abilities rather than 23.** `syncAccessLevel()` still merges a role's
   permissions into the user's *direct* permissions, and that merge is filtered by the grantable
   set — which just grew. So saving a Company Admin now also writes `hr.payroll` as a direct
   grant, where before it stayed role-only. Effective access is unchanged, but it means a
   Role Template that *removes* payroll will not propagate to anyone whose record has been saved
   since. Fixing the merge is Phase 3's job and was left alone here on purpose: Phase 0's
   contract was no behaviour change, and the fix needs Phase 3's backfill designed alongside it.
2. **`roster.view` is still a permission that gates nothing.** Declared `'enforced' => false`
   with a note, excluded from both grids, and preserved untouched by Role Templates — exactly
   its pre-registry handling. Enforcing it is a real behaviour change (Chef, Purchasing, Finance
   and Staff would lose roster visibility) and needs a decision, not a refactor.

### Phase 1 — the dual-read plan was wrong, and what replaced it

This document originally specified `can($perm) || $legacyFlag` during a transition window.
**That is unsafe and was not built.** It traps the revoke: an admin unticks "Approve orders",
the permission is removed, the stale `company_user` flag is still `true`, the `||` still
returns true, and the revoke silently fails — with the UI showing it as revoked.

A full copy has no such window. The flags and Spatie are *both already per-company* (pivot
columns vs. `team_id`), so the migration is a row-for-row copy of `company_user` into
`model_has_permissions` — nothing can be missed, so there is nothing to fall back to.
Permissions became the sole authority in the same deploy.

Verified against the untouched pivot rather than a snapshot: 90 (user × company × ability)
checks, 0 mismatches. Then the case that matters — a user whose legacy `can_approve_po` flag
is still `true`, with the permission revoked through the UI, correctly returns **false**.

**`hasCapability()` → `canDo()`.** Not a rename: `Gate::before` only bypasses for Super Admin,
but `hasCapability()` also returned true for System Admin, whose authority must hold in *every*
company — which a team-scoped role assignment does not give. Dropping call sites to plain
`can()` would have quietly stripped System Admin's rights everywhere. `canDo()` keeps that
short-circuit in one place while putting the real permission name at the call site, where the
drift test can see it.

**The six flag columns are now inert**, deliberately left on `company_user` and `users` so the
migration is reversible. Nothing reads or writes them. Drop them in a later phase, once they
have been unreferenced long enough to be sure.

**`can_view_all_outlets` was not folded in.** It is not a capability — it says *where* a user's
abilities apply, not what they are. It stays a flag and moved to its own "Outlet Scope" section
next to the outlet picker.

### Phase 2 — what shipped, and the column matrix that did not

Settings › Roles & Access is three tabs across two routes, joined by `<x-access-tabs>`:
**Users** (`Settings\Users`, unchanged responsibilities) and **Roles** + **Effective access**
(`Settings\RolesAccess`, new). Splitting them keeps the Users component from passing a thousand
lines; `wire:navigate` makes the seam invisible.

**The V/C/E/D/A column matrix in §4 was not built, because that spine does not exist yet.**
Today's registry has `print`, `receive`, `amend`, `settings`, `log`, `request` — abilities that
do not fit five columns. Rendering fake columns would have meant either inventing empty cells or
hiding real abilities. The Roles tab shows the true grouped ability set per role instead, with a
granted/partial/none state per module and an *n*/41 counter. **The column matrix becomes possible
in Phase 4**, when the spine is actually uniform, and should be built then.

**Fine-tuning is collapsed by default.** Picking a role is the whole job for most people, so the
41-checkbox grid hides behind a one-line summary — "Finance — 4 abilities from this role, 0
granted on top". It auto-opens only when there is no role, or when the person has already been
fine-tuned. The count is recomputed in Alpine from the checkboxes, because `wire:model` here is
deferred and a round-trip per tick would make a 41-box grid crawl.

**Effective access answers the question the old screen could not.** Every ability, with
provenance: `from role` / `added for them` / `system` / `—`. Resolved from `role_has_permissions`
and `model_has_permissions` directly, then cross-checked against `canDo()` — 7 users × 41
abilities, 0 disagreements.

Two things worth knowing:

1. **Role editing is deliberately absent from the tenant screen.** Role rows are still global
   (`team_id` NULL), so a company admin saving "Chef" would change it for every company on the
   platform — F7, open until Phase 3. The tab shows what a role grants and says who can change
   it; editing stays in Admin › Role Templates. **When Phase 3 lands, this tab is where
   per-company role editing belongs.**
### Phase 3a — denials, and role edits that finally reach their holders

Split from 3b (per-company roles) so each half could be verified on its own; this is the
riskier half, because it carries the backfill.

**F6 is closed.** `syncAccessLevel()` no longer merges a role's permissions into the user's
direct grants. It now derives the split from effective access: `granted` = ticked minus what
the role gives, `denied` = the role's abilities that were unticked. The migration deleted the
**45 existing direct grants that merely duplicated their holder's role** (97 rows → 52).

That deletion cannot change anyone's access — the role still grants every one of them, which
is precisely what made them duplicates — and it was verified that way: effective access for
every (user, company) was dumped to JSON before and after, and the two files are identical.
What it changes is where a grant *comes from*, so a role edit now reaches its holders.
Demonstrated: removing `reports.view` from the Finance role flips its holder's `canDo()` to
false, where before Phase 3 they kept a private copy.

**Denials could not be implemented in `Gate::before`, which is what the plan assumed.**
Spatie registers its own `$gate->before()` that returns `true` the moment the user holds the
permission, and Laravel takes the **first non-null** before-callback — so whichever provider
registers first wins, and ours lost. `Gate::after` is no escape either: it merges with
`$result ??= $afterResult`, so it cannot overturn a `true`.

The seam that does work is Spatie's own `checkPermissionTo()`, aliased out of the trait and
wrapped on the User model. Returning false there turns Spatie's `?: null` into `null`, the
Gate falls through to normal resolution, and the ability is denied — through `can:` route
middleware, `@can` and `can()` alike, with no call site needing to know. Verified across all
three paths. Denials do **not** clip Super Admin or System Admin: they are a company-level
instrument, not a way to trim a platform account.

`deniedPermissions()` is memoised per request per team and guards on `Schema::hasTable`,
because it is consulted on every gate check — a missing table there would not break one
feature, it would 500 every page in the app.

**The regression test now exists** — `tests/Feature/PermissionDenialTest.php`, 9 tests. Getting
there meant fixing the suite first; see "The test suite could not build a database" below.

It covers: a denial beating a role grant on all three check paths (`canDo()`, `can()`,
`Gate::allows`), beating a direct grant, staying surgical (the rest of the role survives, and
the user keeps the role), lifting cleanly, applying only to the company it was made in, *not*
clipping a system account, and — the integration-level proof — `can:` route middleware
returning 403.

The ninth is a reflection assertion that `User::checkPermissionTo()` is still overridden,
because that is the load-bearing seam and its connection to the feature is not obvious.

**Negative-controlled.** Neutering the wrapper the way a "simplification" would fails **6 of
the 9**. The three that survive are correct to: one involves no denial, one is the
system-account short-circuit, and the reflection test guards the method's *existence* rather
than its body. Behaviour and seam are covered separately, on purpose.

### Phase 3b — per-company roles

**No schema change was needed**, as §3.3 predicted: `roles.team_id` already existed with a
`(team_id, name, guard_name)` unique index, and Spatie resolves
`team_id IS NULL OR team_id = current` natively. A company role is simply a row with
`team_id` set.

| `team_id` | Meaning | Editable by |
|---|---|---|
| `NULL` | System preset, shared by every company | Servora system admin, in Admin › Role Templates |
| `<company_id>` | That company's own role, invisible to others | That company's admins, in Settings › Roles |

Company admins get **New role**, **Copy & edit** on a preset (clones it rather than touching
the shared row), **Edit** and **Delete** on their own.

**The footgun §3.3 warned about was real and is now closed.** `rolePermMap()` grouped by role
*name*; with a company free to create its own "Chef" beside the preset, that would have
**merged two different roles' permission sets into one**. Everything now keys on role ID:
`RoleCatalogue` is ID-keyed throughout, `$accessRole` holds an ID, and `syncRoles()` is passed
a `Role` model rather than a name string (`Role::findByName()` ends in `->first()` and cannot
tell the two apart). Verified by deliberately creating a colliding "Chef": the two roles kept
separate ability sets, and the user was assigned the right one by ID.

Guards were tested against a **forged Livewire payload**, not just the UI path — forcing
`editingRoleId` to a preset's ID leaves its abilities and display name untouched, and
`deleteRole` on a preset refuses. Deleting a role that anyone still holds is refused too:
dropping the assignment silently would strip access with no trace of why.

`RoleTemplates` (the Servora-admin screen) is now filtered to `team_id IS NULL`. Without that
it would have listed — and let a platform admin silently rewrite — roles that individual
companies created for themselves.

**Cross-company isolation is verified.** The dev database has one company, so it was exercised
by creating a second tenant in a rolled-back transaction and giving *both* a role named `chef`
— which the `(team_id, name, guard_name)` index permits. Each company sees its own and not the
other's, and company 1 correctly sees **two** roles called "chef" with distinct IDs: the global
preset and its own. That is precisely the case that would have collapsed under name-keying.

Production carries 5 companies and 0 company-owned roles so far, so nothing has leaked there
either. The Phase 3a backfill dropped **96 duplicated direct grants** on production (versus 45
on dev), with no 5xx outside the deploy's own maintenance window.

### Phase 4a — Purchasing: read stops meaning write

F3's worst case was Purchasing: `purchasing.view` gated **19 routes**, covering raising
purchase orders and requests, converting and consolidating them, creating stock transfers,
and maintaining suppliers, price alerts and order form templates. There was no way to let
someone read the numbers without letting them commit the company to spending money.

**The split is additive, not a rename.** `purchasing.view` keeps its name and becomes
genuinely read-only. Renaming a permission held broadly in production is a grant migration
with nothing to gain, and §8's plan to split *view* per document type turned out not to be
enforceable anyway: the Purchasing index is a **single tabbed Livewire component** rendering
orders, requests, GRNs and invoices together, so "read orders but not invoices" needs that
component split first. View stays module-level; the **writes** split, which is where F3 bit.

Six new abilities: `orders.create`, `orders.edit`, `requests.create`, `requests.edit`,
`transfers.create`, `suppliers.manage` — alongside Phase 1's `approve`, `request`, `receive`,
`invoice` and `delete`.

**Enforced twice, on purpose.** Routes carry the new abilities, and the three write forms
(`OrderForm`, `PurchaseRequestForm`, `StockTransferForm`) re-check in `save()`. A Livewire
action is its own request to `/livewire/update`, so trusting how the component was first
loaded is not the same as authorising the write.

**Backfill preserved access exactly**: every role and user holding `purchasing.view` received
all six write abilities, because that is precisely what `purchasing.view` already let them do.
Verified as an invariant — *has a new write ability* ⟺ *had `purchasing.view`* — across every
(user, company), 0 mismatches, with pre-existing abilities byte-identical once the six new
ones are set aside.

Then the point of the whole exercise, demonstrated: denying just "Raise orders" leaves
`purchasing.view` **true** and `orders.edit` **true**, while `orders.create` goes false and
`OrderForm::save()` returns 403. Read-only purchasing access now exists.

### Phase 4b — Inventory, and four destructive actions that were never gated at all

Same additive shape as Purchasing, but Inventory splits more cleanly: every document type
already has its **own form component** and its **own delete method**, so recording and
deleting both land on real seams. `inventory.view` stays module-level read (one screen lists
every type); recording splits six ways; deleting splits six ways.

**A hole the original audit missed.** `inventory.delete` existed, but only
`deleteStockTake()` and `deleteTransfer()` ever consulted it. `deleteWastage()`,
`deleteStaffMeal()`, `deletePurchase()` and `deletePrepItem()` had **no permission check
whatsoever** — anyone who could open Inventory could delete those records outright, and the
buttons rendered for everybody. All six are guarded now, and the view hides what the user
cannot do.

The backfill had to mirror **two different realities** to preserve access exactly:

| Held today | Receives | Because |
|---|---|---|
| `inventory.view` | all six `*.record` | that is what view already allowed |
| `inventory.view` | wastage / staff_meals / purchases / prep_items `*.delete` | those deletes were **ungated**, so every viewer could already do them |
| `inventory.delete` | stock_takes / transfers `*.delete` | the only two it actually gated |

Verified against the grant rows: the two formerly-gated deletes went to exactly the 4 users
holding `inventory.delete`, and the four formerly-ungated ones mirror `inventory.view`'s 7
role grants precisely. `inventory.delete` is retired.

> **Worth a deliberate decision:** preserving access exactly means the four previously-ungated
> deletes are now granted to every `inventory.view` holder. That is the agreed posture and
> nobody lost anything — but it carries a pre-existing hole forward. The difference is that it
> is now **visible and revocable** on the Roles tab, which it was not before. Tightening
> "Wastage — delete" and friends is a one-click change per role.

**The drift test earned its keep twice here.** `canDeleteType()` was first written as
`canDo("inventory.{$type}.delete")` — the names never appear as literals, so the guardrail
could not see them and failed the build. Rewritten so each `match` arm calls `canDo()` with a
literal. A permission name assembled at runtime is a permission the drift check cannot
protect.

It also gained a **third check**: `test_every_granted_permission_name_exists` scans
`givePermissionTo()` / `syncPermissions()` arrays for names not in the registry. Retiring
`inventory.delete` left three of them still granting it — in company registration and
onboarding — where Spatie would have thrown `PermissionDoesNotExist` for the next customer to
sign up.

**A Phase 1 miss, found in passing:** `CompanyRegistrationService::registerAdditionalCompany()`
still wrote the capability flags Phase 1 made inert and never received the replacement
permissions, so anyone creating a *second* company landed there without approve or delete
rights. Fixed.

### Phase 4c — HR: the two permissions earlier phases had left coarse

Most of HR was already split by earlier work — payroll, leave and compensation each carry
their own approve ability, clock has manage and delete, documents have view and manage. Two
were still doing double duty.

**`hr.view` gated creating, editing *and* deleting employee records** as well as reading the
list. Those records carry IC numbers, bank details and the link to payroll. And
`Employees::delete()` checked only that the employee sat in an outlet the user could reach —
**no permission check on the deletion at all**, the same shape as the four ungated inventory
deletes found in 4b.

**`hr.attendance` gated editing the attendance grid** — marking present or absent, filling
and clearing ranges, reordering, and managing attendance codes — behind what reads as a read
permission. Attendance feeds payroll, so those are financially material writes.

Three new abilities: `hr.employees.manage`, `hr.employees.delete`, `hr.attendance.record`.
Seven attendance write actions and both employee write paths are now guarded in the component
as well as on the route.

Service charge was deliberately **not** touched: `saveServiceCharge()` already aborts unless
the user passes `Employee::canViewPay()`, which is the stricter compensation gate.

Backfill preserved access exactly — `hr.view` holders received manage and delete,
`hr.attendance` holders received record. Verified across every (user, company): 0 mismatches,
nothing pre-existing lost. Unlike 4a, the dev data had real variation here (3 holders of
`hr.view` against 6 without), so the invariant was exercised in both directions locally.

Demonstrated: denying "Delete staff" and "Edit attendance" leaves `hr.view`,
`hr.employees.manage` and `hr.attendance` **true**, while `Employees::delete()` returns 403
and the row survives, `setCell()` and `fillPresent()` return 403, and the attendance screen
still renders.

**Phase 4 is now complete for Purchasing, Inventory and HR.** Sales, Recipes and Ingredients
still have `.view` meaning write; they were never the F3 examples and none of their writes are
financially material in the way a purchase order, a stock adjustment or an attendance mark is,
so they are left for a later pass rather than done reflexively.

### Phase 5 — the settings split, and an answer to "who granted this?"

**F9 closed.** `settings.view` was one switch over ten unrelated screens: branches, outlet
groups, tax rates, sections, certifications, OT approvers, PO approvers, departments, central
purchasing and central kitchens. One ability per page now, as agreed. Pages belonging to
another module were already gated by it — pay components and banks on `hr.compensation`, leave
types on `hr.leave.approve`, clock settings on `hr.clock.manage`, scheduled reports on
`reports.view`, suppliers and form templates on `purchasing.suppliers.manage` — and were
deliberately not duplicated.

**`settings.view` is retired, not kept as an umbrella.** Once the ten pages carry their own
abilities it gates nothing: the Settings index has no route middleware (it is a list of links,
each tile carrying its own permission), and the three sidebar links into it now name the
abilities of the group they open. A permission that gates nothing is one that quietly rots.
Backfill mirrored it onto all ten pages first — 0 mismatches, nothing else lost.

Two things surfaced while doing it. The `$hasSettingsAccess` parameter threaded into
`companyGroups()` was already dead — the early return it fed had been removed earlier — so
`settings.view` was **already** vestigial in the index and only the route gates kept it alive.
And Phase 4a had left a **nav/route mismatch**: Product Mapping, Form Templates and Price
Alerts still advertised themselves on `purchasing.view` while their routes had moved to
`purchasing.suppliers.manage`, so the links rendered and then 403'd. Both fixed.

**F10 closed.** Role and permission changes are now audited. The pivot tables
(`model_has_roles`, `model_has_permissions`, `permission_denials`, `role_has_permissions`) have
no Eloquent observer, so the existing audit observer never saw them — who gained payroll access
and when was recorded nowhere. Four write points now log through `AuditLogService`:

| Event | Where | Records |
|---|---|---|
| `access_changed` | `Settings\Users::syncAccessLevel()` | role, granted and denied, before and after |
| `role_created` / `role_updated` | `RolesAccess::saveRole()` | name and ability set |
| `role_deleted` | `RolesAccess::deleteRole()` | what the role was, before it went |
| `role_template_updated` | `Admin\RoleTemplates::save()` | a preset change, which touches every company |

The snapshot is read from the grant tables rather than through `can()`, so the entry records
stored state rather than a resolved answer, and it only writes when something actually moved —
re-saving a user with no change produces no entry. Verified: adding `hr.payroll` to someone
whose role lacks it logs `granted: 4 → 5, added: hr.payroll`.

**F8 was mostly an audit error — see the correction on the finding.** The `Manager` orphan does
not exist. The "Area Manager" grant that no-oped is historical and has nothing to fix.
`display_name` is genuinely NULL on all 12 presets, but every screen resolves
`display_name ?: name`, so it is cosmetic and was left alone rather than filled with the same
strings the fallback already produces.

2. **The Roles tab makes visible that roles are now thin on the Phase 1 abilities.** Company
   Admin reads "Purchasing 1/6", because Phase 1 deliberately granted the ex-capability
   abilities per user rather than onto roles (preserve-exactly). That is correct, but it means
   a *newly* created Company Admin gets fewer purchasing rights than an existing one unless the
   admin ticks them. `ROLE_SUGGESTED_ABILITIES` covers the Settings › Users path; adding them to
   the role templates properly is a judgement call for the account owner, and the tab now makes
   the gap visible enough to make it.

---

## 7. Decisions — all settled 2026-08-08

1. ~~**Per-company custom roles**~~ — **DECIDED 2026-08-08: allow custom roles.** Implemented via
   the existing `roles.team_id` (NULL = preset, company_id = custom). See §3.3 for the
   resolve-by-ID requirement this creates.
2. **Deny overrides** — **DECIDED: yes, allow / inherit / deny.** `model_has_permissions` gains a
   `type` column (`allow` | `deny`). Resolver: `(role ∪ allow) − deny`. Deny always wins, and a
   deny is never written for an ability the role does not grant (that is just "inherit").
3. **Granularity** — **DECIDED: full View/Create/Edit/Delete/Approve spine on every module.**
   See §8 for the resulting registry and the honest permission count.
4. **Settings split** — **DECIDED: one ability per settings page (~20).** Settings screens are
   inherently edit surfaces, so each is a single `settings.<page>` ability rather than a
   view/edit pair.
5. **Backfill** — **DECIDED: preserve today's effective access exactly**, then ship a "review
   this role" screen so admins tighten deliberately rather than by surprise. No user's access
   changes on deploy day.

---

## 8. The registry, grounded in real actions

Ability names below were derived from the **actual public action methods** in each Livewire
component, not from a generic CRUD template. Two consequences of decision 3 worth stating
plainly before it is built:

**(a) Not every cell in the spine is meaningful.** Reports and Audit have no create, edit or
delete — they are read-and-export surfaces. Ingredients have no approval step. Inventing
`reports.delete` to fill a column would mean seeding, testing and explaining a permission that
gates nothing, and would put a dead checkbox in the matrix. **Proposal: render the spine on
every module, but show a `—` (not an unchecked box) where the module has no such action.** The
grid stays visually uniform; the registry stays honest.

**(b) Real actions exist outside the spine.** `receive`, `submit`, `rollback`, `settle`, `close`,
`print`, `enrol`, `import`, `export`. These become module-specific extras rendered after the
five spine columns.

### Draft registry

| Group | Module | Spine (V·C·E·D·A) | Extras | n |
|---|---|---|---|---|
| Ops | `ingredients` | V C E D — | import, export | 6 |
| Ops | `recipes` | V C E D — | import, export, price | 7 |
| Ops | `purchasing.orders` | V C E D **A** | submit, reject, rollback | 8 |
| Ops | `purchasing.requests` | V C E D **A** | submit | 6 |
| Ops | `purchasing.receiving` | V — E D — | receive | 4 |
| Ops | `purchasing.invoices` | V C E D — | cancel, payment | 6 |
| Ops | `purchasing.credit_notes` | V C E D — | — | 4 |
| Ops | `purchasing.suppliers` | V C E D — | — | 4 |
| Ops | `sales` | V C E D — | close, import, export | 7 |
| Ops | `inventory.stock_takes` | V C E D — | — | 4 |
| Ops | `inventory.wastage` | V C E D — | — | 4 |
| Ops | `inventory.transfers` | V C E D — | receive | 5 |
| Ops | `inventory.staff_meals` | V C E D — | — | 4 |
| Ops | `inventory.prep_items` | V C E D — | — | 4 |
| Ops | `inventory.purchases` | V C E D — | — | 4 |
| Ops | `kitchen` | V C E D **A** | produce | 6 |
| Ops | `labels` | V C E D — | print, log, manage | 7 |
| People | `hr.employees` | V C E D — | export | 5 |
| People | `hr.compensation` | V C E D **A** | — | 5 |
| People | `hr.attendance` | V C E D — | — | 4 |
| People | `hr.claims` | V C E D **A** | settle | 6 |
| People | `hr.clock` | V — E D — | enrol, manage | 5 |
| People | `hr.leave` | V C E D **A** | — | 5 |
| People | `hr.payroll` | V C E D **A** | payslip, export | 7 |
| People | `hr.documents` | V C E D — | — | 4 |
| People | `roster` | V C E D **A** | submit, amend | 7 |
| People | `staff.pins` | V — E — — | — | 2 |
| Insight | `reports` | V — — — — | export | 2 |
| Insight | `audit` | V — — — — | export | 2 |
| Admin | `users` | V C E D — | — | 4 |
| Admin | `roles` | V C E D — | — | 4 |
| Admin | `settings.*` | one ability per page | — | ~20 |

**Total ≈ 172 permissions**, against the ~120 estimated when the decision was framed. The gap is
not the ability spine — it is the **module list**: Purchasing splits into 5 document types and
Inventory into 6, because a GRN clerk and a PO approver are genuinely different people. Kept at
top level (`purchasing`, `inventory` as single modules) the total is ≈ 90.

Both are workable; 172 is the more precise system and the denser screen. **This is the one thing
left to confirm before Phase 0** — see §10.

### Storage

- `permissions` — seeded from the registry by `permissions:sync`. Add nullable `module`,
  `ability`, `label`, `group`, `sort` columns so the matrix renders from the table, not a const.
- ~~`model_has_permissions` — add `type` enum(`allow`,`deny`)~~ **Wrong — do not do this.**
  Spatie's `HasPermissions::permissions()` filters on `team_id` and nothing else, so a row
  marked `deny` in that table is read straight back as a **grant**. Phase 3a uses a separate
  `permission_denials` table instead.
- `roles` — no change (`team_id` already present, see §3.3).
- No change to `role_has_permissions`, `model_has_roles`, `outlet_user`, `kitchen_users`.

---

## 9. Revised phasing

Decisions 1–5 change the order slightly: deny overrides and per-company roles both land in
Phase 3, and the settings split moves earlier because per-page abilities are needed before the
matrix is designed around them.

| Phase | Work | Ships |
|---|---|---|
| **0** ✅ | **DONE 2026-08-08.** Registry (`config/permissions.php`), `PermissionRegistry`, `permissions:sync`, `PermissionRegistryTest`, both admin screens repointed and regrouped. | **8 orphan permissions became grantable, incl. payroll and leave.** 23 → 31 abilities in the grid; `users.manage` and `roster.view` deliberately excluded (see below). No behaviour change, no migration. F1, F2 closed |
| **1** ✅ | **DONE 2026-08-08.** Six capability flags → permissions; `can_delete_records` split into five. `hasCapability()` replaced by `canDo()`. Migration copies the pivot. **NOT dual-read** — see below. | 41 grantable abilities; delete is per-module. F4, F5, F11, F12 closed |
| **2** ✅ | **DONE 2026-08-09.** Three tabs: Users / Roles / Effective access. Role Guide retired; fine-tuning collapsed to a delta summary. Column matrix deferred to Phase 4 — see below. | Usability; "why can they see payroll?" answerable without a query |
| **3** ✅ | **DONE 2026-08-09**, split 3a/3b. Denials via their own table + `checkPermissionTo` (NOT `Gate::before` — see below); stopped copying role perms into direct, 45 duplicates dropped; per-company roles via the existing `roles.team_id`; resolve-by-ID everywhere. | F6, F7 closed |
| **4** ✅ | **4a+4b+4c DONE (Purchasing, Inventory, HR)** — see below. Sales/Recipes/Ingredients deferred — not F3 examples. | F3 closed where it mattered |
| **5** ✅ | **DONE 2026-08-10.** `settings.view` split into 10 page abilities and retired; role/permission changes audited; F8 corrected — the `Manager` orphan never existed. | Low | F9, F10 closed; F8 corrected |

---

## 10. Confirm before Phase 0

**One open question: how finely to cut the module list.**

- **Sub-module (~172 permissions)** — Purchasing splits into orders / requests / receiving /
  invoices / credit notes / suppliers; Inventory into stock takes / wastage / transfers / staff
  meals / prep items / purchases. Matches how the work is really divided: a GRN clerk, a PO
  approver and an invoice processor are three different people. Denser matrix — needs the
  module-group accordion in the UI to stay readable.
- **Top-level (~90 permissions)** — `purchasing` and `inventory` stay single modules with one
  V/C/E/D/A row each. Half the permissions, a matrix that fits one screen, but you cannot let
  someone receive goods without also letting them raise and approve purchase orders — which is
  finding F3 reappearing one level up.

Everything else is settled and Phase 0 is ready to start on approval.

---

## 11. The test suite could not build a database — and neither could a fresh install

Every phase above was verified with scratch scripts against the dev database, because the
test suite had 25 failures that all shared one cause: migrations written in MySQL-only SQL,
which the SQLite connection in `phpunit.xml` could not execute. Tests died while building
their schema, before reaching an assertion.

Nine migrations were made driver-agnostic — not guarded behind `if (driver === 'mysql')`,
which would have left them silently *wrong* on SQLite, but rewritten to say the same thing in
portable form:

| Construct | Was | Now |
|---|---|---|
| `ALTER TABLE … MODIFY COLUMN … ENUM` | raw SQL, MySQL-only | `$table->enum(...)->change()` — ENUM on MySQL, CHECK-constrained varchar on SQLite |
| `MODIFY … NULL / NOT NULL` | raw SQL | `->nullable()->change()` |
| `information_schema.statistics` | index-existence probe | `Schema::hasIndex()` |
| `information_schema.TABLE_CONSTRAINTS` | FK-existence probe | `Schema::getForeignKeys()` |
| `SHOW INDEX FROM` | index-existence probe | `Schema::hasIndex()` |
| `UPDATE a JOIN b SET …` (×3) | MySQL multi-table update | correlated subquery / chunked builder update |
| `NOW()`, `LAST_DAY()`, `GREATEST()` | MySQL functions | PHP-side values and `CASE WHEN` |
| joined `UPDATE` with a `DB::raw` from the joined table | worked only on MySQL | correlated subquery |

### The finding underneath it

`2026_07_23_000002_add_teams_to_permission_tables` added `roles.team_id` and the pivot
`team_id` columns unconditionally. But `permission.teams` is **now true**, and Spatie's own
`create_permission_tables` migration already builds that shape when it is. So on any database
created from scratch the migration hit **"duplicate column name: team_id"** — *on MySQL as
much as SQLite*.

**Servora could not be installed from scratch.** Production has never noticed because its
database predates the teams flip and the migration has long since run there.

It is now idempotent, and takes a different path when Spatie has already built the teams
shape: that shape has `team_id` **NOT NULL and inside the primary key**, whereas this app
needs it **nullable with no PK**, because system accounts hold team-NULL assignment rows that
`isSystemRole()` reads team-agnostically. A primary key cannot contain a nullable column, and
SQLite cannot drop a PK in place, so on that path the pivot is rebuilt into the target shape —
guarded to refuse outright if the table holds any rows, since it is only ever correct on a
fresh database.

### Two stale skeleton tests

Both predated the app they were testing:

- `ExampleTest` had `use RefreshDatabase` **commented out**, so it asserted 200 from the
  marketing home page against a database with no tables. The page reads `plans`; it 500'd.
- `AuthenticationTest::test_navigation_menu_can_be_rendered` built a user with no company and
  asserted 200 from `/dashboard`, which sits behind `company.scope` — that has redirected a
  companyless user since multi-tenancy landed. It also asserted a Volt component
  (`layout.navigation`) that the dashboard does not render; the product uses its own sidebar.

**Result: 25 failed / 91 passed → 125 passed, 0 failed.** Future phases can be verified by
tests rather than by scratch scripts against dev.
