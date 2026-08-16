package main

import (
	"context"
	"encoding/base64"
	"encoding/json"
	"errors"
	"io"
	"log"
	"net/http"
	"net/http/httptest"
	"os"
	"sync"
	"testing"
	"time"
)

// fakeServer is the Laravel side, boiled down to the four endpoints the
// agent speaks — the same wire surface PrintAgentTest exercises from the
// other direction.
type fakeServer struct {
	mu       sync.Mutex
	token    string
	jobs     []Job
	acks     map[int64]string // job id -> "status|message"
	reports  [][]PrinterInfo
	unpaired bool
}

func newFakeServer() *fakeServer {
	return &fakeServer{token: "test-token-64-chars", acks: map[int64]string{}}
}

func (f *fakeServer) handler() http.Handler {
	mux := http.NewServeMux()

	auth := func(w http.ResponseWriter, r *http.Request) bool {
		f.mu.Lock()
		defer f.mu.Unlock()
		if f.unpaired || r.Header.Get("X-Agent-Token") != f.token {
			w.WriteHeader(http.StatusUnauthorized)
			_ = json.NewEncoder(w).Encode(map[string]string{"status": "unpaired"})
			return false
		}
		return true
	}

	mux.HandleFunc("POST /pair", func(w http.ResponseWriter, r *http.Request) {
		var body map[string]string
		_ = json.NewDecoder(r.Body).Decode(&body)

		if body["code"] != "GOODCODE" {
			w.WriteHeader(http.StatusUnprocessableEntity)
			_ = json.NewEncoder(w).Encode(map[string]string{
				"status": "invalid", "message": "That pairing code is wrong or has expired.",
			})
			return
		}

		_ = json.NewEncoder(w).Encode(map[string]any{
			"status": "paired",
			"token":  f.token,
			"agent":  map[string]any{"id": 1, "name": "Back-office PC", "outlet": "KLCC"},
		})
	})

	mux.HandleFunc("GET /jobs", func(w http.ResponseWriter, r *http.Request) {
		if !auth(w, r) {
			return
		}
		f.mu.Lock()
		jobs := f.jobs
		f.jobs = nil
		f.mu.Unlock()
		if jobs == nil {
			jobs = []Job{}
		}
		_ = json.NewEncoder(w).Encode(map[string]any{"jobs": jobs})
	})

	mux.HandleFunc("POST /jobs/{id}/status", func(w http.ResponseWriter, r *http.Request) {
		if !auth(w, r) {
			return
		}
		var body map[string]string
		_ = json.NewDecoder(r.Body).Decode(&body)

		var id int64
		_ = json.Unmarshal([]byte(r.PathValue("id")), &id)

		f.mu.Lock()
		f.acks[id] = body["status"] + "|" + body["message"]
		f.mu.Unlock()
		_ = json.NewEncoder(w).Encode(map[string]string{"status": "recorded"})
	})

	mux.HandleFunc("POST /printers", func(w http.ResponseWriter, r *http.Request) {
		if !auth(w, r) {
			return
		}
		var body struct {
			Printers []PrinterInfo `json:"printers"`
		}
		raw, _ := io.ReadAll(r.Body)
		_ = json.Unmarshal(raw, &body)

		f.mu.Lock()
		f.reports = append(f.reports, body.Printers)
		f.mu.Unlock()
		_ = json.NewEncoder(w).Encode(map[string]string{"status": "recorded"})
	})

	return mux
}

// fakeExec records prints and can be told to fail.
type fakeExec struct {
	mu     sync.Mutex
	prints []string // "printer|paper|payload"
	fail   error
}

func (e *fakeExec) Print(pdfPath, printerName, paper string) error {
	e.mu.Lock()
	defer e.mu.Unlock()
	if e.fail != nil {
		return e.fail
	}
	raw, _ := os.ReadFile(pdfPath)
	e.prints = append(e.prints, printerName+"|"+paper+"|"+string(raw))
	return nil
}

type fakeEnum struct{ printers []PrinterInfo }

func (e fakeEnum) List() ([]PrinterInfo, error) { return e.printers, nil }

func testAgent(t *testing.T, srv *fakeServer, exec Executor) (*Agent, *httptest.Server) {
	t.Helper()

	ts := httptest.NewServer(srv.handler())
	t.Cleanup(ts.Close)

	cfg := &Config{ServerURL: ts.URL, Token: srv.token, PollSeconds: 1}
	enum := fakeEnum{printers: []PrinterInfo{{Name: "DELI DL-888", IsDefault: true}}}
	logger := log.New(io.Discard, "", 0)

	return NewAgent(cfg, NewClient(cfg.ServerURL, cfg.Token), enum, exec, logger), ts
}

