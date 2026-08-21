# Fingerprint punching — buy vs build

> Status: **plan only, nothing built, nothing bought.** Drafted 2026-08-21 as the
> next step after [ngteco-tc4-integration-study.md](ngteco-tc4-integration-study.md),
> which ruled out the TC4 (§1–5) and ruled out doing fingerprint in the PWA (§6).
> Two paths survived. This doc costs them against each other so the buy-vs-build
> call can be made from evidence rather than before it.
>
> **This is a decision document, not a build order.** §7 lists what has to be
> answered before anybody writes code or raises a PO, and two of those answers can
> flip the recommendation.

---

## 0. A correction that changes the comparison

§6 of the study said a live agent punch fits `ClockInService::punch()` unchanged.
That is true of a *live* punch and it is not the whole picture.

**Both options deliver punches with a past timestamp.** An ADMS terminal buffers
into its own memory when the Wi-Fi drops and pushes on reconnect, carrying the
original punch times. An agent would do the same — `tools/pos-agent` already has
`spool.go` for exactly this, and a fingerprint agent that *refused* to punch while
offline would be worse at the one job a wall-mounted clock has. A dead router at
6am must not stop a kitchen clocking in.

So the historical-timestamp writer described in study §4 gets built **either way**.
It is shared work, not a cost of one option. That removes what looked like the
biggest asymmetry between the two, and it is why this plan leads with the shared
half.

---

## 1. The two options

| | **A — ADMS terminal** | **B — Windows fingerprint agent** |
|---|---|---|
| Hardware per outlet | One wall-mounted ZKTeco business terminal | A PC that stays on, plus a USB reader (ZK9500) |
| What we build | An `/adms` receiver in Servora | `tools/fingerprint-agent` + a receive endpoint |
| Rough new code | Receiver + shared half | Agent (~1.5–2k LOC by the pos-agent yardstick) + shared half |
| SDK licence | None — device does its own matching | **ZKFinger SDK is free with the ZK9500**; DigitalPersona is commercially licensed |
| Matching runs | On the device | Unresolved — see §4.2, this is B's blocking spike |
| Enrolment | At the terminal | At the PC |
| Offline | Device buffers, pushes on reconnect | Agent spools, drains on reconnect |
| Main risk | **Transport security** (§3.2) | **Where templates live** (§4.2) |
| Ongoing tax | Firmware, one box on a wall | A Windows PC per outlet that must stay powered, awake and updated |
| When it breaks at 6am | Swap the box | Debug Windows at an outlet, by phone |

Neither is exotic. The difference that decides it is the last two rows, and we
already have evidence about the second one: the print agent taught us what
supporting Windows at outlets actually costs.

---

## 2. Shared half — built either way

### 2.1 A writer that takes `happened_at`

`ClockInService::punch()` stamps `$at = now()` deliberately: *"a phone's clock is
settable, and this one decides whether its owner was late."* Correct for a live
punch, destructive for a buffered one — a batch pushed after a two-hour outage
would arrive dated to the moment of reconnection, making everybody in it uniformly
punctual or uniformly late.

So: a sibling writer sharing `ShiftResolver`, `PunchState` and the lateness
arithmetic, taking `happened_at` as an argument and re-resolving the shift and late
minutes *against that moment*. All judgement stays in one place; only the clock
differs. **This is the single most delicate piece of work in either option** —
it touches the number that comes off somebody's service charge — and it wants
tests before it wants a caller.

### 2.2 Identity: `staff_id` ↔ device user ID

`employees.staff_id` is the natural key and is already indexed
`(company_id, staff_id)`. A punch for an unmapped ID must be **held for a manager,
never dropped and never guessed** — an unattributed punch that silently vanishes is
worse than one sitting in a queue, because nobody finds out until payroll.

### 2.3 Idempotency

Unique on `(device serial, device user id, happened_at, type)`. A terminal
re-sending its buffer, or an agent re-draining a spool after a crash, must not
double-charge anybody's lateness. `pos-agent`'s sha-dedupe ledger is the precedent.

### 2.4 Flags: the match is evidence, not an absence

