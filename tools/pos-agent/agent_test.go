package main

import (
	"encoding/json"
	"log"
	"net/http"
	"net/http/httptest"
	"os"
	"path/filepath"
	"testing"
	"time"
)

// The wire surface faked with httptest, the file system faked with t.TempDir
// — the whole agent is exercised with no server and no service manager.

func testConfig(t *testing.T, serverURL, watch string) *Config {
	t.Helper()
	cfg := &Config{ServerURL: serverURL, Token: "test-token", WatchPaths: []string{watch}}
	cfg.applyDefaults()
	cfg.MinFileAgeSeconds = 0 // tests write files "now"
	return cfg
}

func writeFile(t *testing.T, dir, name, content string) string {
	t.Helper()
	path := filepath.Join(dir, name)
	if err := os.WriteFile(path, []byte(content), 0o644); err != nil {
		t.Fatal(err)
	}
	return path
}

func TestPairReturnsToken(t *testing.T) {
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		if r.URL.Path != "/pair" {
			t.Errorf("unexpected path %s", r.URL.Path)
		}
		_ = json.NewEncoder(w).Encode(map[string]any{
			"status": "paired", "token": "raw-token-value",
			"agent": map[string]string{"name": "Till", "outlet": "KLCC"},
		})
	}))
	defer srv.Close()

	result, err := NewClient(srv.URL, "").Pair("ABC234", "host", "windows", Version)
	if err != nil {
		t.Fatal(err)
	}
	if result.Token != "raw-token-value" || result.Outlet != "KLCC" {
		t.Fatalf("unexpected result %+v", result)
	}
}

func TestFolderExtractorFiltersByPatternAndAge(t *testing.T) {
	watch := t.TempDir()
	writeFile(t, watch, "DailySummary.xlsx", "report")
	writeFile(t, watch, "notes.txt", "not a report")
	tooNew := writeFile(t, watch, "growing.csv", "still exporting")

	cfg := testConfig(t, "http://unused", watch)

	// First: the too-new file is excluded while min age applies.
	cfg.MinFileAgeSeconds = 3600
	old := time.Now().Add(-2 * time.Hour)
	if err := os.Chtimes(filepath.Join(watch, "DailySummary.xlsx"), old, old); err != nil {
		t.Fatal(err)
	}

	files, err := (&FolderExtractor{}).Collect(cfg)
	if err != nil {
		t.Fatal(err)
	}
	if len(files) != 1 || filepath.Base(files[0]) != "DailySummary.xlsx" {
		t.Fatalf("expected only the aged xlsx, got %v", files)
	}

	// Then: with no min age, the csv joins; the txt never does.
	cfg.MinFileAgeSeconds = 0
	files, _ = (&FolderExtractor{}).Collect(cfg)
	if len(files) != 2 {
		t.Fatalf("expected xlsx+csv, got %v", files)
	}
	_ = tooNew
}

func TestSpoolDedupesAndDrains(t *testing.T) {
	var uploads int
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		switch r.URL.Path {
		case "/batches":
			uploads++
			w.WriteHeader(http.StatusAccepted)
			_ = json.NewEncoder(w).Encode(map[string]any{"status": "received", "batch_id": uploads})
		default:
			w.WriteHeader(http.StatusOK)
			_, _ = w.Write([]byte(`{"status":"ok"}`))
		}
	}))
	defer srv.Close()

	watch := t.TempDir()
	report := writeFile(t, watch, "DailySummary.xlsx", "same-bytes")

	cfg := testConfig(t, srv.URL, watch)
	spool := NewSpool(filepath.Join(t.TempDir(), "spool"))
	agent := NewAgent(cfg, NewClient(srv.URL, "t"), &FolderExtractor{}, spool, log.New(os.Stderr, "", 0))

	agent.SyncOnce()
	if uploads != 1 {
		t.Fatalf("expected 1 upload, got %d", uploads)
	}
	if pending := spool.Pending(); len(pending) != 0 {
		t.Fatalf("spool should be empty after confirmation, has %v", pending)
	}

	// The same file again — same bytes, ledger says handled, nothing sent.
	agent.SyncOnce()
	if uploads != 1 {
		t.Fatalf("re-sync must not re-upload handled bytes, got %d uploads", uploads)
	}

	// A renamed copy with the same content is still the same bytes.
	writeFile(t, watch, "DailySummary (1).xlsx", "same-bytes")
	agent.SyncOnce()
	if uploads != 1 {
		t.Fatalf("renamed copy of same bytes must not re-upload, got %d", uploads)
	}

	// New content is a new report.
	writeFile(t, watch, "DailySummary (2).xlsx", "different-bytes")
	agent.SyncOnce()
	if uploads != 2 {
		t.Fatalf("new bytes should upload, got %d", uploads)
	}
	_ = report
}

func TestSpoolSurvivesServerOutage(t *testing.T) {
	up := false
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		if !up {
			w.WriteHeader(http.StatusBadGateway)
			return
		}
		w.WriteHeader(http.StatusAccepted)
		_ = json.NewEncoder(w).Encode(map[string]any{"status": "received", "batch_id": 1})
	}))
	defer srv.Close()

	watch := t.TempDir()
	writeFile(t, watch, "DailySummary.xlsx", "report-bytes")

	cfg := testConfig(t, srv.URL, watch)
	spool := NewSpool(filepath.Join(t.TempDir(), "spool"))
	agent := NewAgent(cfg, NewClient(srv.URL, "t"), &FolderExtractor{}, spool, log.New(os.Stderr, "", 0))

	// Server down: the report is spooled, not lost.
	agent.SyncOnce()
	if pending := spool.Pending(); len(pending) != 1 {
		t.Fatalf("expected the report to wait in the spool, has %v", pending)
	}

	// Server back: the next pass delivers it.
	up = true
	agent.SyncOnce()
	if pending := spool.Pending(); len(pending) != 0 {
		t.Fatalf("expected the spool to drain, has %v", pending)
	}
}

func TestDuplicateAnswerClearsTheSpool(t *testing.T) {
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		// The server has these bytes already (a replay after a crash).
		_ = json.NewEncoder(w).Encode(map[string]any{"status": "duplicate", "batch_id": 7})
	}))
	defer srv.Close()

	watch := t.TempDir()
	writeFile(t, watch, "DailySummary.xlsx", "replayed-bytes")

	cfg := testConfig(t, srv.URL, watch)
	spool := NewSpool(filepath.Join(t.TempDir(), "spool"))
	agent := NewAgent(cfg, NewClient(srv.URL, "t"), &FolderExtractor{}, spool, log.New(os.Stderr, "", 0))

	agent.SyncOnce()
	if pending := spool.Pending(); len(pending) != 0 {
		t.Fatalf("duplicate is a success — spool should clear, has %v", pending)
	}
}
