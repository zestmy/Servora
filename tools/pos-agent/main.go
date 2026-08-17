package main

import (
	"fmt"
	"os"
)

// Version is reported at pair and ping so the POS Sync screen can nag about
// stale installs. 0.x while the tool is discovery-only; 1.0.0 lands with the
// sync loop, and from then on the server's PosAgent::CURRENT_VERSION must be
// bumped in the same commit (a drift test enforces it).
const Version = "0.1.0"

const usage = `Servora POS Agent v` + Version + `

Syncs sales data from the POS machine to Servora. This build ships the
discovery step only: run it once on a POS terminal and send the report it
writes back to whoever manages your Servora setup.

Usage:
  servora-pos-agent discover    inventory this machine's POS data sources
  servora-pos-agent version     print the version
`

func main() {
	cmd := ""
	if len(os.Args) > 1 {
		cmd = os.Args[1]
	}

	switch cmd {
	case "discover":
		path, err := runDiscovery()
		if err != nil {
			fmt.Fprintln(os.Stderr, "discovery failed:", err)
			os.Exit(1)
		}
		fmt.Printf("Discovery report written to:\n  %s\nSend this file back for review.\n", path)
	case "version", "--version", "-v":
		fmt.Println(Version)
	default:
		fmt.Print(usage)
		os.Exit(2)
	}
}