A fingerprint punch carries no selfie and no coordinates, so `no_face` and
`no_location` would fire on **every single one** and bury the review queue inside a
day — the exact failure `NON_REVIEWABLE_FLAGS` exists to prevent. The device
verified a finger against an enrolled template; that is a different *kind* of
evidence, not a gap in it. Needs its own flag recording that, and suppression of
the two that do not apply.

### 2.5 Enrolment — the hidden cost, and it is the big one

Both options need every employee's finger physically enrolled, once, at a device.
That is not a feature, it is an operation: someone stands at each outlet with a
staff list and works through it, and repeats it for every new hire. Servora already
has the shape for this — `ClockDevice::ENROL_WINDOW_MINUTES` opens a time-boxed
enrolment mode that closes itself, precisely so a device left in enrolment mode
cannot still be in it next Tuesday. Reuse the concept.

**Budget for this honestly.** It is the line item most likely to be underestimated
and the one staff will actually notice.

### 2.6 Devices screen

Terminals and agents both appear beside kiosks with a heartbeat and a revoke
button. `ClockDevice` already carries outlet, token, `last_seen_at`, `revoked_at`
and `statusLabel()`. A paired terminal or agent **is** a registered device at an
outlet — give it a `ClockDevice` row and `kioskLocation()` applies, the geofence is
correctly skipped, and revocation works on day one. **No schema change beyond a
`kind` column and the serial number.**

---

## 3. Option A — ADMS receiver

### 3.1 What gets built

A route group modelled on `routes/pos-agent.php`: registered ahead of `web.php`,
CSRF-exempt for the same safe-by-construction reason (binary caller, no cookies),
and **not JSON** — ADMS is `key=value` query parameters with tab-separated
plain-text bodies.

Three endpoints: `/iclock/cdata` (GET handshake, POST data), `/iclock/getrequest`
(device polls for pending commands), `/iclock/devicecmd` (command acknowledgement).
Attendance rows arrive as `UserID, Timestamp, Status, VerifyMode, WorkCode`.

### 3.2 Security is the design problem, not an afterthought

Every reference implementation of ADMS assumes the server sits on the same LAN as
the devices. **Ours does not — Servora is on the public internet.** That changes
the threat model completely, and the protocol was not built for it:

- **Plain HTTP is the default.** HTTPS exists only on newer firmware.
- **Identity is the serial number.** Stateless, no session, no challenge.
- One published ADMS deployment guide resorts to `.htaccess` passwords on the
  `/iclock/*` routes precisely because "anyone on the same network can access these
  routes" — and for us "the same network" is the internet.

Untreated, this is a punch endpoint on the open internet authenticated by a string
printed on the back of the box. The mitigations, all of which are in scope:

1. **Claim before counting.** An unknown serial number is recorded and quarantined,
   never trusted. A manager claims the SN in Servora — the `ClockDevice` pairing
   flow with the code read off the device instead of the screen — and only a
   claimed terminal's punches reach the writer.
2. **`pushcommkey`** set per device, never a shared constant.
3. **TLS required in production.** Which means firmware that supports it.
4. **Rate limit per SN**, and treat every field in the body as untrusted input.

> **This is the condition the whole recommendation rests on.** *Confirm with the
> distributor, in writing, that the specific model supports pushing to an HTTPS
> endpoint with a valid public certificate* — before a PO is raised, not after.
> If it cannot, option A is off the table and B wins by default.

### 3.3 Phases

1. **Spike** — one terminal on a desk, pointed at a staging Servora, proving the
   handshake, an HTTPS push, and a real punch landing. Nothing else starts until
   this works.
2. Shared half (§2), writer first, with tests.
3. Receiver: claim/quarantine, parsing, idempotency, rate limiting.
4. Enrolment flow and the devices screen.
5. One outlet in production, running **beside** the kiosk rather than instead of
   it, for a full payroll cycle.

---

## 4. Option B — Windows fingerprint agent

### 4.1 What gets built

`tools/fingerprint-agent`: a Go binary on the outlet PC, the same skeleton as
`tools/pos-agent` (1,943 LOC) and `tools/print-agent` (1,606 LOC) — pair once with
a code, Windows service, token in `%ProgramData%`, outbound HTTPS only, spool for
offline. `print-agent` already has the `platform_windows.go` precedent for calling
into Windows from Go, which is how the SDK DLL gets reached.

