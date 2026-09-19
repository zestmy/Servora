<?php

if (! function_exists('ordinal')) {
    /**
     * Format an integer with its English ordinal suffix (1 => "1st", 22 => "22nd").
     */
    function ordinal(int $number): string
    {
        if (in_array($number % 100, [11, 12, 13], true)) {
            $suffix = 'th';
        } else {
            $suffix = ['th', 'st', 'nd', 'rd'][$number % 10] ?? 'th';
        }

        return $number . $suffix;
    }
}

if (! function_exists('brand_asset')) {
    /**
     * A public asset URL with the file's own mtime on it.
     *
     * The logo files keep their names across identity changes, because every
     * layout points at them and renaming would mean touching all of those on
     * every rebrand. The cost of that is cache: same URL, new bytes, so a
     * browser or CDN that already holds the old image keeps serving it and
     * the rebrand simply does not appear for anyone who visited before.
     *
     * Hanging the mtime off the URL fixes that without renaming anything —
     * replace the file and the URL changes with it, so every cache in the
     * chain misses exactly once and then holds the new one.
     *
     * Falls back to a plain asset() when the file is not on disk (a missing
     * favicon should 404 as itself, not blow up the page that references it).
     */
    function brand_asset(string $path): string
    {
        static $versions = [];

        $relative = ltrim($path, '/');

        if (! array_key_exists($relative, $versions)) {
            $file = public_path($relative);
            $versions[$relative] = is_file($file) ? filemtime($file) : null;
        }

        $url = asset($relative);

        return $versions[$relative] === null ? $url : $url . '?v=' . $versions[$relative];
    }
}
