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
Purchasing, Finance, HR Manager, Staff *(assignable)*; **Manager** *(orphan — superseded, never deleted)*.

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

### F8 — the role catalogue has drifted · **Medium**

- `Manager` (from the original 2026-03 migration) is orphaned — superseded by Branch/Operations
  Manager, never deleted, still assignable in the DB.
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
| **2** | Build Settings › Roles & Access. Retire the old modal. | Low | Usability; F11, F12 closed |
| **3** | `company_id` on roles; allow/deny overrides; stop copying role perms into direct. Backfill: derive each user's current direct set into overrides so effective access is byte-identical on deploy. | **Medium** — the backfill is the risky step | F6, F7 closed |
| **4** | Granular abilities module by module, starting Purchasing then HR/Payroll. Everyone holding `x.view` today is granted `view + create + edit`, so **no one loses access on deploy**; admins tighten afterwards. | Medium | F3 closed |
| **5** | Split `settings.view` per area; audit-log permission changes; delete the orphan `Manager` role; populate `display_name` / `description`. | Low | F8, F9, F10 closed |

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
- `model_has_permissions` — add `type` enum(`allow`,`deny`) default `allow`.
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
| **2** | Settings › Roles & Access: the matrix, the user drawer, the effective-access tab. Old modal retired. | Usability; F11, F12 closed |
| **3** | Per-company roles (`team_id`), resolve-by-ID everywhere, allow/deny overrides, stop copying role perms into direct. Backfill preserves effective access exactly. | F6, F7 closed |
| **4** | Granular abilities rolled out module by module — Purchasing first, then HR/Payroll, then Inventory. Everyone holding `x.view` is granted `view+create+edit` so no access is lost. | F3 closed |
| **5** | Settings split per page; audit-log role/permission pivot changes; delete the orphan `Manager` role; populate `display_name`/`description`. | F8, F9, F10 closed |

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
