//go:build !windows

package main

import "os"

// Non-Windows builds exist only so the package compiles and tests run on a
// dev machine; discovery of a real POS terminal is a Windows affair.

func platformFacts() []string {
	return []string{"(not a Windows machine — registry sections skipped)"}
}

func installedSoftware() section {
	return section{title: "Installed software (POS-related)"}
}

func odbcSources() section {
	return section{title: "ODBC data sources"}
}

func scheduledTasks() section {
	return section{title: "Scheduled tasks (non-Microsoft)"}
}

func servicesAndProcesses() section {
	return section{title: "Services and processes (POS-related)"}
}

func scanRoots() []string {
	home, err := os.UserHomeDir()
	if err != nil {
		return nil
	}
	return []string{home}
}

func userDocumentRoots() []string {
	return nil
}
