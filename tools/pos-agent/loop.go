package main

import (
	"context"
	"errors"
	"log"
	"math/rand"
	"time"
)

// Agent is the service loop: ping for liveness and config, collect report
// files, spool them, drain the spool to the server.
type Agent struct {
	cfg       *Config
	client    *Client
	extractor Extractor
	spool     *Spool
	log       *log.Logger

	// reported keeps the outcome log from repeating itself every ping.
	reported map[int64]string
}

func NewAgent(cfg *Config, client *Client, extractor Extractor, spool *Spool, logger *log.Logger) *Agent {
	return &Agent{
		cfg:       cfg,
		client:    client,
		extractor: extractor,
		spool:     spool,
		log:       logger,
		reported:  map[int64]string{},
	}
}

// unpairedBackoff throttles the loop once the server answers 401: nothing
// will change until a human re-pairs, so once a minute is plenty.
const unpairedBackoff = time.Minute

func (a *Agent) Run(ctx context.Context) {
	a.log.Printf("servora-pos-agent %s starting (server %s, sync every %dm)",
		Version, a.cfg.ServerURL, a.cfg.SyncIntervalMinutes)

	// Jitter the first sync so a fleet rebooting after a power cut does not
	// stampede the server on the same second.
	jitter := time.Duration(rand.Intn(60)) * time.Second

	pingTicker := time.NewTicker(PingIntervalMinutes * time.Minute)
	defer pingTicker.Stop()

	syncTicker := time.NewTicker(time.Duration(a.cfg.SyncIntervalMinutes) * time.Minute)
	defer syncTicker.Stop()

	a.ping()

	select {
	case <-ctx.Done():
		return
	case <-time.After(jitter):
	}
	a.syncOnce()

	for {
		select {
		case <-ctx.Done():
			a.log.Print("stopping")
			return
		case <-pingTicker.C:
			if interval := a.ping(); interval > 0 && interval != a.cfg.SyncIntervalMinutes {
				a.log.Printf("server changed sync interval: %dm -> %dm", a.cfg.SyncIntervalMinutes, interval)
				a.cfg.SyncIntervalMinutes = interval
				syncTicker.Reset(time.Duration(interval) * time.Minute)
				_ = saveConfig(a.cfg)
			}
			// A ping is also the retry moment for anything still spooled —
			// an upload that failed on outlet Wi-Fi retries within minutes,
			// not at the next full sync.
			a.drain()
		case <-syncTicker.C:
			a.syncOnce()
		}
	}
}

// SyncOnce is one full pass: collect, spool, drain. Exported for the
// one-shot `sync` subcommand.
func (a *Agent) SyncOnce() {
	a.syncOnce()
}

func (a *Agent) syncOnce() {
	files, err := a.extractor.Collect(a.cfg)
	if err != nil {
		a.log.Printf("collect (%s): %v", a.extractor.Name(), err)
		return
	}

	for _, f := range files {
		added, err := a.spool.Add(f)
		if err != nil {
			a.log.Printf("spool %s: %v", f, err)
			continue
		}
		if added {
			a.log.Printf("spooled %s", f)
		}
	}

	a.drain()
}

func (a *Agent) drain() {
	for _, spooled := range a.spool.Pending() {
		result, err := a.client.UploadBatch(spooled, a.spool.SourceOf(spooled), a.extractor.Name())
		if errors.Is(err, ErrUnpaired) {
			a.log.Print("UNPAIRED: ", err)
			time.Sleep(unpairedBackoff)
			return
		}
		if err != nil {
			// Leave it spooled; the next ping or sync retries. Stop the
			// drain rather than hammering a server that just said no.
			a.log.Printf("upload %s: %v (will retry)", spooled, err)
			return
		}

		a.log.Printf("uploaded %s: %s (batch %d)", a.spool.SourceOf(spooled), result.Status, result.BatchID)
		a.spool.MarkSent(spooled, result.BatchID)
	}
}

// ping heartbeats, reports our version, logs newly-settled batch outcomes,
// and returns the server's requested sync interval (0 on failure).
func (a *Agent) ping() int {
	result, err := a.client.Ping()
	if errors.Is(err, ErrUnpaired) {
		a.log.Print("UNPAIRED: ", err)
		time.Sleep(unpairedBackoff)
		return 0
	}
	if err != nil {
		a.log.Printf("ping: %v", err)
		return 0
	}

	for _, batch := range result.RecentBatches {
		if a.reported[batch.ID] == batch.Status {
			continue
		}
		a.reported[batch.ID] = batch.Status

		if batch.Error != "" {
			a.log.Printf("server: batch %d is %s — %s", batch.ID, batch.Status, batch.Error)
		} else {
			a.log.Printf("server: batch %d is %s", batch.ID, batch.Status)
		}
	}

	if result.CurrentVersion != "" && result.CurrentVersion != Version {
		a.log.Printf("a newer agent build exists (%s, this is %s) — re-download from Servora's downloads page",
			result.CurrentVersion, Version)
	}

	return result.SyncIntervalMinutes
}