func TestPairExchangesCodeForToken(t *testing.T) {
	srv := newFakeServer()
	ts := httptest.NewServer(srv.handler())
	defer ts.Close()

	result, err := NewClient(ts.URL, "").Pair("GOODCODE", "host", "windows", Version)
	if err != nil {
		t.Fatalf("pair failed: %v", err)
	}
	if result.Token != srv.token {
		t.Fatalf("token = %q, want %q", result.Token, srv.token)
	}
	if result.Agent != "Back-office PC" || result.Outlet != "KLCC" {
		t.Fatalf("agent identity wrong: %+v", result)
	}
}

func TestPairSurfacesTheServersOwnMessage(t *testing.T) {
	srv := newFakeServer()
	ts := httptest.NewServer(srv.handler())
	defer ts.Close()

	_, err := NewClient(ts.URL, "").Pair("WRONG", "host", "windows", Version)
	if err == nil || err.Error() != "That pairing code is wrong or has expired." {
		// The server's message is written for the human at the PC; the
		// client must not bury it under a generic one.
		t.Fatalf("err = %v, want the server's message verbatim", err)
	}
}

func TestJobIsPrintedAndAckedDone(t *testing.T) {
	srv := newFakeServer()
	exec := &fakeExec{}
	agent, _ := testAgent(t, srv, exec)

	srv.mu.Lock()
	srv.jobs = []Job{{
		ID:          42,
		PrinterName: "DELI DL-888",
		Title:       "Servora KLCC DELI — 3 labels",
		Options:     JobOptions{Paper: "70 x 40 mm"},
		Payload:     base64.StdEncoding.EncodeToString([]byte("%PDF-fake")),
	}}
	srv.mu.Unlock()

	runBriefly(agent, 3)

	exec.mu.Lock()
	defer exec.mu.Unlock()
	if len(exec.prints) != 1 {
		t.Fatalf("prints = %d, want 1", len(exec.prints))
	}
	if exec.prints[0] != "DELI DL-888|70 x 40 mm|%PDF-fake" {
		t.Fatalf("printed %q — printer, paper or payload wrong", exec.prints[0])
	}

	srv.mu.Lock()
	defer srv.mu.Unlock()
	if srv.acks[42] != "done|" {
		t.Fatalf("ack = %q, want done with no message", srv.acks[42])
	}
}

func TestPrintFailureAcksErrorWithTheReason(t *testing.T) {
	srv := newFakeServer()
	exec := &fakeExec{fail: errors.New("printing to \"DELI\" failed: exit 1 — out of labels")}
	agent, _ := testAgent(t, srv, exec)

	srv.mu.Lock()
	srv.jobs = []Job{{ID: 7, PrinterName: "DELI", Payload: base64.StdEncoding.EncodeToString([]byte("x"))}}
	srv.mu.Unlock()

	runBriefly(agent, 3)

	srv.mu.Lock()
	defer srv.mu.Unlock()
	if srv.acks[7] != "error|printing to \"DELI\" failed: exit 1 — out of labels" {
		// The reason lands on the job row server-side — it is the only
		// window support ever gets into this PC.
		t.Fatalf("ack = %q, want the executor's error verbatim", srv.acks[7])
	}
}

func TestGarbagePayloadAcksErrorWithoutPrinting(t *testing.T) {
	srv := newFakeServer()
	exec := &fakeExec{}
	agent, _ := testAgent(t, srv, exec)

	srv.mu.Lock()
	srv.jobs = []Job{{ID: 9, PrinterName: "DELI", Payload: "not!!base64"}}
	srv.mu.Unlock()

	runBriefly(agent, 3)

	exec.mu.Lock()
	if len(exec.prints) != 0 {
		t.Fatalf("printed garbage: %v", exec.prints)
	}
	exec.mu.Unlock()

	srv.mu.Lock()
	defer srv.mu.Unlock()
	if srv.acks[9] == "" {
		t.Fatal("no error ack for the garbage payload")
	}
}

func TestPrintersAreReportedOnStartup(t *testing.T) {
	srv := newFakeServer()
	agent, _ := testAgent(t, srv, &fakeExec{})

	runBriefly(agent, 2)

	srv.mu.Lock()
	defer srv.mu.Unlock()
	if len(srv.reports) == 0 {
		t.Fatal("no printer inventory reported on startup")
	}
	if srv.reports[0][0].Name != "DELI DL-888" || !srv.reports[0][0].IsDefault {
		t.Fatalf("reported %+v", srv.reports[0])
	}
}

func TestUnpairedIsRecognisedNotRetriedBlindly(t *testing.T) {
	srv := newFakeServer()
	srv.unpaired = true

	ts := httptest.NewServer(srv.handler())
	defer ts.Close()

	_, err := NewClient(ts.URL, srv.token).Jobs()
	if !errors.Is(err, ErrUnpaired) {
		t.Fatalf("err = %v, want ErrUnpaired — the loop keys its backoff off this", err)
	}
}

// runBriefly runs the loop long enough for n one-second polls.
func runBriefly(agent *Agent, n int) {
	ctx, cancel := context.WithTimeout(context.Background(),
		time.Duration(n)*time.Second+500*time.Millisecond)
	defer cancel()
	agent.Run(ctx)
}
