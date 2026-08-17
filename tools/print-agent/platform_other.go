//go:build !windows

package main

import (
	"fmt"
	"os/exec"
	"strings"
)

// Windows is the fleet reality; these exist so the agent builds, tests and
// dev-runs anywhere. The Linux print path (`lp -d`) is the "nearly free"
// half the plan promised — functional for a dev with CUPS, unsupported in
// the field until a Linux phase ships properly.

// CupsEnumerator lists CUPS destinations via lpstat. No paper forms — on
// CUPS the media is a job option, not a driver form list.
type CupsEnumerator struct{}

func (CupsEnumerator) List() ([]PrinterInfo, error) {
	out, err := exec.Command("lpstat", "-e").Output()
	if err != nil {
		// No CUPS is normal on a dev box; report nothing rather than fail.
		return []PrinterInfo{}, nil
	}

	defaultOut, _ := exec.Command("lpstat", "-d").Output()
	defaultName := ""
	if parts := strings.SplitN(strings.TrimSpace(string(defaultOut)), ": ", 2); len(parts) == 2 {
		defaultName = parts[1]
	}

	var infos []PrinterInfo
	for _, line := range strings.Split(strings.TrimSpace(string(out)), "\n") {
		name := strings.TrimSpace(line)
		if name == "" {
			continue
		}
		infos = append(infos, PrinterInfo{Name: name, IsDefault: name == defaultName})
	}

	return infos, nil
}

// LPExecutor prints via lp. `fit-to-page` is deliberately NOT passed — the
// PDF is already exactly the label size, the same 100%-scale rule as
// everywhere else in the module.
type LPExecutor struct{}

func (LPExecutor) Print(pdfPath, printerName, paper string) error {
	args := []string{"-d", printerName}
	if paper != "" {
		args = append(args, "-o", "media="+paper)
	}
	args = append(args, pdfPath)

	out, err := exec.Command("lp", args...).CombinedOutput()
	if err != nil {
		return fmt.Errorf("lp failed for %q: %v — %s", printerName, err, strings.TrimSpace(string(out)))
	}

	return nil
}

func newEnumerator() Enumerator { return CupsEnumerator{} }
func newExecutor() Executor     { return LPExecutor{} }

// probeCommand diagnoses Windows driver paper/orientation behaviour; on
// CUPS the media option already carries dimensions, so there is nothing to
// probe.
func probeCommand(printerName, paperName string) error {
	return fmt.Errorf("probe is a Windows diagnostic; this build prints via CUPS")
}
