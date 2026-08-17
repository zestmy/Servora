package main

import (
	"os"
	"path/filepath"
	"time"
)

// FolderExtractor watches one or more directories a report export lands in
// — Zeoniq's own scheduled export, or a "save it here" habit. The simplest
// extractor that removes the manual upload, and the fallback whatever the
// discovery step finds.
type FolderExtractor struct{}

// exportWindowDays bounds how far back a watched folder is read. Anything
// older was either synced long ago or predates the agent — and a folder of
// years of exports must not become a first-run upload storm.
const exportWindowDays = 30

func (e *FolderExtractor) Name() string { return "folder" }

func (e *FolderExtractor) Collect(cfg *Config) ([]string, error) {
	var out []string

	cutoff := time.Now().AddDate(0, 0, -exportWindowDays)
	minAge := time.Duration(cfg.MinFileAgeSeconds) * time.Second

	for _, dir := range cfg.WatchPaths {
		entries, err := os.ReadDir(dir)
		if err != nil {
			// A missing watch path is a configuration problem to log, not a
			// reason to stop scanning the others.
			continue
		}

		for _, entry := range entries {
			if entry.IsDir() {
				continue
			}

			matched := false
			for _, pattern := range cfg.FilePatterns {
				if ok, _ := filepath.Match(pattern, entry.Name()); ok {
					matched = true
					break
				}
			}
			if !matched {
				continue
			}

			info, err := entry.Info()
			if err != nil {
				continue
			}

			// Too old: outside the window. Too new: possibly still being
			// written — wait for the next pass.
			if info.ModTime().Before(cutoff) || time.Since(info.ModTime()) < minAge {
				continue
			}

			out = append(out, filepath.Join(dir, entry.Name()))
		}
	}

	return out, nil
}
