//go:build windows

package main

import (
	"context"
	"fmt"
	"os"
	"os/exec"
	"path/filepath"
	"strings"
	"time"
)

// SumatraExecutor prints by shelling out to the SumatraPDF.exe shipped in
// the install zip, NEVER a copy found on the machine — version drift in the
// print path is a support ticket with no reproduction.
//
// The settings string is the module's hard-won paper lesson enforced at the
// last hop: `noscale` is the 100%-scale rule, and `paper=<form>` names the
// driver form so the driver's own default can never rotate or shrink the
// label to fit the wrong stock. Requires SumatraPDF >= 3.4 (paper=).
type SumatraExecutor struct{}

// A hung driver dialog must not wedge the whole queue: one job gets this
// long, then it is an error the chef can read.
const printTimeout = 2 * time.Minute

func (SumatraExecutor) Print(pdfPath, printerName, paper string) error {
	exe, err := sumatraPath()
	if err != nil {
		return err
	}

	settings := "noscale"
	if paper != "" {
		settings += ",paper=" + paper
		// Make the form REAL before Sumatra inherits the defaults: some
		// label drivers normalise a landscape form to portrait and swap
		// the axes (see devmode_windows.go). Best-effort — on failure the
		// inherit-the-defaults path still prints, correct on well-behaved
		// drivers, and `probe` exists to diagnose the rest.
		_, _ = applyPaperDevMode(printerName, paper)
	}

	ctx, cancel := context.WithTimeout(context.Background(), printTimeout)
	defer cancel()

	cmd := exec.CommandContext(ctx, exe,
		"-print-to", printerName,
		"-print-settings", settings,
		"-silent",
		pdfPath,
	)

	out, err := cmd.CombinedOutput()
	if ctx.Err() == context.DeadlineExceeded {
		return fmt.Errorf("printing to %q timed out after %s — check the printer and its driver dialogs", printerName, printTimeout)
	}
	if err != nil {
		return fmt.Errorf("printing to %q failed: %v%s", printerName, err, outputTail(out))
	}

	return nil
}

// sumatraPath finds SumatraPDF.exe beside the agent binary — where the
// install zip puts it.
func sumatraPath() (string, error) {
	self, err := os.Executable()
	if err != nil {
		return "", err
	}

	exe := filepath.Join(filepath.Dir(self), "SumatraPDF.exe")
	if _, err := os.Stat(exe); err != nil {
		return "", fmt.Errorf("SumatraPDF.exe not found next to the agent (%s) — re-install from the zip", exe)
	}

	return exe, nil
}

// outputTail keeps the last few hundred bytes of whatever Sumatra said —
// enough to diagnose, short enough for the server's 500-char error column.
func outputTail(out []byte) string {
	s := strings.TrimSpace(string(out))
	if s == "" {
		return ""
	}
	if len(s) > 300 {
		s = s[len(s)-300:]
	}
	return " — " + s
}
