# NGTeco TC4 → Servora time attendance — feasibility study

> Status: **research only, no code written.** Drafted 2026-08-21 to answer one
> question: can the NGTeco TC4 cloud time clock feed Servora's attendance module?
> Companion to the clock-in domain that already exists — `ClockEvent`,
> `ClockDevice`, `ClockSetting`, `ClockInService` — which this doc treats as the
> thing any device integration has to plug into, not as something to be replaced.

**Short answer: not in any supported way, and Servora already does what the TC4
does.** The TC4 is a sealed cloud appliance. It talks to NGTeco's AWS backend and
to nothing else — no open API, no webhook, and (unlike ZKTeco's business range) no
configurable server address to point at us. The only way data leaves it is an Excel
report a human exports from the NGTeco Office app or web portal. Any "integration"
is therefore a **file import of yesterday's punches**, which throws away every
control the clock module was built around: server-stamped time, the selfie, the
geofence, the flag set, the review queue, and the late-minute charge computed at
the moment of the punch.

If the goal is *dedicated punch hardware at the counter* rather than *this
particular box*, there is a real path — see option (c). It needs different hardware
from the same parent company, and it is a fortnight of work, not an afternoon.

---

## 1. What the TC4 actually is

NGTeco is ZKTeco's consumer / small-business brand ([confirmed by
NZTeco](https://www.nzteco.co.nz/product-category/ngteco/), ZKTeco's NZ
distributor). That lineage matters, because it sets up the trap this study exists
to flag: ZKTeco's *business* terminals are famously integrable, and the NGTeco
*cloud* line — TC1, TC2, TC4, TC7 — deliberately is not. Same silicon heritage,
opposite product decision.

