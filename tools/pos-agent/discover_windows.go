//go:build windows

package main

import (
	"fmt"
	"os"
	"os/exec"
	"path/filepath"
	"strings"

	"golang.org/x/sys/windows/registry"
)

func platformFacts() []string {
	var lines []string

	k, err := registry.OpenKey(registry.LOCAL_MACHINE,
		`SOFTWARE\Microsoft\Windows NT\CurrentVersion`, registry.QUERY_VALUE)
	if err == nil {
		product, _, _ := k.GetStringValue("ProductName")
		display, _, _ := k.GetStringValue("DisplayVersion")
		build, _, _ := k.GetStringValue("CurrentBuildNumber")
		lines = append(lines, fmt.Sprintf("windows:  %s %s (build %s)", product, display, build))
		k.Close()
	}

	// PROCESSOR_ARCHITEW6432 is only set for a 32-bit process on 64-bit
	// Windows; between the two variables we get the real OS arch, which
	// decides whether the future sync agent ships as amd64 or 386.
	arch := os.Getenv("PROCESSOR_ARCHITEW6432")
	if arch == "" {
		arch = os.Getenv("PROCESSOR_ARCHITECTURE")
	}
	lines = append(lines, "os arch:  "+arch)

	return lines
}

// installedSoftware reads the three Uninstall hives (64-bit, 32-bit, and
// per-user) — the same list "Apps & features" shows — and keeps entries
// matching the POS keywords.
func installedSoftware() section {
	s := section{title: "Installed software (POS-related)"}

	type hive struct {
		root registry.Key
		path string
	}
	hives := []hive{
		{registry.LOCAL_MACHINE, `SOFTWARE\Microsoft\Windows\CurrentVersion\Uninstall`},
		{registry.LOCAL_MACHINE, `SOFTWARE\WOW6432Node\Microsoft\Windows\CurrentVersion\Uninstall`},
		{registry.CURRENT_USER, `SOFTWARE\Microsoft\Windows\CurrentVersion\Uninstall`},
	}

	seen := map[string]bool{}
	for _, h := range hives {
		k, err := registry.OpenKey(h.root, h.path, registry.ENUMERATE_SUB_KEYS)
		if err != nil {
			continue
		}
		names, _ := k.ReadSubKeyNames(0)
		for _, name := range names {
			sub, err := registry.OpenKey(h.root, h.path+`\`+name, registry.QUERY_VALUE)
			if err != nil {
				continue
			}
			display, _, _ := sub.GetStringValue("DisplayName")
			version, _, _ := sub.GetStringValue("DisplayVersion")
			location, _, _ := sub.GetStringValue("InstallLocation")
			sub.Close()

			if display == "" || !matchesKeyword(display) || seen[display] {
				continue
			}
			seen[display] = true

			line := display
			if version != "" {
				line += "  v" + version
			}
			if location != "" {
				line += "  @ " + location
			}
			s.lines = append(s.lines, line)
		}
		k.Close()
	}
	return s
}

// odbcSources lists every configured DSN with its driver — a DSN pointing at
// a local database is the single strongest hint about where sales data
// lives, because it means some other program already reads it that way.
func odbcSources() section {
	s := section{title: "ODBC data sources"}

	paths := []string{
		`SOFTWARE\ODBC\ODBC.INI`,
		`SOFTWARE\WOW6432Node\ODBC\ODBC.INI`,
	}
	for _, root := range []registry.Key{registry.LOCAL_MACHINE, registry.CURRENT_USER} {
		rootName := "HKLM"
		if root == registry.CURRENT_USER {
			rootName = "HKCU"
		}
		for _, p := range paths {
			k, err := registry.OpenKey(root, p, registry.ENUMERATE_SUB_KEYS)
			if err != nil {
				continue
			}
			names, _ := k.ReadSubKeyNames(0)
			for _, name := range names {
				if name == "ODBC Data Sources" {
					continue
				}
				line := fmt.Sprintf("%s %s", rootName, name)
				if sub, err := registry.OpenKey(root, p+`\`+name, registry.QUERY_VALUE); err == nil {
					driver, _, _ := sub.GetStringValue("Driver")
					db, _, _ := sub.GetStringValue("Database")
					if db == "" {
						db, _, _ = sub.GetStringValue("DBQ") // Access/dBase name their file this way
					}
					server, _, _ := sub.GetStringValue("Server")
					if driver != "" {
						line += "  driver=" + filepath.Base(driver)
					}
					if server != "" {
						line += "  server=" + server
					}
					if db != "" {
						line += "  db=" + db
					}
					sub.Close()
				}
				s.lines = append(s.lines, line)
			}
			k.Close()
		}
	}
	return s
}

// scheduledTasks keeps non-Microsoft tasks: an existing nightly report
// export or accounting-sync job would show up here, and piggybacking on one
// beats building an extractor.
func scheduledTasks() section {
	s := section{title: "Scheduled tasks (non-Microsoft)"}

	out, err := exec.Command("schtasks", "/query", "/fo", "csv", "/nh").Output()
	if err != nil {
		s.lines = append(s.lines, "could not query scheduled tasks: "+err.Error())
		return s
	}

	seen := map[string]bool{} // schtasks repeats a task once per trigger
	for _, line := range strings.Split(string(out), "\n") {
		line = strings.TrimSpace(line)
		if line == "" {
			continue
		}
		fields := parseCSVLine(line)
		if len(fields) == 0 {
			continue
		}
		name := fields[0]
		if strings.HasPrefix(name, `\Microsoft`) || name == "TaskName" || seen[name] {
			continue
		}
		seen[name] = true
		s.lines = append(s.lines, strings.Join(fields, "  "))
	}
	return s
}

// servicesAndProcesses reports keyword-matched Windows services (with their
// binary path — that path is often the POS install directory) and running
// processes.
func servicesAndProcesses() section {
	s := section{title: "Services and processes (POS-related)"}

	k, err := registry.OpenKey(registry.LOCAL_MACHINE,
		`SYSTEM\CurrentControlSet\Services`, registry.ENUMERATE_SUB_KEYS)
	if err == nil {
		names, _ := k.ReadSubKeyNames(0)
		for _, name := range names {
			sub, err := registry.OpenKey(registry.LOCAL_MACHINE,
				`SYSTEM\CurrentControlSet\Services\`+name, registry.QUERY_VALUE)
			if err != nil {
				continue
			}
			display, _, _ := sub.GetStringValue("DisplayName")
			image, _, _ := sub.GetStringValue("ImagePath")
			sub.Close()

			if !matchesKeyword(name) && !matchesKeyword(display) && !matchesKeyword(image) {
				continue
			}
			line := "service: " + name
			if display != "" && !strings.HasPrefix(display, "@") {
				line += " (" + display + ")"
			}
			if image != "" {
				line += "  " + image
			}
			s.lines = append(s.lines, line)
		}
		k.Close()
	}

	out, err := exec.Command("tasklist", "/fo", "csv", "/nh").Output()
	if err == nil {
		seen := map[string]bool{}
		for _, line := range strings.Split(string(out), "\n") {
			fields := parseCSVLine(strings.TrimSpace(line))
			if len(fields) > 0 && matchesKeyword(fields[0]) && !seen[fields[0]] {
				seen[fields[0]] = true
				s.lines = append(s.lines, "process: "+fields[0])
			}
		}
	}
	return s
}

func parseCSVLine(line string) []string {
	var fields []string
	var cur strings.Builder
	inQuote := false
	for i := 0; i < len(line); i++ {
		c := line[i]
		switch {
		case c == '"':
			inQuote = !inQuote
		case c == ',' && !inQuote:
			fields = append(fields, cur.String())
			cur.Reset()
		default:
			cur.WriteByte(c)
		}
	}
	fields = append(fields, cur.String())
	return fields
}

func scanRoots() []string {
	system := os.Getenv("SystemDrive")
	if system == "" {
		system = "C:"
	}
	roots := []string{
		system + `\`,
		os.Getenv("ProgramFiles"),
		os.Getenv("ProgramFiles(x86)"),
		os.Getenv("ProgramData"),
	}
	var out []string
	for _, r := range roots {
		if r != "" {
			out = append(out, r)
		}
	}
	return out
}

func userDocumentRoots() []string {
	system := os.Getenv("SystemDrive")
	if system == "" {
		system = "C:"
	}
	profiles, err := os.ReadDir(system + `\Users`)
	if err != nil {
		return nil
	}
	var roots []string
	for _, p := range profiles {
		if !p.IsDir() {
			continue
		}
		for _, sub := range []string{"Desktop", "Documents", "Downloads"} {
			dir := filepath.Join(system+`\Users`, p.Name(), sub)
			if _, err := os.Stat(dir); err == nil {
				roots = append(roots, dir)
			}
		}
	}
	return roots
}
