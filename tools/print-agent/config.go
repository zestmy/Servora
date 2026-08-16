package main

import (
	"encoding/json"
	"errors"
	"fmt"
	"os"
	"path/filepath"
	"runtime"
)

// Config is the agent's entire persistent state: where the server is, the
// token pairing produced, and how often to poll. One JSON file, written once
// at pairing and read at every start — there is nothing else to remember,
// because the server owns the printers, the jobs and the outlet.
type Config struct {
	ServerURL   string `json:"server_url"`
	Token       string `json:"token"`
	PollSeconds int    `json:"poll_seconds"`
}

// DefaultPollSeconds matches the server-side design: a short fixed poll,
// no idle backoff — backoff would trade the first label after a lull, the
// exact case where a chef is standing at the printer.
const DefaultPollSeconds = 3

// configDir is %ProgramData%\Servora\PrintAgent on Windows — machine-wide,
// because the agent runs as a service with no user profile — and the
// platform config dir elsewhere (dev machines).
func configDir() string {
	if runtime.GOOS == "windows" {
		base := os.Getenv("ProgramData")
		if base == "" {
			base = `C:\ProgramData`
		}
		return filepath.Join(base, "Servora", "PrintAgent")
	}

	base, err := os.UserConfigDir()
	if err != nil {
		base = "."
	}
	return filepath.Join(base, "servora-print-agent")
}

func configPath() string {
	return filepath.Join(configDir(), "config.json")
}

func loadConfig() (*Config, error) {
	raw, err := os.ReadFile(configPath())
	if err != nil {
		return nil, err
	}

	cfg := &Config{}
	if err := json.Unmarshal(raw, cfg); err != nil {
		return nil, fmt.Errorf("config file %s is not valid JSON: %w", configPath(), err)
	}

	if cfg.PollSeconds <= 0 {
		cfg.PollSeconds = DefaultPollSeconds
	}

	return cfg, nil
}

func saveConfig(cfg *Config) error {
	if err := os.MkdirAll(configDir(), 0o755); err != nil {
		return err
	}

	raw, err := json.MarshalIndent(cfg, "", "  ")
	if err != nil {
		return err
	}

	// 0600: the token is the one secret this program holds.
	return os.WriteFile(configPath(), raw, 0o600)
}

// requireConfig is what `run` starts with: a missing config is not a crash,
// it is the instruction to pair.
func requireConfig() (*Config, error) {
	cfg, err := loadConfig()
	if errors.Is(err, os.ErrNotExist) {
		return nil, fmt.Errorf(
			"not paired yet — run:  servora-print-agent pair\n(config expected at %s)", configPath())
	}
	return cfg, err
}