**Hardware: ZK9500, because the ZKFinger SDK for Windows is free of charge with
it.** DigitalPersona/HID U.are.U is commercially licensed with redistribution
terms, which turns a hardware choice into a contract negotiation.

### 4.2 The blocking spike: where do templates live?

`FaceIdentifier` makes a deliberate architectural choice, and it is worth quoting
because it decides this one:

> *"The tablet computes a descriptor from a camera frame … and sends 128 floats. It
> never receives anybody's enrolment … Doing this the other way round would mean
> shipping the company's entire face database to a tablet sitting on a public
> counter."*

The same instinct says the agent should capture, send a template, and let the
server do 1:N. **But a fingerprint template is a proprietary format that only the
vendor's library can match — PHP cannot compare two of them.** So server-side
matching needs a ZKFinger matching service running next to Servora, which is a
second deployable and a Linux SDK question. Matching on the agent instead means
**every outlet PC holds a copy of the company's fingerprint templates**, which is
exactly the thing `FaceIdentifier` refuses to do with faces.

There is no free answer here. Neither is obviously wrong, but the choice is
architectural, it contradicts an existing decision either way, and it must be
resolved **before** committing to B — not discovered in week three.

### 4.3 Phases

1. **Spike** — resolve §4.2. Capture on a ZK9500 from Go, and prove whichever
   matching topology is chosen actually works.
2. Shared half (§2).
3. Agent: pair, capture, match, spool, drain. Installer and service.
4. Enrolment at the PC, devices screen.
5. One outlet beside the kiosk for a payroll cycle.

---

## 5. Cost

**Hardware pricing could not be established from here and should not be guessed.**
Searches returned no current Malaysian retail or trade prices for the candidate
models, and this environment's egress policy blocks several vendor sites. Get a
written quote covering: unit price per outlet, whether **ADMS/push is included or a
paid add-on** (some listings show it as optional), firmware version, **HTTPS push
support per §3.2**, warranty, and local support turnaround.

What can be said without a quote:

- **Development is comparable.** The shared half (§2) dominates both, and B adds an
  agent on top — so B is strictly more code, by roughly the size of an existing
  agent.
- **Ongoing cost is not comparable, and this is the real gap.** A is a box on a
  wall with firmware. B is a Windows PC per outlet that must stay powered, awake,
  updated and driver-intact — and when it breaks it breaks at 6am, at an outlet,
  to be debugged by phone. That tax recurs monthly and is paid by whoever is on
  support, forever.
- **B's SDK is free**, which removes what would otherwise have been its worst cost.

---

## 6. Recommendation

**Option A, conditional on the TLS answer in §3.2.** Not because the receiver is
easier — it is roughly the same work — but because the ongoing per-outlet burden is
where B loses, and we already have direct evidence of what supporting Windows at
outlets costs us.

**If terminals cannot push to HTTPS, switch to B**, and resolve §4.2 first.

**Before either: do the cheap diagnosis.** If the reason fingerprint came up is
that face identification struggles somewhere specific, find out where and why
first. Repositioning a kiosk or adding light over it is a morning's work against a
fortnight's, and `FaceIdentifier` already records `ambiguous` and `unknown`
outcomes — the data to answer it is in production now. **Run that query before
spending anything.**

---

## 7. Answer these before building

| # | Question | Who answers |
|---|---|---|
| 1 | Why did fingerprint come up — is face identification failing, and where? | Us, from production data |
| 2 | Does the candidate terminal push to a public **HTTPS** endpoint? (§3.2 — flips the recommendation) | Distributor, in writing |
| 3 | Is ADMS included or a paid add-on on that SKU? | Distributor |
| 4 | Unit price per outlet, warranty, local support turnaround | Distributor quote |
| 5 | If B: server-side matching service, or templates on outlet PCs? (§4.2) | Us, architectural |
| 6 | How many outlets, and how many staff per outlet to enrol? (§2.5) | Operations |
| 7 | Does fingerprint **replace** face, or run alongside it as a third method? | Operations |

Question 7 is worth asking early. Running alongside is the safer rollout and is
what §3.3/§4.3 phase 5 assumes — but if the intent is replacement, the enrolment
burden in §2.5 becomes a hard cutover date rather than a gradual one, and that
changes the plan.
