package main

import (
	"encoding/json"
	"errors"
	"fmt"
	"os"
	"path/filepath"
	"runtime"
)

// Config is the agent's persistent state: where the server is, the token
// pairing produced, and where to look for report files. One JSON file,
// written at pairing and read at every start. The sync interval is also
// pushed by the server on every ping — the stored value is just the
// starting point.
type Config struct {
	ServerURL           string   `json:"server_url"`
	Token               string   `json:"token"`
	SyncIntervalMinutes int      `json:"sync_interval_minutes"`
	Extractor           string   `json:"extractor"`
	WatchPaths          []string `json:"watch_paths"`
	FilePatterns        []string `json:"file_patterns"`
	MinFileAgeSeconds   int      `json:"min_file_age_seconds"`
}

const (
	// DefaultSyncIntervalMinutes: sales sync is latency-tolerant — the
	// numbers are read tomorrow morning, not in three seconds. Hourly keeps
	// the day's figures fresh without giving the 2 GB server a fleet-wide
	// stampede.
	DefaultSyncIntervalMinutes = 60

	// DefaultMinFileAgeSeconds: never upload a file Zeoniq or Excel may
	// still be writing. Two minutes of quiet is "the export finished".
	DefaultMinFileAgeSeconds = 120

	// PingIntervalMinutes drives the online badge and config adoption.
	// The server treats an agent silent for 12 minutes as offline — two
	// missed pings plus jitter.
	PingIntervalMinutes = 5
)

func defaultFilePatterns() []string {
	return []string{"*.xlsx", "*.xls", "*.csv"}
}

// configDir is %ProgramData%\Servora\PosAgent on Windows — machine-wide,
// because the agent runs as a service with no user profile — and the
// platform config dir elsewhere (dev machines).
func configDir() string {
	if runtime.GOOS == "windows" {
		base := os.Getenv("ProgramData")
		if base == "" {
			base = `C:\ProgramData`
		}
		return filepath.Join(base, "Servora", "PosAgent")
	}

	base, err := os.UserConfigDir()
	if err != nil {
		base = "."
	}
	return filepath.Join(base, "servora-pos-agent")
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

	cfg.applyDefaults()

	return cfg, nil
}

func (cfg *Config) applyDefaults() {
	if cfg.SyncIntervalMinutes <= 0 {
		cfg.SyncIntervalMinutes = DefaultSyncIntervalMinutes
	}
	if cfg.Extractor == "" {
		cfg.Extractor = "folder"
	}
	if len(cfg.FilePatterns) == 0 {
		cfg.FilePatterns = defaultFilePatterns()
	}
	if cfg.MinFileAgeSeconds <= 0 {
		cfg.MinFileAgeSeconds = DefaultMinFileAgeSeconds
	}
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
			"not paired yet — run:  servora-pos-agent pair\n(config expected at %s)", configPath())
	}
	return cfg, err
}
