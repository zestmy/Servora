# Servora — Developer Docs

A structured, navigable reference for the Servora codebase. Pair with [../README.md](../README.md) (project overview, install, deploy) — these docs are the developer's map.

## Quick nav

| # | Doc | Read this when… |
|---|-----|------------------|
| 01 | [Architecture](01-architecture.md) | …you need to understand tenancy, middleware, guards, roles, layouts. |
| 02 | [Domain model](02-domain-model.md) | …you need to find a model, its relationships, or its global scopes. |
| 03 | [Livewire modules](03-modules.md) | …you know *what* the user does and need the component + route for it. |
| 04 | [Services](04-services.md) | …you're about to write business logic — check if a service already does it. |
| 05 | [Workflows](05-workflows.md) | …you need to trace an end-to-end flow (PO → GRN → Invoice, sales, P&L, etc.). |
| 06 | [Routes & controllers](06-routes.md) | …you need a specific URL, controller, or middleware stack. |
| 07 | [Database schema](07-database.md) | …you're writing a migration or looking up when a column was added. |
| 08 | [Feature playbook](08-feature-playbook.md) | …you want a step-by-step for common additions (new entity, new report, new toggle, new role). |

---

## How these docs are maintained

- Paths are clickable relative links — stable as long as files aren't renamed.
- When you move or rename a file, `grep docs/` for references before committing.
- When you introduce a new subsystem, drop a short section in the matching doc. Don't create a new doc unless the topic is ≥ 200 lines.
- When a company-level toggle, status value, or service method is added/removed, update [05-workflows.md](05-workflows.md) and [04-services.md](04-services.md).

---

## Conventions (quick reference)

- **Multi-tenancy** — `CompanyScope` global scope on every tenant model. See [01-architecture.md](01-architecture.md#multi-tenancy).
- **Outlet scoping** — `ScopesToActiveOutlet` trait in Livewire components; active outlet in session.
- **Status** — string columns, not DB enums. Canonical values per entity live in [05-workflows.md](05-workflows.md).
- **Auto numbers** — `PO-YYYYMMDD-NNN`, `PR-`, `DO-`, `GRN-`, `STO-`, `PROD-`, `QTN-`.
- **Money precision** — `decimal(12,2)` totals, `decimal(12,4)` unit costs.
- **Names uppercased** — `Ingredient.name` and `Recipe.name` uppercased in `saving()` hook.
- **Services own side-effects** — audit logs, emails, PDF generation, external API calls.

---

## When you're new

Read in order:
1. [../README.md](../README.md) — 10-minute overview of features and tech.
2. [01-architecture.md](01-architecture.md) — 15 min, tenancy + middleware + guards.
3. [05-workflows.md](05-workflows.md) — skim the procurement + cost workflows.
4. Pick a module from [03-modules.md](03-modules.md) and read its key Livewire component.
5. Open [08-feature-playbook.md](08-feature-playbook.md) when you start your first PR.
