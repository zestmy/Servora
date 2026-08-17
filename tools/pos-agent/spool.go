package main

import (
	"crypto/sha256"
	"encoding/hex"
	"encoding/json"
	"fmt"
	"io"
	"os"
	"path/filepath"
	"sort"
	"strings"
	"time"
)

// Spool is the agent's offline tolerance: every collected report is copied
// into a local spool directory and only deleted once the server confirms it
// (received or duplicate). A weekend of dead Wi-Fi uploads itself Monday
// morning; a crash mid-upload re-sends the same bytes, which the server's
// sha dedupe makes free.
//
// ledger.json remembers every sha ever handled — spooled, sent, or evicted
// — so the extractor can hand us the same folder listing every hour and
// nothing is copied or uploaded twice.
type Spool struct {
	dir string
}

// spoolCapBytes bounds the spool. A report is ~10 KB, so this is years of
// backlog; hitting it means something is deeply wrong, and dropping the
// OLDEST is right because a fresh report supersedes stale ones anyway.
const spoolCapBytes = 200 << 20

type ledgerEntry struct {
	SourcePath string    `json:"source_path"`
	SpooledAt  time.Time `json:"spooled_at"`
	UploadedAt time.Time `json:"uploaded_at,omitzero"`
	BatchID    int64     `json:"batch_id,omitempty"`
}

func NewSpool(dir string) *Spool {
	return &Spool{dir: dir}
}

func (s *Spool) ledgerPath() string { return filepath.Join(s.dir, "ledger.json") }

func (s *Spool) loadLedger() map[string]ledgerEntry {
	raw, err := os.ReadFile(s.ledgerPath())
	if err != nil {
		return map[string]ledgerEntry{}
	}

	ledger := map[string]ledgerEntry{}
	if err := json.Unmarshal(raw, &ledger); err != nil {
		return map[string]ledgerEntry{}
	}
	return ledger
}

func (s *Spool) saveLedger(ledger map[string]ledgerEntry) error {
	if err := os.MkdirAll(s.dir, 0o755); err != nil {
		return err
	}
	raw, err := json.MarshalIndent(ledger, "", "  ")
	if err != nil {
		return err
	}
	return os.WriteFile(s.ledgerPath(), raw, 0o644)
}

// Add copies a collected file into the spool unless its bytes were already
// handled. Returns true when the file is new.
func (s *Spool) Add(sourcePath string) (bool, error) {
	sha, err := fileSha256(sourcePath)
	if err != nil {
		return false, err
	}

	ledger := s.loadLedger()
	if _, known := ledger[sha]; known {
		return false, nil
	}

	if err := os.MkdirAll(s.dir, 0o755); err != nil {
		return false, err
	}

	ext := strings.ToLower(filepath.Ext(sourcePath))
	dst := filepath.Join(s.dir, sha+ext)

	if err := copyFile(sourcePath, dst); err != nil {
		return false, err
	}

	ledger[sha] = ledgerEntry{SourcePath: sourcePath, SpooledAt: time.Now()}
	if err := s.saveLedger(ledger); err != nil {
		return false, err
	}

	s.evictOverCap(ledger)

	return true, nil
}

// Pending returns spool files still awaiting a server confirmation, oldest
// first — replays should arrive in the order the reports appeared.
func (s *Spool) Pending() []string {
	entries, err := os.ReadDir(s.dir)
	if err != nil {
		return nil
	}

	type pending struct {
		path string
		mod  time.Time
	}
	var out []pending

	for _, entry := range entries {
		if entry.IsDir() || entry.Name() == "ledger.json" {
			continue
		}
		info, err := entry.Info()
		if err != nil {
			continue
		}
		out = append(out, pending{filepath.Join(s.dir, entry.Name()), info.ModTime()})
	}

	sort.Slice(out, func(i, j int) bool { return out[i].mod.Before(out[j].mod) })

	paths := make([]string, len(out))
	for i, p := range out {
		paths[i] = p.path
	}
	return paths
}

// MarkSent records the server's confirmation and drops the spool copy.
func (s *Spool) MarkSent(spoolPath string, batchID int64) {
	sha := strings.TrimSuffix(filepath.Base(spoolPath), filepath.Ext(spoolPath))

	ledger := s.loadLedger()
	if entry, ok := ledger[sha]; ok {
		entry.UploadedAt = time.Now()
		entry.BatchID = batchID
		ledger[sha] = entry
		_ = s.saveLedger(ledger)
	}

	_ = os.Remove(spoolPath)
}

// SourceOf answers where a spool file originally came from, for the upload's
// filename and the server's source_path field.
func (s *Spool) SourceOf(spoolPath string) string {
	sha := strings.TrimSuffix(filepath.Base(spoolPath), filepath.Ext(spoolPath))
	if entry, ok := s.loadLedger()[sha]; ok && entry.SourcePath != "" {
		return entry.SourcePath
	}
	return spoolPath
}

func (s *Spool) evictOverCap(ledger map[string]ledgerEntry) {
	pending := s.Pending()

	var total int64
	sizes := map[string]int64{}
	for _, p := range pending {
		if info, err := os.Stat(p); err == nil {
			sizes[p] = info.Size()
			total += info.Size()
		}
	}

	for _, p := range pending {
		if total <= spoolCapBytes {
			break
		}
		_ = os.Remove(p)
		total -= sizes[p]
	}

	_ = ledger // ledger entries stay: evicted bytes were still "handled".
}

func fileSha256(path string) (string, error) {
	f, err := os.Open(path)
	if err != nil {
		return "", err
	}
	defer f.Close()

	h := sha256.New()
	if _, err := io.Copy(h, f); err != nil {
		return "", err
	}
	return hex.EncodeToString(h.Sum(nil)), nil
}

func copyFile(src, dst string) error {
	in, err := os.Open(src)
	if err != nil {
		return err
	}
	defer in.Close()

	out, err := os.Create(dst)
	if err != nil {
		return err
	}
	defer out.Close()

	if _, err := io.Copy(out, in); err != nil {
		return fmt.Errorf("copying %s: %w", src, err)
	}
	return out.Close()
}
