# ZKTeco ADMS (Push) protocol — field notes

> Status: **research, 2026-08-21.** Grounded in three working open-source
> implementations read in full, not in vendor documentation:
> [`s0x90/zkteco-adms`](https://github.com/s0x90/zkteco-adms) (Go, tested),
> [`syofyanzuhad/filament-zkteco-adms`](https://github.com/syofyanzuhad/filament-zkteco-adms)
> (**Laravel**, closest to our stack) and
> [`mmd-rehan/ADMS-server-ZKTeco`](https://github.com/mmd-rehan/ADMS-server-ZKTeco)
> (Laravel, deployment-focused). Prompted by the observation that a number of
> people integrate ADMS with Odoo, so the ground is well trodden.
>
> Written to make [fingerprint-punch-plan.md](fingerprint-punch-plan.md) §3
> buildable. **It changes three things in that plan** — two of them make option A
> cheaper, one makes it stricter. See §6.

---

## 1. Shape of the thing

The device dials out. Nothing listens at the outlet, nothing is port-forwarded,
and the terminal polls a URL you type into its menu. That is the whole appeal, and
it is why the Odoo integrations describe it as needing "no public IP" — true for
them, because their Odoo is usually on the same LAN. **Ours is not**, and §5 is
about what that costs.

It is not REST. Plain HTTP, identity in a query parameter, `key=value` bodies and
tab-separated data rows. `Content-Type` is `text/plain` throughout, in both
directions. A successful reply is very often the two bytes `OK`.

## 2. The endpoints

| Route | Method | Purpose |
|---|---|---|
| `/iclock/cdata` | GET | Handshake — device asks for its configuration |
| `/iclock/cdata` | POST | Data push — `?table=ATTLOG` / `OPERLOG` / `USERINFO` |
| `/iclock/getrequest` | GET | Device polls for pending commands |
| `/iclock/devicecmd` | POST | Device reports a command's result |
| `/iclock/registry` | GET/POST | Registration and capabilities (newer firmware) |
| `/iclock/test` | GET/POST | Connectivity check — returns `OK` |

Every request carries `?SN=<serial>`. That is the only identity in the protocol.

## 3. The conversation

**Handshake.** `GET /iclock/cdata?SN=…&options=all&pushver=…&DeviceType=…&FWVersion=…`
The server answers with a newline-separated option block. From the Laravel
implementation, verbatim:

```
GET OPTION FROM: <serial>
Stamp=<attendance cursor>
OpStamp=<operation cursor>
ErrorDelay=60
Delay=30
TransTimes=10
TransInterval=1
TransFlag=TransData AttLog OpLog AttPhoto
Realtime=1
Encrypt=0
```

`Delay` is the poll interval in seconds; `Realtime=1` asks the device to push
immediately rather than batch. **`Stamp` is a resume cursor** — the server echoes
back the highest stamp it has seen, and the device sends what came after it. It is
the protocol's own answer to "what have you already got", and it is worth honouring
rather than relying on our idempotency key alone.

**Attendance push.** `POST /iclock/cdata?SN=…&table=ATTLOG&Stamp=…`, body of
tab-separated rows, one punch per line:

```
UserID \t YYYY-MM-DD HH:MM:SS \t Status \t VerifyMode \t WorkCode
```

Timestamps arrive either in that format or as a Unix epoch integer — the Go parser
accepts both, and so should we. Reply `OK` (the Go library replies `OK: <count>`).

**Commands.** The device polls `GET /iclock/getrequest?SN=…`; the server replies
either `OK` (nothing pending) or one command per line:

```
C:<id>:<command>
```

The device executes and POSTs to `/iclock/devicecmd` with
`ID=<id>&Return=<code>&CMD=<command>`, where `Return=0` means success. The `id` is
ours, assigned when we queue it, which is what makes the round trip correlatable.

## 4. Two things worth having

### 4.1 `VerifyMode` tells us *how* the person was recognised

Per punch. From the Go library's table, with its own note that codes vary by
firmware:

| Code | Meaning | | Code | Meaning |
|---|---|---|---|---|
| 0, 3 | Password | | 5 | Fingerprint + Card |
| 1 | Fingerprint | | 6 | Fingerprint + Password |
| 2, 4 | Card | | 7 | Card + Password |
| 15 | Face | | 8 | Card + Fingerprint + Password |
| 25 | Palm | | 9 | Other |

This lands directly on plan §2.4. A punch verified by fingerprint is *evidence*,
and a punch verified by password on the same terminal is much weaker — same device,
same outlet, different trust. We can record the distinction rather than treating
every terminal punch alike, and `flags` is the obvious home for it.

Note the firmware variation: **treat an unrecognised code as unknown and flag it**,
never as a default of 0 (which would silently read "password").

### 4.2 We can push employee records to the terminal

The commands the Laravel package builds — this is the full useful set:

| Command | Wire form |
|---|---|
| Add / update a user | `DATA USER PIN=<id>\tName=<name>\tCard=…\tPri=0\tPasswd=…\tGrp=1` |
| Delete a user | `DATA DEL_USER PIN=<id>` |
| Clear attendance log | `CLEAR LOG` |
| Clear all data / users | `CLEAR DATA` / `CLEAR USER` |
| Set device clock | `SET OPTIONS ServerLocalTime=<Y-m-d H:i:s>` |
| Device info | `INFO` |
| Liveness | `CHECK` |
| Restart | `REBOOT` |

`DATA USER` is the one that matters. **`PIN` is the device's user ID, so Servora
can push `employees.staff_id` as the PIN and own the mapping** — nobody keys a
staff number at the terminal, joiners appear automatically, leavers can be removed
from Servora with `DATA DEL_USER`, and the "unmapped ID" case in plan §2.2 becomes
rare rather than routine.

**It does not push fingerprints.** The finger still has to be physically enrolled
at each device by each person. Plan §2.5 stands as the real cost — but it shrinks
to only the biometric step.

## 5. Authentication: there isn't any

This is the finding that matters, and it is worse than "weak".

**Identity is the serial number in a query string.** No token, no signature, no
challenge, no session. Every request is independent and self-asserting.

What the three implementations actually do with an unknown serial:

- **The Laravel/Filament package auto-creates a device row for it.**
  `findOrCreateDevice()` looks the SN up and, failing that, creates it — and the
  config flag governing this, `device.auto_register`, **defaults to `true`**. Its
  HTTP layer and routes contain no middleware, no shared secret, no allowlist: a
  grep for auth of any kind across its controllers and routes returns nothing.
- **The Go library** registers the SN too, bounded only by a `maxDevices` cap. Its
  author was plainly aware of the problem — the docs for the inspect endpoint note
  it serves device data "without authentication" and suggest putting it behind an
  authenticated route — but the data endpoints have none either.
- **The deployment-focused Laravel repo** resorts to `.htaccess` passwords on
  `/iclock/*`, explaining that otherwise "anyone on the same network can access
  these routes".

Odoo module listings describe "device authentication by serial number". A serial
number printed on the back of a box is an identifier, not a credential. The same
listings say the push model "eliminates the need to expose your server to the
public internet" — true for a LAN-hosted Odoo, **and not transferable to us**:
Servora is already public, so for us this is an unauthenticated write endpoint on
the open internet that creates attendance records, which is to say payroll records.

Ship none of these as-is. The receiver has to add what the protocol omits:

1. **Claim before counting.** An unknown SN is quarantined, never auto-registered.
   A manager claims it in Servora — the `ClockDevice` pairing flow, with the code
   read off the device rather than the screen. Only a claimed terminal's punches
   reach the writer. **Explicitly the opposite of `auto_register`.**
2. **A secret in the path.** The server address is a device menu field, so it can
   carry an unguessable per-company segment: `/adms/<random>/iclock/cdata`. Crude,
   but it is the only place in the protocol we control that the device will carry
   back to us on every request.
3. **TLS, and confirm it before buying** — plan §3.2's condition, unchanged. Plain
   HTTP is the protocol's default and older firmware offers nothing else.
4. **Rate limit per SN**, cap the body, and treat every field as hostile input.
   The parsers above skip malformed lines and log them; ours should quarantine
   them, because a line we could not parse is a punch somebody made.

## 6. What this changes in the plan

**Cheaper than assumed**, two ways: `DATA USER` means Servora owns the identity
mapping instead of somebody typing staff numbers into a terminal (§2.2, §2.5), and
`VerifyMode` gives per-punch modality for free, which the flag work in §2.4 wanted
anyway.

**Stricter than assumed**, one way, and it is a correctness issue rather than a
security one: **the device stamps the punch time, and lateness is computed from
that stamp.** A terminal with a drifting clock does not fail loudly — it quietly
charges the wrong late minutes against somebody's service charge. `SET OPTIONS
ServerLocalTime` is the fix, but it has to be sent on a schedule, and drift beyond
a threshold has to be *surfaced*, not silently corrected. That belongs in §2.1 next
to the writer.

None of this changes the §6 recommendation, and the protocol being this well-trodden
mildly strengthens it: there is a Laravel package to read for the parsing, tested Go
to check our understanding against, and the failure modes are already documented by
other people's mistakes.

## 7. Still open

1. **Which SKU, and does its firmware do HTTPS?** (plan §7 Q2 — unchanged, still
   the condition the recommendation rests on.)
2. **Does `Realtime=1` behave on the chosen model**, or does it batch regardless?
   Decides whether punches land in seconds or minutes.
3. **What `Status` actually contains on that firmware** — nominally the in/out
   code, but it is the field most likely to differ per model, and it is how we
   would know a punch was a clock-out.
4. **Does the device accept a long URL path** for the secret segment in §5.2? Menu
   fields have length limits, and this needs testing on hardware, not guessing.

Questions 2–4 are all answered by the same afternoon with one terminal on a desk —
the §3.3 phase-1 spike, which this makes more clearly worth doing first.
