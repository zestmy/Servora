package main

import (
	"bytes"
	"encoding/json"
	"errors"
	"fmt"
	"io"
	"net/http"
	"strings"
	"time"
)

// ErrUnpaired is the server's 401 {status: "unpaired"} — every way a token
// can stop being good (revoked, deleted, wrong tenant) collapses to it, and
// the loop's answer is always the same: log loudly, slow down, and wait for
// a human to re-pair. See PrintAgentAuthenticate on the server side.
var ErrUnpaired = errors.New("this agent is not paired (or was revoked) — a manager must issue a new pairing code")

// Client speaks the agent's whole wire surface: four JSON endpoints under
// the tenant's /agent mount. Nothing else in the binary knows HTTP.
type Client struct {
	base  string
	token string
	http  *http.Client
}

func NewClient(baseURL, token string) *Client {
	return &Client{
		base:  strings.TrimRight(baseURL, "/"),
		token: token,
		// Generous against a busy shared droplet, but bounded: a poll that
		// hangs forever would silence the heartbeat while looking alive.
		http: &http.Client{Timeout: 30 * time.Second},
	}
}

// Job is one document to put on one printer, exactly as the server queued
// it. PrinterName was copied from the printer record at submit time, so a
// config edit mid-flight cannot retarget it.
type Job struct {
	ID          int64      `json:"id"`
	PrinterName string     `json:"printer_name"`
	Title       string     `json:"title"`
	Options     JobOptions `json:"options"`
	Payload     string     `json:"payload_base64"`
}

type JobOptions struct {
	// Paper names the driver form to print on. Empty means "the driver
	// default is already the label stock" — never invent one.
	Paper string `json:"paper"`
}

// PrinterInfo is what the agent reports: the queue names this PC can print
// to, and the paper forms each one's driver offers. The server stores it
// verbatim as the picker's inventory.
type PrinterInfo struct {
	Name      string  `json:"name"`
	IsDefault bool    `json:"is_default"`
	Status    string  `json:"status,omitempty"`
	Papers    []Paper `json:"papers"`
}

type Paper struct {
	Name string `json:"name"`
	Size string `json:"size,omitempty"`
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

	status, err := c.request(http.MethodPost, "/pair", body, &resp)
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

// Jobs is the poll — and, server-side, the heartbeat. Returned jobs are
// already marked delivered; not acking them re-offers them in 2 minutes.
func (c *Client) Jobs() ([]Job, error) {
	var resp struct {
		Jobs []Job `json:"jobs"`
	}

	status, err := c.request(http.MethodGet, "/jobs", nil, &resp)
	if err != nil {
		return nil, err
	}
	if status == http.StatusUnauthorized {
		return nil, ErrUnpaired
	}
	if status != http.StatusOK {
		return nil, fmt.Errorf("poll failed (HTTP %d)", status)
	}

	return resp.Jobs, nil
}

// Ack reports a job's terminal fate. "done" means handed to the spooler and
// the print command exited cleanly — the same honesty the server documents.
func (c *Client) Ack(id int64, status, message string) error {
	body := map[string]string{"status": status}
	if message != "" {
		body["message"] = message
	}

	code, err := c.request(http.MethodPost, fmt.Sprintf("/jobs/%d/status", id), body, nil)
	if err != nil {
		return err
	}
	if code == http.StatusUnauthorized {
		return ErrUnpaired
	}
	if code != http.StatusOK {
		return fmt.Errorf("ack failed (HTTP %d)", code)
	}
	return nil
}

// ReportPrinters replaces the server's copy of this PC's inventory.
func (c *Client) ReportPrinters(printers []PrinterInfo, version string) error {
	if printers == nil {
		printers = []PrinterInfo{}
	}

	body := map[string]any{"printers": printers, "version": version}

	code, err := c.request(http.MethodPost, "/printers", body, nil)
	if err != nil {
		return err
	}
	if code == http.StatusUnauthorized {
		return ErrUnpaired
	}
	if code != http.StatusOK {
		return fmt.Errorf("printer report failed (HTTP %d)", code)
	}
	return nil
}

// request does one JSON round trip. Non-2xx statuses are returned, not
// errors — the callers each know which codes mean what.
func (c *Client) request(method, path string, body any, out any) (int, error) {
	var reader io.Reader
	if body != nil {
		raw, err := json.Marshal(body)
		if err != nil {
			return 0, err
		}
		reader = bytes.NewReader(raw)
	}

	req, err := http.NewRequest(method, c.base+path, reader)
	if err != nil {
		return 0, err
	}
	req.Header.Set("Accept", "application/json")
	if body != nil {
		req.Header.Set("Content-Type", "application/json")
	}
	if c.token != "" {
		req.Header.Set("X-Agent-Token", c.token)
	}
	req.Header.Set("User-Agent", "servora-print-agent/"+Version)

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
