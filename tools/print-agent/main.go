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

// Version is reported at pair and poll so the Agents screen can nag about
// stale installs — the only update mechanism v1 has.
const Version = "1.0.0"

const usage = `Servora Print Agent v` + Version + `

Prints labels sent from Servora to printers on this PC. See
docs/print-agent-plan.md in the Servora repository.

Usage:
  servora-print-agent pair        interactive pairing (server URL + code)
  servora-print-agent run         run in the foreground (what the service runs)
  servora-print-agent install     install and start the Windows service
  servora-print-agent uninstall   stop and remove the Windows service
  servora-print-agent version     print the version
`

func main() {
	cmd := "run"
	if len(os.Args) > 1 {
		cmd = os.Args[1]
	}

	switch cmd {
	case "pair":
		if err := pairInteractive(); err != nil {
			fmt.Fprintln(os.Stderr, "pairing failed:", err)
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

// pairInteractive is first-run setup: the human at the PC types the server
// address and the code a manager read out. The token in the response goes
// straight into the config file — no eyes on it, by design.
func pairInteractive() error {
	in := bufio.NewReader(os.Stdin)

	fmt.Print("Server address (e.g. https://yourcompany.servora.com.my/agent): ")
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

	hostname, _ := os.Hostname()

	result, err := NewClient(server, "").Pair(code, hostname, runtime.GOOS, Version)
	if err != nil {
		return err
	}

	cfg := &Config{ServerURL: server, Token: result.Token, PollSeconds: DefaultPollSeconds}
	if err := saveConfig(cfg); err != nil {
		return fmt.Errorf("paired, but could not save the config: %w", err)
	}

	fmt.Printf("Paired as %q (outlet %s). Config saved to %s.\n", result.Agent, result.Outlet, configPath())
	fmt.Println("Now run:  servora-print-agent install   (or `run` for the foreground)")
	return nil
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

	agent := NewAgent(cfg, NewClient(cfg.ServerURL, cfg.Token), newEnumerator(), newExecutor(), logger)

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
		Name:        "ServoraPrintAgent",
		DisplayName: "Servora Print Agent",
		Description: "Prints labels sent from Servora to printers on this PC.",
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
