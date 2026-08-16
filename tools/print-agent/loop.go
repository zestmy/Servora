package main

import (
	"context"
	"encoding/base64"
	"errors"
	"fmt"
	"log"
	"os"
	"path/filepath"
	"time"
)

// Enumerator lists the printers this PC can reach. Behind an interface so
// the loop is testable off Windows; the real one wraps winspool.
type Enumerator interface {
	List() ([]PrinterInfo, error)
}

// Executor puts one PDF on one printer. Behind an interface for the same
// reason; the real one shells out to the bundled SumatraPDF.
type Executor interface {
	Print(pdfPath, printerName, paper string) error
}

// How often the reported inventory is refreshed. Printers appear and
// disappear on the scale of "someone plugged one in", not seconds.
const reportEvery = 5 * time.Minute

// How long to sulk after a 401 before trying again. The token is not
// coming back on its own — a human has to re-pair — so hammering the
// server every 3 seconds would only fill both logs.
const unpairedBackoff = 60 * time.Second

// Agent is the whole runtime: poll, print, ack, report. One loop, no
// concurrency — a kitchen prints a handful of batches an hour, and jobs
// coming off one queue in order is a feature (labels peel in walk order).
type Agent struct {
	cfg    *Config
	client *Client
	enum   Enumerator
	exec   Executor
	log    *log.Logger
}

func NewAgent(cfg *Config, client *Client, enum Enumerator, exec Executor, logger *log.Logger) *Agent {
	return &Agent{cfg: cfg, client: client, enum: enum, exec: exec, log: logger}
}

// Run polls until the context ends. Errors never stop the loop — an outlet
// PC has nobody watching a console, so the only acceptable failure mode is
// "keeps trying, logs what happened".
func (a *Agent) Run(ctx context.Context) {
	a.log.Printf("agent v%s polling %s every %ds", Version, a.cfg.ServerURL, a.cfg.PollSeconds)

	a.reportPrinters()
	lastReport := time.Now()

	ticker := time.NewTicker(time.Duration(a.cfg.PollSeconds) * time.Second)
	defer ticker.Stop()

	for {
		select {
		case <-ctx.Done():
			a.log.Printf("stopping")
			return
		case <-ticker.C:
		}

		jobs, err := a.client.Jobs()
		if err != nil {
			if errors.Is(err, ErrUnpaired) {
				a.log.Printf("UNPAIRED: %v — retrying in %s", err, unpairedBackoff)
				if !sleepCtx(ctx, unpairedBackoff) {
					return
				}
				continue
			}
			// Network blips are normal on outlet wifi. Say so once per
			// blip and keep the cadence — the next poll is the retry.
			a.log.Printf("poll failed: %v", err)
			continue
		}

		for _, job := range jobs {
			a.handle(job)
		}

		if time.Since(lastReport) >= reportEvery {
			a.reportPrinters()
			lastReport = time.Now()
		}
	}
}

// handle prints one job and acks its fate. Every failure path acks 'error'
// with a reason a human at the printer can act on — the server writes it
// onto the job row, which is the only way support ever sees it.
func (a *Agent) handle(job Job) {
	a.log.Printf("job %d: %q -> %q (paper %q)", job.ID, job.Title, job.PrinterName, job.Options.Paper)

	pdf, err := base64.StdEncoding.DecodeString(job.Payload)
	if err != nil || len(pdf) == 0 {
		a.ack(job.ID, "error", "the job payload was empty or not valid base64")
		return
	}

	path := filepath.Join(os.TempDir(), fmt.Sprintf("servora-job-%d.pdf", job.ID))
	if err := os.WriteFile(path, pdf, 0o600); err != nil {
		a.ack(job.ID, "error", "could not write the PDF to disk: "+err.Error())
		return
	}
	defer os.Remove(path)

	if err := a.exec.Print(path, job.PrinterName, job.Options.Paper); err != nil {
		a.log.Printf("job %d: print failed: %v", job.ID, err)
		a.ack(job.ID, "error", err.Error())
		return
	}

	a.log.Printf("job %d: done", job.ID)
	a.ack(job.ID, "done", "")
}

func (a *Agent) ack(id int64, status, message string) {
	if err := a.client.Ack(id, status, message); err != nil {
		// Nothing more to do: if the ack is lost the server re-offers the
		// job after 2 minutes, and a duplicate label is the accepted cost.
		a.log.Printf("job %d: ack (%s) failed: %v", id, status, err)
	}
}

func (a *Agent) reportPrinters() {
	printers, err := a.enum.List()
	if err != nil {
		// The honest fallback the plan allows: report nothing rather than
		// fail. The picker's free-text path covers it.
		a.log.Printf("printer enumeration failed: %v", err)
		return
	}

	if err := a.client.ReportPrinters(printers, Version); err != nil {
		a.log.Printf("printer report failed: %v", err)
		return
	}

	a.log.Printf("reported %d printer(s)", len(printers))
}

// sleepCtx sleeps unless the context ends first; false means "we're done".
func sleepCtx(ctx context.Context, d time.Duration) bool {
	select {
	case <-ctx.Done():
		return false
	case <-time.After(d):
		return true
	}
}
