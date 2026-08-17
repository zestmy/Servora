# Servora POS Agent

Syncs sales data from a POS terminal to Servora automatically, replacing the
manual "export the Zeoniq report, log in, upload it" routine.

**This build is the discovery step only.** Before the sync agent can be
finished we need to know where the POS software on your terminals keeps its
data, and machines differ — so v0.1 ships one command that inventories a
terminal and writes a report.

## Running discovery on a POS terminal

1. Copy `servora-pos-agent.exe` onto the terminal (USB stick is fine —
   if you're unsure whether the machine is 32- or 64-bit, take both zips
   and try the amd64 one first; a 32-bit machine will refuse to run it).
2. Open Command Prompt in that folder and run:

   ```
   servora-pos-agent.exe discover
   ```

3. It scans for a minute or two and writes
   `pos-agent-discovery-<computer name>.zip` beside the exe.
4. Send that zip back to whoever manages your Servora setup.

The report contains: Windows version and architecture, installed software
that looks POS-related, candidate database files, ODBC data sources,
non-Microsoft scheduled tasks, POS-related services/processes, and recent
Excel/CSV report files. It does **not** contain sales figures, passwords, or
file contents (only the first row of CSV files, to identify report layouts).

Running it needs no administrator rights and changes nothing on the machine.

## What comes next

The discovery reports decide how the sync agent reads sales data (watching a
report-export folder, querying the POS database directly, or a vendor API).
The full agent — pairing with Servora, background service, automatic upload —
ships as v1.0 in this same folder, mirroring `tools/print-agent`.

## Building

Needs Go 1.24+ and `zip`:

```
./build.sh
```

Writes `dist/servora-pos-agent-<version>-windows-amd64.zip` and `-386.zip`.
