package main

// Extractor is the pluggable seam between "how this terminal's POS exposes
// sales data" and everything else. v1 ships FolderExtractor (a watched
// export directory); whatever discovery reveals — a Firebird query, a
// vendor CLI export, a cloud API — lands here as another implementation
// without touching the spool, the uploads or the service loop.
type Extractor interface {
	Name() string

	// Collect returns paths of candidate report files. The caller consults
	// the spool ledger for what was already sent — an extractor never has
	// to remember anything.
	Collect(cfg *Config) ([]string, error)
}

func newExtractor(cfg *Config) Extractor {
	// Only one so far; a "db" extractor will switch on cfg.Extractor here.
	return &FolderExtractor{}
}
