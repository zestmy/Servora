package main

import (
	"bytes"
	"encoding/json"
	"errors"
	"fmt"
	"io"
	"mime/multipart"
	"net/http"
	"os"
	"path/filepath"
	"strings"
	"time"
)

// ErrUnpaired is the server's 401 {status: "unpaired"} — every way a token
// can stop being good collapses to it, and the loop's answer is always the
// same: log loudly, slow down, wait for a human to re-pair.
var ErrUnpaired = errors.New("this agent is not paired (or was revoked) — a manager must issue a new pairing code")

// Client speaks the agent's whole wire surface: three endpoints under the
// tenant's /pos-agent mount. Nothing else in the binary knows HTTP.
type Client struct {
	base  string
	token string
	http  *http.Client
}

func NewClient(baseURL, token string) *Client {
	return &Client{
		base:  strings.TrimRight(baseURL, "/"),
		token: token,
		// Longer than the print agent's 30s: uploads ride outlet Wi-Fi that
		// was not provisioned with servers in mind, and a report is worth
		// waiting for. Still bounded — a hung upload must not wedge the loop.
		http: &http.Client{Timeout: 120 * time.Second},
	}
}

type PairResult struct {
	Token  string
	Agent  string
	Outlet string
}

// Pair redeems a pairing code for the token. The one unauthenticated call,
// and the only time the token ever crosses the wire toward us.
func (c *Client) Pair(code, hostname, osName, version string) (*PairResult, error) {
	body := map[string]string{
		"code":     code,
		"hostname": hostname,
		"os":       osName,
		"version":  version,
	}

	var resp struct {
		Status  string `json:"status"`
		Message string `json:"message"`
		Token   string `json:"token"`
		Agent   struct {
			Name   string `json:"name"`
			Outlet string `json:"outlet"`
		} `json:"agent"`
	}

	raw, err := json.Marshal(body)
	if err != nil {
		return nil, err
	}

	status, err := c.do(http.MethodPost, "/pair", "application/json", bytes.NewReader(raw), &resp)
	if err != nil {
		return nil, err
	}
	if status != http.StatusOK {
		if resp.Message != "" {
			return nil, errors.New(resp.Message)
		}
		return nil, fmt.Errorf("pairing failed (HTTP %d)", status)
	}

	return &PairResult{Token: resp.Token, Agent: resp.Agent.Name, Outlet: resp.Agent.Outlet}, nil
}

// BatchOutcome is the server's account of one earlier upload, returned on
// ping so the local log can say what became of yesterday's report.
type BatchOutcome struct {
	ID     int64          `json:"id"`
	Sha256 string         `json:"sha256"`
	Status string         `json:"status"`
	Result map[string]any `json:"result"`
	Error  string         `json:"error"`
}

type PingResult struct {
	CurrentVersion      string
	SyncIntervalMinutes int
	RecentBatches       []BatchOutcome
}

// Ping is the heartbeat, the config channel and the outcome feed in one
// round trip.
func (c *Client) Ping() (*PingResult, error) {
	var resp struct {
		Status string `json:"status"`
		Agent  struct {
			CurrentVersion string `json:"current_version"`
		} `json:"agent"`
		Config struct {
			SyncIntervalMinutes int `json:"sync_interval_minutes"`
		} `json:"config"`
		RecentBatches []BatchOutcome `json:"recent_batches"`
	}

	status, err := c.do(http.MethodGet, "/ping?version="+Version, "", nil, &resp)
	if err != nil {
		return nil, err
	}
	if status == http.StatusUnauthorized {
		return nil, ErrUnpaired
	}
	if status != http.StatusOK {
		return nil, fmt.Errorf("ping failed (HTTP %d)", status)
	}

	return &PingResult{
		CurrentVersion:      resp.Agent.CurrentVersion,
		SyncIntervalMinutes: resp.Config.SyncIntervalMinutes,
		RecentBatches:       resp.RecentBatches,
	}, nil
}

type UploadResult struct {
	Status  string // received | duplicate
	BatchID int64
}

// UploadBatch sends one report file as multipart form data. "duplicate" is
// a success — the server has these bytes already, delete the spool copy.
func (c *Client) UploadBatch(path, sourcePath, extractorName string) (*UploadResult, error) {
	f, err := os.Open(path)
	if err != nil {
		return nil, err
	}
	defer f.Close()

	var buf bytes.Buffer
	mw := multipart.NewWriter(&buf)

	part, err := mw.CreateFormFile("file", filepath.Base(sourcePath))
	if err != nil {
		return nil, err
	}
	if _, err := io.Copy(part, f); err != nil {
		return nil, err
	}
	_ = mw.WriteField("version", Version)
	_ = mw.WriteField("extractor", extractorName)
	_ = mw.WriteField("source_path", sourcePath)
	if err := mw.Close(); err != nil {
		return nil, err
	}

	var resp struct {
		Status  string `json:"status"`
		Message string `json:"message"`
		BatchID int64  `json:"batch_id"`
	}

	status, err := c.do(http.MethodPost, "/batches", mw.FormDataContentType(), &buf, &resp)
	if err != nil {
		return nil, err
	}
	if status == http.StatusUnauthorized {
		return nil, ErrUnpaired
	}
	if status != http.StatusOK && status != http.StatusAccepted {
		if resp.Message != "" {
			return nil, errors.New(resp.Message)
		}
		return nil, fmt.Errorf("upload failed (HTTP %d)", status)
	}

	return &UploadResult{Status: resp.Status, BatchID: resp.BatchID}, nil
}

// do runs one round trip. Non-2xx statuses are returned, not errors — the
// callers each know which codes mean what.
func (c *Client) do(method, path, contentType string, body io.Reader, out any) (int, error) {
	req, err := http.NewRequest(method, c.base+path, body)
	if err != nil {
		return 0, err
	}
	req.Header.Set("Accept", "application/json")
	if contentType != "" {
		req.Header.Set("Content-Type", contentType)
	}
	if c.token != "" {
		req.Header.Set("X-Agent-Token", c.token)
	}
	req.Header.Set("User-Agent", "servora-pos-agent/"+Version)

	resp, err := c.http.Do(req)
	if err != nil {
		return 0, err
	}
	defer resp.Body.Close()

	if out != nil {
		// Decode errors on an error status are expected (HTML error pages);
		// the status code is the real answer there.
		raw, _ := io.ReadAll(io.LimitReader(resp.Body, 1<<20))
		_ = json.Unmarshal(raw, out)
	}

	return resp.StatusCode, nil
}
