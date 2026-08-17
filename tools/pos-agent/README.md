# Servora POS Agent

Syncs sales data from a POS terminal to Servora automatically, replacing the
manual "export the Zeoniq report, log in, upload it" routine. Runs as a
Windows service; pairs once with a code a manager issues under
**Sales › POS Sync**.

v1 watches a folder the POS exports report files into (Zeoniq's own
scheduled export, or a "save it here" habit), uploads anything new, and the
server parses and applies it — unmapped departments or conflicts park for
review in Servora instead of importing wrongly. Reports are spooled locally
first, so an offline weekend uploads itself when the network returns, and
re-sends are free (the server dedupes by content).

## Setting up a terminal

1. Extract the zip on the POS terminal (64-bit build first; if Windows
   refuses to run it, use the 386 build).
2. In Servora, a manager adds the terminal under **Sales › POS Sync** —
   this shows the server address and a pairing code (valid 10 minutes).
3. Double-click `SETUP.cmd`. It asks for the address, the code, and the
   folder the POS exports reports into, then installs the service.
4. Export a report from the POS and run `servora-pos-agent.exe sync` —
   the upload appears under Sales › POS Sync within moments.

Not sure where the POS keeps or exports its data? Run

```
servora-pos-agent.exe discover
```

first — it inventories the machine (installed POS software, database
files, ODBC sources, scheduled exports, recent report files) into a zip to
send back, and needs no admin rights.

## Commands

```
servora-pos-agent discover    inventory this machine's POS data sources
servora-pos-agent pair        interactive pairing (server URL + code)
servora-pos-agent sync        one manual sync pass, then exit
servora-pos-agent run         run in the foreground (what the service runs)
servora-pos-agent install     install + start the Windows service (admin)
servora-pos-agent uninstall   stop + remove the service (admin)
servora-pos-agent version     print the version
```

Config lives at `%ProgramData%\Servora\PosAgent\config.json` (the token is
the one secret in it); the local log and the offline spool sit beside it.

## Building

Needs Go 1.24+ and `zip`:

```
./build.sh
```

Writes `dist/servora-pos-agent-<version>-windows-amd64.zip` and `-386.zip`.
Bump `const Version` in main.go and `PosAgent::CURRENT_VERSION` on the
server together — `PosAgentTest` fails on drift — and host the new zips in
`public/downloads/` so the downloads pages pick them up.

Builds and runs on Linux/macOS too for development:

```
go test ./...
```
