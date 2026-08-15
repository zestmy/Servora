# Servora Print Agent — Plan

> Status: **proposal**. Nothing here is built. Drafted 2026-08-15 to answer one
> question: what replaces PrintNode so tenants stop paying its per-computer
> subscription? Companion to [label-printing-plan.md](label-printing-plan.md), which
> owns the label domain; this doc owns one transport and the shippable artifact
> behind it.

PrintNode exists in the codebase for exactly one scenario: staff on a phone or
tablet → server → *something on the outlet PC* → the label printer. The "something"
is PrintNode's client app plus their cloud relay, billed monthly per computer.
Everything Servora-side already sits above the two-method `LabelDriver` seam, and
the live PrintNode round-trip was never verified — no account exists, no tenant is
on it. So this is not a migration. It is choosing a different implementation of a
seam that was built for exactly this swap, before anyone pays for the first one.

The proposal: a **Servora Print Agent** — a small self-hosted binary on the outlet
PC that pairs with the server, polls for jobs, prints the PDF, reports back. Build
our own PrintNode client, minus the cloud in the middle, minus the invoice.

---

## 1. Options considered

| | (a) Custom print agent | (b) QZ Tray | (c) Direct IPP / RAW:9100 | (d) Browser kiosk only |
|---|---|---|---|---|
| What it is | Small native binary on the outlet PC; polls Servora for jobs, prints PDFs to Windows printers | Open-source Java tray app; a **browser page** talks to it over a local websocket | Server opens TCP straight to the printer | Chrome `--kiosk-printing`, already built and the default |
| Solves phone → printer? | Yes — server holds the job, agent pulls it | **Not by itself.** QZ is driven from a browser page on the *same machine* as the printer. Phone-initiated printing still needs a resident page on the outlet PC polling the server and forwarding to QZ — i.e. architecture (a) with a Java tray app and a websocket bolted on | No — printers sit on the outlet LAN behind NAT; the droplet cannot reach them. Needs an on-prem bridge, which *is* (a) | No. One printer per PC, and the print must originate on that PC |
| Recurring cost | Zero | LGPL and free — but unsigned use shows a trust prompt unless a self-signed cert is manually installed per machine, and the sanctioned signing route is a paid QZ support plan (order of USD 1k+/yr). Also ships a bundled JRE | n/a | Zero |
| Status visibility | Full: heartbeat + per-job ack | Only while the driving page is open | None, and unreachable anyway | None — `sent` records intent, not outcome |
| Multi-printer per outlet | Yes | Yes, same caveat | n/a | No — OS default printer only |

**Decision: (a).** The agent is a poll loop, a printer enumerator and a shell-out to
a PDF printer — a few hundred lines of Go. The server half is `ClockDeviceService`
with the nouns renamed, plus a jobs table. QZ is rejected because it does not remove
the resident-process requirement and re-introduces a yearly fee — the thing being
removed. (c) degenerates to (a). (d) stays exactly as it is: the free default for
the laptop-at-station case; it simply cannot do tablets, multiple printers, or
status.

The one genuinely good property of PrintNode's architecture is kept: **the outlet
dials out**. Nothing listens on the outlet LAN, no port forwarding, works behind
NAT/CGNAT.

---

## 2. Server side

Everything maps onto seams that already exist. Nothing above the driver changes —
the same three gaps PrintNode already closed (driver-supplied status, per-batch job
id, no unconditional browser print event) carry the new driver unchanged.

### `print_agents`

Clone of `clock_devices` (migration `2026_08_07_000001` is the template), minus the
enrolment columns, plus an inventory:

