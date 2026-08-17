package main

import (
	"archive/zip"
	"fmt"
	"io"
	"io/fs"
	"os"
	"path/filepath"
	"runtime"
	"sort"
	"strings"
	"time"
)

// posKeywords marks anything plausibly POS-related in installed-software
// names, directory names, service names and task names. Deliberately broad:
// the report is read by a human, and a false positive costs a line while a
// false negative costs a second site visit.
var posKeywords = []string{
	"zeoniq", "autocount", "sql account", "sqlacc", "firebird",
	"interbase", "mysql", "sql server", "mariadb", "retail",
}

// tokenKeywords are too short to substring-match — "pos" is inside
// "Composer" and every "Compositor" driver — so they must stand alone as a
// word ("POS", "MyPOS-Backup", "fnb_data").
var tokenKeywords = []string{"pos", "fnb", "f&b"}

// dataFileExts are the database-file shapes Malaysian POS installs actually
// use: Firebird/Interbase (.fdb/.gdb), SQL Server (.mdf/.ldf), Access
// (.mdb), SQLite (.db/.sqlite), and dBase (.dbf).
var dataFileExts = map[string]bool{
	".fdb": true, ".gdb": true, ".mdf": true, ".ldf": true,
	".mdb": true, ".db": true, ".sqlite": true, ".dbf": true,
}

var reportExts = map[string]bool{".xls": true, ".xlsx": true, ".csv": true}

const (
	scanMaxDepth     = 4
	scanMaxHits      = 200
	reportWindowDays = 30
)

type section struct {
	title string
	lines []string
}

// runDiscovery inventories the machine and writes a zipped text report next
// to the executable (the POS terminal's user will likely run this from a USB
// stick or Downloads — beside the exe is the one place they can find again).
func runDiscovery() (string, error) {
	hostname, _ := os.Hostname()
	if hostname == "" {
		hostname = "unknown-host"
	}

	fmt.Println("Inspecting this machine (takes a minute or two)...")

	sections := []section{
		machineFacts(hostname),
		installedSoftware(),
		matchingDirectories(),
		dataFiles(),
		odbcSources(),
		scheduledTasks(),
		servicesAndProcesses(),
		recentReportFiles(),
	}

	var b strings.Builder
	fmt.Fprintf(&b, "Servora POS Agent discovery report v%s\n", Version)
	fmt.Fprintf(&b, "Host: %s\nGenerated: %s\n", hostname, time.Now().Format(time.RFC3339))
	for _, s := range sections {
		fmt.Fprintf(&b, "\n== %s ==\n", s.title)
		if len(s.lines) == 0 {
			b.WriteString("(nothing found)\n")
			continue
		}
		for _, l := range s.lines {
			b.WriteString(l)
			b.WriteByte('\n')
		}
	}

	exeDir := "."
	if exe, err := os.Executable(); err == nil {
		exeDir = filepath.Dir(exe)
	}

	name := fmt.Sprintf("pos-agent-discovery-%s", sanitizeName(hostname))
	zipPath := filepath.Join(exeDir, name+".zip")

	if err := writeZip(zipPath, name+".txt", b.String()); err != nil {
		// A locked-down machine may refuse writes beside the exe; fall back
		// to the temp dir rather than losing the scan.
		zipPath = filepath.Join(os.TempDir(), name+".zip")
		if err := writeZip(zipPath, name+".txt", b.String()); err != nil {
			return "", err
		}
	}

	return zipPath, nil
}

func writeZip(path, inner, content string) error {
	f, err := os.Create(path)
	if err != nil {
		return err
	}
	defer f.Close()

	zw := zip.NewWriter(f)
	w, err := zw.Create(inner)
	if err != nil {
		return err
	}
	if _, err := io.WriteString(w, content); err != nil {
		return err
	}
	return zw.Close()
}

func sanitizeName(s string) string {
	return strings.Map(func(r rune) rune {
		switch {
		case r >= 'a' && r <= 'z', r >= 'A' && r <= 'Z', r >= '0' && r <= '9', r == '-', r == '_':
			return r
		default:
			return '-'
		}
	}, s)
}

func matchesKeyword(s string) bool {
	low := strings.ToLower(s)
	for _, k := range posKeywords {
		if strings.Contains(low, k) {
			return true
		}
	}

	tokens := strings.FieldsFunc(low, func(r rune) bool {
		return !(r >= 'a' && r <= 'z' || r >= '0' && r <= '9' || r == '&')
	})
	for _, t := range tokens {
		for _, k := range tokenKeywords {
			if t == k {
				return true
			}
		}
	}
	return false
}

func machineFacts(hostname string) section {
	s := section{title: "Machine"}
	s.lines = append(s.lines,
		"hostname: "+hostname,
		"go arch:  "+runtime.GOARCH,
	)
	s.lines = append(s.lines, platformFacts()...)
	return s
}

// matchingDirectories lists top-level directories whose names look
// POS-related, two levels deep — enough to spot "C:\Zeoniq\Data" without
// walking the world.
func matchingDirectories() section {
	s := section{title: "POS-looking directories (2 levels)"}
	for _, root := range scanRoots() {
		entries, err := os.ReadDir(root)
		if err != nil {
			continue
		}
		for _, e := range entries {
			if !e.IsDir() || !matchesKeyword(e.Name()) {
				continue
			}
			dir := filepath.Join(root, e.Name())
			s.lines = append(s.lines, dir)
			children, err := os.ReadDir(dir)
			if err != nil {
				continue
			}
			for _, c := range children {
				marker := ""
				if c.IsDir() {
					marker = string(os.PathSeparator)
				}
				s.lines = append(s.lines, "  "+c.Name()+marker)
			}
		}
	}
	return s
}

