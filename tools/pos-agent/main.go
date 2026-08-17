package main

import (
	"bufio"
	"context"
	"fmt"
	"log"
	"os"
	"path/filepath"
	"runtime"
	"strings"

	"github.com/kardianos/service"
)

// Version is reported at pair, ping and upload so the POS Sync screen can
// nag about stale installs — the only update mechanism v1 has. MUST match
// PosAgent::CURRENT_VERSION on the server; a drift test reads this file.
const Version = "1.0.0"

const usage = `Servora POS Agent v` + Version + `

Syncs sales data from this POS terminal to Servora: watches for exported
Zeoniq report files and uploads them automatically.

Usage:
  servora-pos-agent discover    inventory this machine's POS data sources
  servora-pos-agent pair        interactive pairing (server URL + code)
  servora-pos-agent sync        one manual sync pass, then exit
  servora-pos-agent run         run in the foreground (what the service runs)
  servora-pos-agent install     install and start the Windows service
  servora-pos-agent uninstall   stop and remove the Windows service
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
	case "pair":
		if err := pairInteractive(); err != nil {
			fmt.Fprintln(os.Stderr, "pairing failed:", err)
			os.Exit(1)
		}
	case "sync":
		if err := syncOnce(); err != nil {
			fmt.Fprintln(os.Stderr, err)
			os.Exit(1)
		}
	case "run", "install", "uninstall", "start", "stop":
		if err := runService(cmd); err != nil {
			fmt.Fprintln(os.Stderr, err)
			os.Exit(1)
		}
	case "version", "--version", "-v":
		fmt.Println(Version)
	default:
		fmt.Print(usage)
		os.Exit(2)
	}
}

// pairInteractive is first-run setup: the human at the terminal types the
// server address, the code a manager read out, and the folder the POS
// exports its reports into. The token in the response goes straight into
// the config file — no eyes on it, by design.
func pairInteractive() error {
	in := bufio.NewReader(os.Stdin)

	fmt.Print("Server address (e.g. https://yourcompany.servora.com.my/pos-agent): ")
	server, _ := in.ReadString('\n')
	server = strings.TrimSpace(server)
	if server == "" {
		return fmt.Errorf("a server address is required")
	}
	if !strings.HasPrefix(server, "https://") && !strings.HasPrefix(server, "http://") {
		server = "https://" + server
	}

	fmt.Print("Pairing code: ")
	code, _ := in.ReadString('\n')
	code = strings.TrimSpace(code)
	if code == "" {
		return fmt.Errorf("a pairing code is required")
	}

	fmt.Print("Folder the POS exports reports into (e.g. C:\\Zeoniq\\Reports): ")
	watch, _ := in.ReadString('\n')
	watch = strings.TrimSpace(strings.Trim(strings.TrimSpace(watch), `"`))
	if watch == "" {
		return fmt.Errorf("a report folder is required — run discovery if unsure where reports land")
	}
	if info, err := os.Stat(watch); err != nil || !info.IsDir() {
		return fmt.Errorf("%s is not a folder this machine can see", watch)
	}

	hostname, _ := os.Hostname()

	result, err := NewClient(server, "").Pair(code, hostname, runtime.GOOS, Version)
	if err != nil {
		return err
	}

	cfg := &Config{ServerURL: server, Token: result.Token, WatchPaths: []string{watch}}
	cfg.applyDefaults()
	if err := saveConfig(cfg); err != nil {
		return fmt.Errorf("paired, but could not save the config: %w", err)
	}

	fmt.Printf("Paired as %q (outlet %s). Config saved to %s.\n", result.Agent, result.Outlet, configPath())
	fmt.Println("Now run:  servora-pos-agent install   (or `sync` for a one-off pass)")
	return nil
}

// syncOnce is the manual escape hatch: one collect-spool-drain pass with
// the log on stdout, for testing a setup before installing the service.
func syncOnce() error {
	cfg, err := requireConfig()
	if err != nil {
		return err
	}

	agent := newAgentFromConfig(cfg, log.New(os.Stdout, "", log.LstdFlags))
	agent.SyncOnce()
	return nil
}

func newAgentFromConfig(cfg *Config, logger *log.Logger) *Agent {
	return NewAgent(
		cfg,
		NewClient(cfg.ServerURL, cfg.Token),
		newExtractor(cfg),
		NewSpool(filepath.Join(configDir(), "spool")),
		logger,
	)
}

// program adapts the agent loop to kardianos/service, which handles the
// Windows service control protocol and degrades to a plain foreground run
// elsewhere.
type program struct {
	cancel context.CancelFunc
	done   chan struct{}
}

func (p *program) Start(service.Service) error {
	cfg, err := requireConfig()
	if err != nil {
		return err
	}

	logger, closeLog, err := openLog()
	if err != nil {
		return err
	}

	ctx, cancel := context.WithCancel(context.Background())
	p.cancel = cancel
	p.done = make(chan struct{})

	agent := newAgentFromConfig(cfg, logger)

	go func() {
		defer close(p.done)
		defer closeLog()
		agent.Run(ctx)
	}()

	return nil
}

func (p *program) Stop(service.Service) error {
	if p.cancel != nil {
		p.cancel()
		<-p.done
	}
	return nil
}

func runService(cmd string) error {
	svcConfig := &service.Config{
		Name:        "ServoraPosAgent",
		DisplayName: "Servora POS Agent",
		Description: "Syncs sales data from this POS terminal to Servora.",
		Arguments:   []string{"run"},
	}

	svc, err := service.New(&program{}, svcConfig)
	if err != nil {
		return err
	}

	switch cmd {
	case "install":
		// Fail here, not at 3am: an unpaired service would start, log one
		// line and die on every boot forever.
		if _, err := requireConfig(); err != nil {
			return err
		}
		if err := svc.Install(); err != nil {
			return fmt.Errorf("install failed (run as Administrator?): %w", err)
		}
		if err := svc.Start(); err != nil {
			return fmt.Errorf("installed, but start failed: %w", err)
		}
		fmt.Println("Service installed and started.")
		return nil
	case "uninstall":
		_ = svc.Stop()
		if err := svc.Uninstall(); err != nil {
			return fmt.Errorf("uninstall failed: %w", err)
		}
		fmt.Println("Service removed.")
		return nil
	case "start":
		return svc.Start()
	case "stop":
		return svc.Stop()
	default: // run
		return svc.Run()
	}
}

// openLog writes beside the config with a one-deep size rotation — enough
// history to debug yesterday, no daemon slowly filling a disk nobody
// watches.
func openLog() (*log.Logger, func(), error) {
	if err := os.MkdirAll(configDir(), 0o755); err != nil {
		return nil, nil, err
	}

	path := filepath.Join(configDir(), "agent.log")

	if info, err := os.Stat(path); err == nil && info.Size() > 5<<20 {
		_ = os.Rename(path, path+".old")
	}

	f, err := os.OpenFile(path, os.O_CREATE|os.O_APPEND|os.O_WRONLY, 0o644)
	if err != nil {
		return nil, nil, err
	}

	return log.New(f, "", log.LstdFlags), func() { _ = f.Close() }, nil
}