| Column | Notes |
|---|---|
| `company_id`, `outlet_id` | both NOT NULL — an agent vouches for one outlet, chosen by the manager at pairing, same reasoning as the kiosk |
| `name` | "Back-office PC" |
| `token_hash` | SHA-256, unique. Raw token exists once, at redemption |
| `pairing_code`, `pairing_expires_at`, `paired_at` | 10-min TTL, human-safe alphabet — `ClockDevice::CODE_ALPHABET` verbatim |
| `last_seen_at`, `last_seen_ip` | heartbeat, write-throttled (the kiosk's 55 s rule) |
| `agent_version`, `hostname`, `os` | reported at pair and poll, so the UI can nag about stale versions |
| `printers` json, `printers_reported_at` | the agent's reported inventory: `[{name, is_default, status, papers: [{name, size}]}]` |
| `revoked_at`, `revoked_by`, `created_by` | revoke nulls the hash; the row survives for audit |

`printers` is JSON, not a child table, deliberately: it is a cache of remote truth,
replaced wholesale on every report and never queried relationally — exactly the
shape `PrintNodeClient::printers()` already returns to the picker.

### `print_jobs`

The queue the agent drains.

| Column | Notes |
|---|---|
| `company_id`, `print_agent_id`, `label_printer_id` | |
| `batch_id` | → `label_print_batches`. One batch = one document = one job, as with PrintNode |
| `printer_name` | the Windows queue name, **copied from the printer record at submit time** — editing the printer later must not retarget an in-flight job |
| `payload` | MEDIUMBLOB, the rendered PDF |
| `title`, `options` json | `{paper, rotate}` |
| `status` | `pending → delivered → done \| error \| expired` |
| `claimed_at`, `finished_at`, `expires_at`, `error_message` | |

Index `(print_agent_id, status)` — the poll query.

**The PDF lives in the DB, not `storage/`.** Batch PDFs are tens-to-low-hundreds of
KB and jobs live minutes; the platform already keeps queue, cache and sessions in
MySQL; a blob dies atomically with its row, where a file needs an orphan sweep and
breaks the day a second app server appears. Growth is handled by lifecycle, not
storage choice: **the payload is nulled the moment a job reaches a terminal
status**, the skeleton row is kept 7 days for debugging (aligned with the
reconciler's lookback), then deleted by an hourly `labels:prune-print-jobs`.

**Job TTL: `expires_at` = created + 10 minutes.** A label is wanted *now*; a job the
agent never collected means the PC is off, and the chef has long since fallen back
to browser printing. Expiry writes `expired` — vocabulary `label_prints` already
has.

**Redelivery:** a job `delivered` but not acked within 2 minutes is offered again on
the next poll (agent crashed mid-print). This can double-print inside that window.
Accepted knowingly: for HACCP, a duplicate label is peeled and binned; a missing one
is a compliance gap.

### Routes and auth

New `routes/print-agent.php`, mounted exactly like `routes/clock-staff.php`:
required at the top of `web.php`, `{companySlug}.<domain>` constraint in production,
a path-prefix fallback locally. The agent's configured server URL is the tenant
subdomain, so `company.subdomain` resolves the company and the token is checked
*within* it — a token lifted from one tenant is dead at another, the same property
the kiosk has.

| Route | Auth | Purpose |
|---|---|---|
| `POST /agent/pair` `{code, name?, hostname, version}` | pairing code only | Redeems the code for the raw token, once. CSRF-exempt: the caller is a native binary with no cookies — the code *is* the credential. (The kiosk's pairing form is a browser page and stays CSRF-protected; this one is not a page) |
| `GET /agent/jobs` | `X-Agent-Token` header | The poll. Returns pending jobs with inline base64 payload, marks them `delivered`. Doubles as the heartbeat — no separate ping endpoint |
| `POST /agent/jobs/{id}/status` `{status: done\|error, message?}` | header | Terminal ack. Updates `print_jobs` **and** `label_prints`, using the reconciler's exact state-write shape (drop `CompanyScope`, `whereIn PENDING_STATUSES`) |
| `POST /agent/printers` | header | Replace the inventory JSON. Sent on startup and every 5 minutes or on change |

Header routes are CSRF-exempt in `bootstrap/app.php` for the same by-construction
reason as `kiosk/*`: a header has to be set by the caller deliberately, so it is not
ambient authority. New pieces mirror the kiosk one-for-one:
`App\Http\Middleware\PrintAgentAuthenticate` (clone of `KioskAuthenticate`,
answering 401 JSON `{status: 'unpaired'}` so the agent knows to ask for re-pairing)
and `App\Services\Labels\PrintAgentService` (clone of `ClockDeviceService`: code
issued and consumed, token shown once, hashed at rest).

**What replaces the per-company API key: nothing.** Pairing binds the agent to a
company and outlet; the token is the credential. `label_settings.printnode_api_key`
is untouched and keeps working for any tenant still on the `printnode` driver.

### Poll cadence: short poll at 2–3 s. Not long-poll.

Long-poll parks a PHP-FPM worker per connected outlet for the duration of every
poll. The droplet's FPM pool is small and shared with the most-loaded screens in the
app, so a few dozen idle agents could starve real users — to save queries that cost
nothing. A short poll is one indexed query returning empty ~99% of the time; fifty
agents at 3 s is ~17 trivial requests/s. Latency works out: chef taps print → job
row committed inside the same request that already answers in seconds → agent
collects within one poll interval → label in hand well under 5 s. No idle backoff:
backoff trades the first label after a lull — the exact case where a chef is
standing at the printer — to save queries that are already free. If the fleet ever
grows past ~100 agents, long-poll is the revisit; the wire contract doesn't change.

### Driver, status, reconciler

- **`App\Services\Labels\AgentDriver`** implements `LabelDriver`: require
  `print_agent_id` + `agent_printer_name` on the printer record (fail loudly — an
  agent printer must never fall back to the local browser, the `DriverFactory`
  comment applies verbatim), render via the existing `LabelRenderService::pdf()`,
  insert a `print_jobs` row, return `{status: 'queued', document: null, job_id}`.
  One new arm in `DriverFactory`'s match, one new entry in `LabelPrinter::DRIVERS`.
- **Rotation.** PrintNode did `rotate_90` via a job option; SumatraPDF has no rotate
  flag. Do it server-side: set `/Rotate 90` in the PDF page dictionary after dompdf
  renders. **This is the one piece with no existing precedent — spike it first**
  (phase 1) before committing. Fallback: "rotate_90 unsupported on the agent driver
  in v1" — acceptable, since the printer-setup notes record that rotation was almost
  never the real fix (paper size was).
- **`PrinterStatus`** gains a branch: `driver === 'agent'` → `ONLINE` when the
  agent's `last_seen_at` is fresh within **2 minutes** (the agent polls every 3 s;
  two minutes is ~40 missed polls — tighter than the kiosk's 10 because a chef is
  deciding whether to walk to the printer) and the reported inventory entry isn't
  erroring; `OFFLINE` otherwise; never-paired → `UNKNOWN`. It reads two local
  columns — no HTTP, no cache subtlety, cheaper than the PrintNode path beside it.
- **`LabelJobReconciler`** keeps its PrintNode half untouched and gains one cheap
  sweep: `print_jobs` past `expires_at` and not terminal → `expired`, on the job and
  its `label_prints`. No external polling for agent jobs — the agent pushes `done` /
  `error` itself, which is the structural win over PrintNode's poll-only API. Same
  command, same ten-minute schedule.

---

## 3. The agent

| | (i) Go static binary + SumatraPDF | (ii) .NET | (iii) Node / Electron | (iv) Python + pywin32 |
|---|---|---|---|---|
| Runtime on the outlet PC | None — one .exe | Self-contained publish ≈70 MB, or a runtime install | Node/Chromium, 100 MB+, RAM-hungry on the old PCs outlets actually run | Python, or a PyInstaller bundle with its notorious AV false-positives |
| PDF → Windows printer | Shell out to SumatraPDF.exe — proven silent printing | `PrintDocument` cannot render PDF; needs PDFium or a commercial lib | the usual npm package wraps SumatraPDF anyway | shell verb, or Sumatra anyway |
| Linux later | `GOOS=linux`, print via `lp -d` — nearly free | possible | possible | possible |

**Decision: (i).** Every path that isn't Go-plus-Sumatra either bundles a heavy
runtime or ends up shelling out to Sumatra regardless.

**Print command:**

```
SumatraPDF.exe -print-to "<printer_name>" -print-settings "noscale,paper=<form>" -silent <file.pdf>
```

- `noscale` is non-negotiable — the module's 100%-scale rule, enforced at the last
  hop.
- `paper=<name>` (SumatraPDF **3.4+** — pin and bundle a known version; never use a
  machine-installed copy) is the agent's answer to the module's hardest-won lesson:
  **never let a print path pick the paper for you.** The job names its form, exactly
  as `options.paper` did under PrintNode.
- Licensing: SumatraPDF is GPLv3. Shipping it *beside* the agent — a separate
  executable, exec'd — is aggregation, not linking. Include its license file and a
  source pointer in the install zip.

**Printer and paper enumeration:** queue names via `EnumPrinters`
(`github.com/alexbrainman/printer`), paper form names via
`DeviceCapabilities(DC_PAPERNAMES)`. Honest v1 fallback if the syscall work slips:
report printer names only and make the paper field assisted free-text, with the
calibration doc's "create the form in Print Server Properties" instruction — the
workflow the browser path already documents. Either way the outlet setup guide says
to also set the label form as the printer's default preference, so a missing
`paper=` degrades to *correct* rather than to a rotated postage stamp.

**Shape:** a single loop. Load config → no token? prompt for server URL + pairing
code, `POST /agent/pair`, write config → report printers → poll every 3 s → per job:
write PDF to temp, exec Sumatra, ack `done` on exit 0 or `error` with the stderr
tail. Config is one JSON at `%ProgramData%\Servora\PrintAgent\config.json`
(`server_url`, `token`, `poll_seconds`). Logs rotate locally next to the config;
the server sees per-job failures through `error_message` without any log-shipping.

**Auto-start:** a Windows service via `kardianos/service` — the binary self-installs
(`servora-print-agent install|uninstall|run`), survives logoff and reboot, needs no
logged-in user (an advantage over the Chrome-kiosk PC, which does). Startup-shortcut
is the fallback for locked-down machines. **Updates:** v1 is manual re-download; the
reported `agent_version` lets the UI nag.

**Honesty about `done`:** it means "handed to the Windows spooler and Sumatra exited
0", not "a label emerged". Same epistemics as PrintNode's `done`. Spooler-level job
tracking is a later refinement, not a v1 promise.

---

## 4. Security

All inherited deliberately from the kiosk precedent, because it already answered
these questions:

- Pairing code from the human-safe alphabet, 10-minute TTL, redeemed once and
  consumed. A manager creates the agent row (naming it, choosing its outlet) and
  reads the code to whoever is at the PC — nobody types an admin password into an
  outlet machine.
- Token: 64 chars, SHA-256 at rest, raw value exists only in the redemption
  response and the agent's config file. No human ever sees it — narrower even than
  the kiosk cookie.
- Company + outlet are bound *before* the box is on site, by someone who knows
  where it's going. The agent cannot nominate its own outlet.
- Revoke from the UI nulls the hash; the row survives so "which jobs went through
  the stolen PC" stays answerable.
- Job payloads are only reachable through the token owning the job's agent — the
  lookup is `where print_agent_id = <resolved agent>`, never by bare job id.
- Outbound-only, HTTPS to the tenant subdomain. Nothing to port-forward, nothing
  listening in the outlet.

---

## 5. UI changes

- **New `/labels/agents`** (`Labels\Agents`, `labels.manage`), beside
  `/labels/printers`: list agents (name, outlet, version, last-seen phrase —
  the `ClockDevice::statusLabel()` pattern), create + issue pairing code (modal
  shows the code and the exact server URL to type into the agent), regenerate,
  revoke.
- **`Labels\Printers`**: the driver select gains `agent`. Choosing it shows an agent
  picker (active paired agents, flagged when offline), then that agent's *reported*
  printer list, then that printer's *reported* papers — a structural mirror of the
  existing PrintNode picker (`loadRemotePrinters()` / `paperOptions()`), except it
  reads `print_agents.printers` JSON instead of calling a remote API: instant, and
  no error path needed. Same clearing rules: changing the agent clears the printer
  name; changing the printer clears the paper — paper names belong to one driver,
  the comment already written on `updatedPrintnodePrinterId()`.
- **Settings**: the PrintNode key section gains one sentence pointing at agents as
  the recommended path. It is removed only when no `label_printers` row carries
  `driver='printnode'`. **All PrintNode code stays** — built, tested, zero marginal
  cost, and the escape hatch if the agent hits an unforeseen wall.

---

## 6. Rollout and risks

Browser kiosk stays the default driver. `agent` is opt-in per printer. No existing
behaviour changes.

| Risk | Handling |
|---|---|
| Outlet PC off / agent dead | Job expires at 10 min → `expired` on the compliance record; printer badge goes offline within 2 min; agent printers never silently fall back to the browser (deliberate, as with PrintNode) |
| Windows driver misconfig — wrong form, scaling | Same failure and same fix as ever: the "Printer setup, learned the hard way" notes apply verbatim; `paper=` per job is the systematic guard |
| PHP-FPM exhaustion from polling | Avoided by design — short poll chosen for exactly this; revisit long-poll only past ~100 agents |
| Payload growth in MySQL | Payload nulled at terminal status; skeleton rows pruned at 7 days, hourly |
| SmartScreen on an unsigned exe | v1: a documented "More info → Run anyway" in the setup guide. Later: one OV code-signing cert (~USD 100–400/yr) covers every tenant — contrast with QZ's support plan and PrintNode's per-computer month |
| Duplicate print in the crash-redelivery window | Accepted: duplicate label < missing label, for a food-safety record |
| Sumatra version drift | Pinned and bundled; a machine-installed copy is never used |
| `/Rotate` post-process unproven | Phase-1 spike before anything else depends on it; fallback is documenting rotate_90 as unsupported on this driver in v1 |

---

## 7. Phasing

**Phase 1 — server.** Migrations (`print_agents`, `print_jobs`, three columns on
`label_printers`), `PrintAgentService`, `PrintAgentAuthenticate`,
`routes/print-agent.php` + CSRF exemptions, `AgentDriver` + `DriverFactory` arm +
`LabelPrinter::DRIVERS`, `PrinterStatus` branch, reconciler expiry sweep,
`labels:prune-print-jobs`, the Agents screen and the Printers picker. Fully testable
with a fake agent driven by curl. Includes the `/Rotate` spike.

**Phase 2 — agent v1 (Windows).** Go binary: pair / poll / print / ack, printer +
paper enumeration (or the free-text fallback), service self-install, bundled
Sumatra, install zip.

**Phase 3 — polish.** Outlet setup guide (mirroring the existing "Outlet laptop
setup" section), version nag, error surfacing in the print log, and a live
end-to-end run against a real Deli/Zebra — the verification step PrintNode never
got.

**Later.** Linux agent (`lp -d` via CUPS — nearly free given Go), auto-update,
spooler-level job tracking, ZPL/raw passthrough for printers where PDF-through-
driver is the bottleneck.

---

## 8. Open questions

1. Are outlet PCs Windows-only in practice, and roughly what spec/age? Decides how
   hard to lean on the service-install path, and whether Linux moves up.
2. Expected outlet/agent count at 12 months? Validates the short-poll arithmetic.
3. Code-signing budget for v1 (~USD 100–400/yr), or is the SmartScreen
   click-through acceptable at first?
4. Any near-term Zebra-fleet demand that would pull ZPL passthrough forward?
5. Keep the PrintNode driver indefinitely as the escape hatch, or sunset it once
   the agent is proven in the field?