| | TC4 (what was asked about) | ZKTeco business line (e.g. MB460, K40, SpeedFace-V5L) |
|---|---|---|
| Sold as | Cloud time clock for SMEs, "no monthly fee" | Access-control / T&A terminal |
| Managed by | NGTeco Office app + [office.ngteco.com](https://office.ngteco.com/) | Vendor software **or your own server** |
| Server address configurable | **No** — bound to NGTeco's AWS backend at setup | **Yes** — ADMS server IP/port in the device menu |
| Push protocol | None exposed | ADMS / Push SDK over HTTP(S) |
| Pull protocol | None exposed | ZK protocol on TCP 4370 |
| Open API | **None.** NGTeco state plainly that their devices "do not support open API access or direct software integration with third-party platforms" | Documented SDKs, plus mature third-party libraries |
| Data egress | Excel export / emailed report, by hand | Real-time push, per punch |

Setup is app-first and there is no admin path around it: pair the device over
Bluetooth from the NGTeco Office app, hand it 2.4 GHz Wi-Fi credentials, done. The
device menu configures *attendance rules*, not *where the data goes*. There is no
screen on which a server address could be typed, which is the whole finding.

**A note on the model number.** Retail listings under the "TC4" name are not
consistent — one describes a fingerprint-only unit on 2.4 GHz Wi-Fi, another a
4-in-1 (face / fingerprint / RFID / PIN) with a 4.3" touchscreen and dual-band
Wi-Fi, ~200 users. This does not change the conclusion (every unit in the cloud
line is bound to the same backend), but **confirm the exact SKU against the box
before anyone buys on the strength of a spec sheet.** NGTeco's own site is blocked
by this environment's egress policy, so the spec claims above come from search
result summaries and retail listings rather than from the vendor page read directly.

### The one thing that would change the answer

If the TC4 firmware turns out to expose the ZK pull protocol on port 4370 on the
local network — undocumented, but its ancestry makes it *conceivable* — option (c)
below becomes available without new hardware. The pyzk project has [a report of an
NGTeco device (NG-MB2) answering on 4370 with ZKTeco
firmware](https://github.com/fananimi/pyzk/issues/240), so the family is not
uniformly sealed. That report is a *different, non-cloud model*, and basic reads
worked while user enrolment froze the device outright.

**This is a ten-minute test, and it is the only test worth running before deciding:**
put a TC4 on the outlet LAN, find its IP, and `nmap -p 4370` it. Open port →
re-read this doc. Closed → the table above stands and the answer is settled.

---

## 2. Servora already has this

This is the part that should be weighed before any of the options below, because
it is what makes most of them not worth doing. Servora's clock module is not a
timesheet — it is an evidence chain, and every link is load-bearing somewhere
downstream in payroll:

- **`ClockDevice`** — a paired outlet tablet: pairing code with a 10-minute TTL,
  hashed token, one-minute heartbeat, revocation. `ClockDeviceService` owns all of
  it. This is already "dedicated punch hardware at the counter"; it just runs on a
  tablet you already own instead of a purpose-built box.
- **Face identification at the kiosk** — the camera has to name somebody out of
  everybody posted to that outlet before a punch is even offered, with PIN as the
  documented fallback and `pin_fallback` / `face_ambiguous` recorded when it is used.
- **Geofenced BYOD** — phone punches are measured against the outlet's fence, and
  flagged `byod_when_kiosk_up` if the kiosk was online and they went round it.
- **A review queue with reasons** — `ClockEvent::FLAG_LABELS` carries eighteen
  distinct flags, split by `NON_REVIEWABLE_FLAGS` into "a manager must look" versus
  "this is just a record of what happened".
- **Lateness priced at the punch** — `minutes_late` → `chargeable_late_minutes` →
  `penalty_amount`, feeding `LatePenalties` and the service-charge distribution.
- **A selfie retained as evidence**, soft-deleted with the punch so a disputed one
  can be restored intact.

The TC4 offers face/fingerprint/RFID/PIN capture and hour totals. It offers none of
the rest, and it cannot be told about any of it. **Sending punches from the TC4
into Servora is a downgrade of the attendance record, not an upgrade of it.**

---

## 3. Options

| | (a) Keep the Servora kiosk | (b) Import NGTeco Excel exports | (c) ADMS receiver + ZKTeco business hardware | (d) Reverse-engineer the NGTeco cloud API |
|---|---|---|---|---|
| What it is | Do nothing; the TC4 is not bought | A `terminal` punch source fed by an uploaded report file | Servora speaks ADMS; devices push punches to us in real time | Sniff the NGTeco Office app, call its private endpoints |
| Punch latency | Live | A day or more, and only when somebody remembers to export | Seconds | Poll interval |
| Face evidence | Selfie per punch | None | None (device verifies; no image reaches us) | None |
| Geofence / device attestation | Yes | None | Device SN is the attestation | None |
| Lateness charged correctly | Yes | Only by re-deriving it from a past timestamp | Yes | Yes-ish |
| Review queue | Works as designed | Every punch arrives flagless or falsely flagged | Works, with a terminal-aware flag path | Works |
| Cost | Zero | ~1 week + a permanent manual step | ~2 weeks + new hardware per outlet | Uncosted; breaks without warning |
| Risk | None | Silent gaps when nobody exports | Ordinary | ToS violation, no support, no stability guarantee |

**Recommendation: (a).** Buy nothing. The TC4 solves a problem Servora solved in
August, and solves it worse, and cannot be wired in without hand-carrying a
spreadsheet.

**If dedicated hardware is genuinely wanted** — a fair ask; a bolted-down terminal
survives a kitchen better than a tablet, and a fingerprint reader beats a camera in
a steamy prep area — then **(c)**, and buy an ADMS-capable ZKTeco unit rather than
an NGTeco cloud one. Same parent company, roughly the same money, and the device
menu has the field this whole study is about.

**(d) is rejected outright.** It is against NGTeco's terms, unsupported, and would
put staff biometric attendance behind an undocumented endpoint that can change in
any app release, silently, on a Friday.

---

## 4. What (b) or (c) would actually cost us

Both land in the same place — punches arriving from a device Servora did not
observe — so they share a design, and it is worth writing down because the obvious
implementation is wrong in a way that would not show up until payroll.

**`ClockInService::punch()` cannot be the writer.** It stamps `$at = now()`
deliberately — "a phone's clock is settable, and this one decides whether its owner
was late" — and derives `source` from whether a verified `ClockDevice` was
presented. Both are correct for a live punch and both are fatal for an imported
one, which carries a past timestamp from a third-party clock. An import that went
through `punch()` would record every punch in a batch as having happened at import
time: everybody on time, or everybody catastrophically late, depending on when the
file was uploaded.

So the work is a **sibling writer** — call it `TerminalPunchWriter` — sharing
`ShiftResolver`, `PunchState` and the lateness arithmetic, but taking `happened_at`
as an argument and re-resolving the shift and the late minutes *against that
moment*. The judgement stays in one place; only the clock differs.

Around it:

1. **Schema.** `source` is an `enum('kiosk','byod','manual')`; add `terminal`.
   Reuse `clock_devices` for the terminal registry rather than adding a table — it
   already carries outlet, token, heartbeat and revocation — with a `kind` column
   and the serial number, and let `ClockEvent::sourceDetail()` name it.
2. **Identity mapping.** `employees.staff_id` is the natural key against the
   device's user ID; it is already indexed `(company_id, staff_id)`. A punch for an
   unmapped ID must be **held, not dropped and not guessed** — an unattributed
   punch that silently vanishes is worse than one sitting in a queue.
3. **Idempotency.** `(device serial, device user id, happened_at, type)` unique.
   Re-uploading last week's export, or an ADMS device re-sending its buffer after a
   Wi-Fi drop, must not double-charge anybody's lateness.
4. **Flags.** A terminal punch has no selfie and no coordinates, so `no_face` and
   `no_location` would fire on every single one and bury the review queue in noise
   within a day. The terminal path needs its own leniency — the device verified the
   person, which is a different kind of evidence, not an absence of it — plus
   probably a new non-reviewable flag recording *that* it was device-verified.
5. **Transport.** For (c), an `/adms` route group modelled on
   `routes/pos-agent.php`: unauthenticated registration, then a token-checked
   middleware, CSRF-exempt, JSON-free (ADMS is `key=value` and tab-separated text
   over `/iclock/cdata`, `/iclock/getrequest`, `/iclock/devicecmd`). For (b), a
   Livewire upload screen and a parser, plus a standing instruction to a human that
   the system cannot enforce.

Neither option gives Servora a selfie or a geofence result. That is not a gap in
the implementation — it is what buying the punch from someone else's device costs,
and it is the reason (a) is the recommendation.

---

## 5. Sources

- [NGTeco TC4 product page](https://ngteco.com/products/cloud-time-clock-tc4) *(vendor site blocked by this environment's egress policy; content via search summaries)*
- [NGTeco FAQs](https://ngteco.com/pages/faqs) — the "no open API" statement
- [NGTeco cloud platform](https://ngteco.com/pages/cloud) and [NGTeco Office web portal](https://office.ngteco.com/)
- [NGTeco TC4 user manual](https://manuals.plus/asin/B0F9YRKNXN) *(also blocked)*
- [NGTeco is a ZKTeco brand — NZTeco](https://www.nzteco.co.nz/product-category/ngteco/)
- [pyzk issue #240 — NGTeco device on the ZK protocol](https://github.com/fananimi/pyzk/issues/240)
- [ZKTeco ADMS / Push protocol overview](https://www.linkedin.com/pulse/zkteco-adms-protocol-link-your-zk-device-server-herbin-tsobeng-qg0ze), [zkteco-adms (Go)](https://github.com/s0x90/zkteco-adms), [ADMS server reference implementation](https://github.com/mmd-rehan/ADMS-server-ZKTeco)

---

## 6. Follow-up: fingerprint scanning in the PWA on a Windows PC

Asked 2026-08-21, immediately after the above: *can the staff PWA read a USB
fingerprint scanner on a Windows machine?*

**A web page cannot read a fingerprint scanner.** This is not a gap in our code or
a browser we have not tried — no browser exposes fingerprint data to a page, on any
OS, deliberately. There are three routes that look like they get round that, and
only the third one works.

### (A) WebAuthn / Windows Hello — real, and wrong for attendance

This is the one that genuinely runs in a PWA. Windows Hello is a platform
authenticator, Chrome and Edge expose it through WebAuthn, and a fingerprint on a
Hello-compatible reader will satisfy it. It fails here for three reasons, and the
second is fatal:

1. **It binds to the Windows account, not to your staff list.** Hello enrolments
   live against the PC's Windows user, capped around ten per device. A kitchen with
   turnover blows through that, and every enrolment is a Windows admin task at the
   machine rather than something a manager does from Servora.

2. **The PIN fallback cannot be switched off.** `userVerification` is one bit in
   the authenticator data — verified or not — and it does not distinguish a
   fingerprint from a passcode. The extension that would report the method (`uvm`)
   has never shipped in any browser. So Windows Hello is free to satisfy
   `userVerification: "required"` with the machine's Hello PIN, and we cannot tell
   afterwards that it did. **Anyone who knows the kiosk PC's Windows PIN can punch
   as any colleague whose credential is on that box, and the punch arrives looking
   perfectly verified.** For a system whose whole job is knowing who was at work,
   that is the end of the discussion.

3. **It authenticates, it does not identify.** The person picks their name, then
   proves it. That is our existing PIN flow with a Windows dependency bolted on —
   and strictly worse than `FaceIdentifier`, which answers *who is this* against
   everybody posted to the outlet, with a threshold and a runner-up margin, and
   asks for a PIN rather than resolving a coin toss.

### (B) WebUSB / WebHID straight to the scanner — does not work

Two independent blockers, either one sufficient:

- **Windows will not let go of the device.** Fingerprint readers bind to the
  Windows Biometric Framework (WBDI). Chrome can only open a device bound to
  `WinUSB.sys`, so making this work means running Zadig on every outlet PC to swap
  the driver — which disables Windows Hello on that machine and is not something
  anyone can ship to an outlet as an instruction.
- **A raw image is not an identity.** Minutiae extraction and 1:N matching are the
  vendor's proprietary SDK. Reimplementing that in JavaScript is a research
  project, not a sprint.

Chrome and Edge only, besides — no Safari, no Firefox, so any iPad kiosk is out.

### (C) A local agent — the answer, and we have built it twice

`tools/print-agent` and `tools/pos-agent` are both already this: a small Go binary
on the outlet PC that pairs once with a code, runs as a Windows service, keeps its
token in `%ProgramData%`, and talks outbound HTTPS only — no inbound ports, works
behind NAT. A fingerprint agent is that skeleton with a vendor SDK (DigitalPersona
/ HID U.are.U, SecuGen, Futronic, ZKFinger) where SumatraPDF sits in the print one.
It captures, extracts the template, matches 1:N locally, and posts the identified
`staff_id`.

Two shapes:

- **(C1) The agent is the punch client.** It posts straight to a device-token
  endpoint, exactly as `pos-agent` posts batches. No PWA involvement at all. This
  is the simple one and probably the right one.
- **(C2) The agent is a sidecar the kiosk PWA calls** on `http://127.0.0.1:<port>`,
  so the PWA stays the UI and gains fingerprint as a third identify method beside
  face and PIN. Loopback is exempt from mixed-content blocking, so an HTTPS page
  may call it — but **Chrome 142 gates loopback requests from public origins behind
  a Local Network Access permission prompt**, so this needs a one-time grant per
  kiosk. Workable, one more thing to go wrong at 6am.

**A live agent punch fits `ClockInService::punch()` unchanged, and that is the
whole difference from §4.** It happens *now*, so the deliberate `$at = now()` is
correct rather than destructive — none of the sibling-writer work an imported batch
needs. Better still, a paired agent **is** a registered device at an outlet, so it
can hold a `ClockDevice` row and punch as `source = kiosk` with `clock_device_id`
set: `kioskLocation()` applies, the geofence is correctly skipped, the devices
screen shows its heartbeat, and revocation already works. **No schema change at
all.** The only real work beyond the agent is flags — a fingerprint punch carries
no selfie, so `no_face` would fire on every one and bury the review queue inside a
day. The fingerprint match *is* the evidence; it needs recording as such rather
than as an absence.

### (D) Don't build any of it — buy the terminal

A standalone ADMS-capable ZKTeco fingerprint terminal, per option (c) in §3, does
capture, 1:N matching and enrolment in a box that is already certified, already
survives a kitchen, and pushes punches to us over HTTP. No PC, no driver, no agent
to maintain across Windows updates. **If the reason for wanting a fingerprint at
all is "the camera struggles in the prep area", this is cheaper and faster than
(C) and should be priced first.**

### Recommendation

**Do not attempt (A) or (B).** (A) looks closest to a PWA answer and is the one
worth explicitly refusing, because the PIN-fallback hole is invisible: it produces
punches that pass every check we have while proving nothing about who made them.

If fingerprint is genuinely needed, price **(D)** before **(C)** — the same
decision §3 already reached by a different road. And if the actual problem is that
face identification struggles in a specific room, say so out loud first: a better
camera position, or better lighting over the kiosk, is a morning's work against a
fortnight's.

> **Next step, and one correction.** Both surviving paths — (C) the agent and (D)
> the terminal — are costed against each other in
> [fingerprint-punch-plan.md](fingerprint-punch-plan.md). That plan corrects one
> claim above: a live agent punch does fit `punch()` unchanged, but an agent that
> spools while offline (as `pos-agent` already does, and as any clock at an outlet
> should) delivers past timestamps too. So the historical-timestamp writer of §4 is
> shared work, not a cost of the terminal option.
