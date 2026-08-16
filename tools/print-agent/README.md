# Servora Print Agent

The outlet-PC half of Servora's self-hosted label printing — the piece that
replaces PrintNode's client and subscription. Design and server half:
[docs/print-agent-plan.md](../../docs/print-agent-plan.md).

One small Windows program: it pairs with your Servora subdomain once, then
polls for label jobs, prints each PDF to the right local printer through the
bundled SumatraPDF, and reports done/error back. Outbound HTTPS only — no
ports to open, works behind NAT.

## Building the install zip

Needs Go 1.24+, `curl`, `zip`:

```
./build.sh
```

Produces `dist/servora-print-agent-<version>-windows-amd64.zip` containing the
agent, a pinned SumatraPDF (>= 3.4 is required for `paper=`; never use a
machine-installed copy), its GPLv3 notice, and this file.

## Outlet setup (per PC, once)

1. Get the install zip — the **Download Agent** button on **Labels →
   Print Agents** — and unzip it anywhere permanent, e.g.
   `C:\Servora\PrintAgent\`. The agent needs `SumatraPDF.exe` sitting
   beside it.
2. In Servora: **Labels → Print Agents → Add Agent**, name the PC, pick its
   outlet, and read the pairing code off the screen (valid 10 minutes).
3. Double-click **`SETUP.cmd`** in the unzipped folder and accept the
   administrator prompt. It asks two questions — the server address shown
   on the Agents screen (e.g. `https://yourcompany.servora.com.my/agent`)
   and the pairing code — then installs the Windows service. The service
   starts now, survives reboots and logouts, and needs nobody signed in.
   The token is saved to `%ProgramData%\Servora\PrintAgent\config.json` —
   no human ever sees it.

   Prefer a terminal, or need to script it? The same two steps by hand:
   `servora-print-agent.exe pair`, then `servora-print-agent.exe install`
   from an Administrator terminal.
4. Back in Servora: **Labels → Label Printers** — set the printer's driver to
   *Servora agent*, pick this agent, pick the Windows printer it reported,
   and pick the **paper form** matching your label stock.

### The paper form matters — same lesson as every other print path

If the printer's driver doesn't already have a form for your label size
(e.g. 70 × 40 mm), create it once: **Windows Settings → Printers → Print
Server Properties → Forms**, then select it in the agent printer's *paper*
dropdown in Servora. Also set it as the printer's default preference, so
even a job with no form named lands on the right stock. If output comes out
rotated or shrunk, the paper form is the first thing to check — see
"Printer setup, learned the hard way" in the label printing plan.

SmartScreen note: the exe is unsigned in v1. Windows will show
"Windows protected your PC" — click **More info → Run anyway**.

## Commands

| Command | Does |
|---|---|
| `pair` | Interactive first-run pairing |
| `install` / `uninstall` | Add/remove the Windows service (run as Administrator) |
| `run` | Foreground run (what the service executes; handy for debugging) |
| `version` | Print the agent version |

Logs: `%ProgramData%\Servora\PrintAgent\agent.log` (rotated once at 5 MB).
Per-job errors also land on the job row in Servora, so support can read them
without touching the PC.

## Behaviour worth knowing

- Polls every 3 seconds; the poll doubles as the heartbeat behind the
  printer's online/offline badge. No idle backoff, on purpose.
- A job the server queued but nobody collected expires after 10 minutes.
- If the agent crashes mid-print, the server re-offers the job after
  2 minutes — a duplicate label beats a missing one on a food-safety trail.
- A 401 means the agent was revoked or the token cleared: it logs
  `UNPAIRED`, slows to one attempt a minute, and waits for a human to
  `pair` again.
- `done` means "handed to the Windows spooler and the print command exited
  cleanly" — the same honesty PrintNode's `done` had.

## Development

Builds and runs on Linux/macOS too (CUPS `lp` / `lpstat`) for development
against a local Servora — where routes mount at `http://localhost/agent-api`.
Tests fake the server with `httptest` and the hardware behind two
interfaces:

```
go test ./...
```