// dataFiles walks the scan roots for database-shaped files. Depth-capped and
// hit-capped: this runs on a live till, and a complete answer that arrives
// tomorrow is worse than a good answer in a minute.
func dataFiles() section {
	s := section{title: "Candidate data files"}
	type hit struct {
		path string
		size int64
		mod  time.Time
	}
	var hits []hit
	seen := map[string]bool{} // scan roots overlap (C:\ contains Program Files)

	for _, root := range scanRoots() {
		rootDepth := strings.Count(filepath.Clean(root), string(os.PathSeparator))
		_ = filepath.WalkDir(root, func(path string, d fs.DirEntry, err error) error {
			if err != nil {
				return nil
			}
			if len(hits) >= scanMaxHits {
				return filepath.SkipAll
			}
			if d.IsDir() {
				if skipDir(d.Name()) {
					return filepath.SkipDir
				}
				if strings.Count(filepath.Clean(path), string(os.PathSeparator))-rootDepth >= scanMaxDepth {
					return filepath.SkipDir
				}
				return nil
			}
			if !dataFileExts[strings.ToLower(filepath.Ext(d.Name()))] {
				return nil
			}
			info, err := d.Info()
			if err != nil {
				return nil
			}
			key := strings.ToLower(path)
			if seen[key] {
				return nil
			}
			seen[key] = true
			hits = append(hits, hit{path, info.Size(), info.ModTime()})
			return nil
		})
	}

	// Most recently written first: on a POS the live database is the one
	// that changed today.
	sort.Slice(hits, func(i, j int) bool { return hits[i].mod.After(hits[j].mod) })
	for _, h := range hits {
		s.lines = append(s.lines, fmt.Sprintf("%s  (%s, modified %s)",
			h.path, humanSize(h.size), h.mod.Format("2006-01-02 15:04")))
	}
	if len(hits) >= scanMaxHits {
		s.lines = append(s.lines, fmt.Sprintf("(stopped at %d files — scan was capped)", scanMaxHits))
	}
	return s
}

func skipDir(name string) bool {
	switch strings.ToLower(name) {
	case "windows", "$recycle.bin", "system volume information", "winsxs",
		"node_modules", "appdata", "microsoft", "windowsapps":
		return true
	}
	return false
}

// recentReportFiles looks where a human or a scheduled export would drop a
// sales report: user-profile folders plus any export/report-named directory
// under the POS-looking trees.
func recentReportFiles() section {
	s := section{title: fmt.Sprintf("Report files modified in the last %d days", reportWindowDays)}
	cutoff := time.Now().AddDate(0, 0, -reportWindowDays)

	var roots []string
	roots = append(roots, userDocumentRoots()...)
	for _, root := range scanRoots() {
		entries, err := os.ReadDir(root)
		if err != nil {
			continue
		}
		for _, e := range entries {
			if e.IsDir() && matchesKeyword(e.Name()) {
				roots = append(roots, filepath.Join(root, e.Name()))
			}
		}
	}

	seen := map[string]bool{}
	for _, root := range roots {
		if seen[root] {
			continue
		}
		seen[root] = true
		rootDepth := strings.Count(filepath.Clean(root), string(os.PathSeparator))
		_ = filepath.WalkDir(root, func(path string, d fs.DirEntry, err error) error {
			if err != nil {
				return nil
			}
			if d.IsDir() {
				if skipDir(d.Name()) {
					return filepath.SkipDir
				}
				if strings.Count(filepath.Clean(path), string(os.PathSeparator))-rootDepth >= scanMaxDepth {
					return filepath.SkipDir
				}
				return nil
			}
			if !reportExts[strings.ToLower(filepath.Ext(d.Name()))] {
				return nil
			}
			info, err := d.Info()
			if err != nil || info.ModTime().Before(cutoff) {
				return nil
			}
			line := fmt.Sprintf("%s  (%s, modified %s)",
				path, humanSize(info.Size()), info.ModTime().Format("2006-01-02 15:04"))
			if strings.EqualFold(filepath.Ext(path), ".csv") {
				if head := firstLine(path); head != "" {
					line += "\n    first row: " + head
				}
			}
			s.lines = append(s.lines, line)
			return nil
		})
	}
	return s
}

func firstLine(path string) string {
	f, err := os.Open(path)
	if err != nil {
		return ""
	}
	defer f.Close()

	buf := make([]byte, 512)
	n, _ := f.Read(buf)
	head := string(buf[:n])
	if i := strings.IndexAny(head, "\r\n"); i >= 0 {
		head = head[:i]
	}
	return strings.TrimSpace(head)
}

func humanSize(n int64) string {
	switch {
	case n >= 1<<30:
		return fmt.Sprintf("%.1f GB", float64(n)/(1<<30))
	case n >= 1<<20:
		return fmt.Sprintf("%.1f MB", float64(n)/(1<<20))
	case n >= 1<<10:
		return fmt.Sprintf("%.0f KB", float64(n)/(1<<10))
	default:
		return fmt.Sprintf("%d B", n)
	}
}
