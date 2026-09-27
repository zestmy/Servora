<?php

namespace App\Services\Hr;

use Carbon\Carbon;
use Smalot\PdfParser\Parser;

/**
 * Reads a weekly duty roster that was built in Excel and saved as PDF.
 *
 * Outlets have kept their rosters in a spreadsheet for years, and the sheet
 * is laid out the same way everywhere: a row of dates, a row of day names,
 * then two lines per person — name and shifts on the first, position and
 * stations under them on the second. Retyping that into the grid is the
 * chore this exists to remove.
 *
 * It works from where the text sits on the page, not from the order it was
 * written in. Excel centres each cell, so a cell's column is the day header
 * whose centre is nearest to the cell's centre; a line is everything at the
 * same height. That is deterministic, costs nothing per upload, and gives the
 * same answer twice — which matters for something people will re-upload
 * every Friday after moving two shifts.
 *
 * The result is a plain array for the review screen. Nothing here touches the
 * database; matching names to employees is RosterPdfImport's job.
 */
class RosterPdfParser
{
    private const DAY_NAMES = [
        'MON' => 1, 'TUE' => 2, 'WED' => 3, 'THU' => 4, 'FRI' => 5, 'SAT' => 6, 'SUN' => 7,
    ];

    /** Left-column labels whose cells are notes about the day, not a person. */
    private const REMARK_LABELS = [
        'PUBLIC HOLIDAY' => 'public_holiday',
        'HOLIDAY'        => 'public_holiday',
        'EVENTS'         => 'event',
        'EVENT'          => 'event',
        'REMARKS'        => 'custom',
        'REMARK'         => 'custom',
        'NOTES'          => 'custom',
    ];

    /** Cell text that means "not working", mapped to RosterEntry::LEAVE_*. */
    private const LEAVE_WORDS = [
        'OFF' => 'off', 'OFF DAY' => 'off', 'OFFDAY' => 'off', 'OD' => 'off', 'REST DAY' => 'off', 'RD' => 'off',
        'AL' => 'al', 'ANNUAL LEAVE' => 'al', 'LEAVE' => 'al',
        'MC' => 'mc', 'SICK' => 'mc', 'MEDICAL LEAVE' => 'mc', 'SL' => 'mc',
        'RPH' => 'rph', 'PH' => 'rph', 'PUBLIC HOLIDAY' => 'rph',
        'RDO' => 'rdo', 'REPLACEMENT' => 'rdo',
        'CH' => 'ch', 'CLAIM HOUR' => 'ch', 'CLAIM HOURS' => 'ch',
    ];

    /** Rough half-width of one character, in points, for centring a string. */
    private const HALF_CHAR = 2.5;

    /** Two pieces of text within this many points vertically share a line. */
    private const LINE_TOLERANCE = 3.0;

    public function parseFile(string $path, ?Carbon $today = null): array
    {
        try {
            $pdf = (new Parser())->parseFile($path);
        } catch (\Throwable $e) {
            throw new RosterPdfException('This file could not be read as a PDF.');
        }

        $pages = [];
        foreach ($pdf->getPages() as $page) {
            $items = [];
            foreach ($page->getDataTm() as [$matrix, $text]) {
                $items[] = ['x' => (float) $matrix[4], 'y' => (float) $matrix[5], 'text' => (string) $text];
            }
            $pages[] = $items;
        }

        return $this->parsePages($pages, $today);
    }

    /**
     * @param array<int, array<int, array{x: float, y: float, text: string}>> $pages
     *        Text pieces per page, PDF coordinates (y grows upwards).
     */
    public function parsePages(array $pages, ?Carbon $today = null): array
    {
        $today ??= Carbon::now();

        $columns = null;
        $headerDates = [];
        $headerDays = [];
        $title = [];
        $rows = [];
        $remarks = [];
        $current = null;
        $stopped = false;
        $sawText = false;

        foreach ($pages as $items) {
            $lines = $this->lines($items);
            if ($lines) {
                $sawText = true;
            }

            foreach ($lines as $line) {
                // The header can repeat on every page; take the first one
                // and let later ones simply re-anchor the columns.
                if ($found = $this->headerColumns($line)) {
                    [$kind, $cols] = $found;
                    $columns = $cols;
                    if ($kind === 'dates' && ! $headerDates) {
                        $headerDates = array_column($cols, 'text');
                    }
                    if ($kind === 'days' && ! $headerDays) {
                        $headerDays = array_column($cols, 'text');
                    }
                    $current = null;
                    continue;
                }

                if (! $columns) {
                    // Above the grid: the title strip ("DUTY ROSTER", outlet name).
                    $title[] = implode('  ', array_column($line, 'text'));
                    continue;
                }

                if ($stopped) {
                    continue;
                }

                [$label, $cells] = $this->split($line, $columns);
                $labelUpper = $this->upper($label);

                if (str_starts_with($labelUpper, 'NOTICE') || str_starts_with($labelUpper, 'NOTE:')) {
                    $stopped = true;
                    continue;
                }

                if ($remarkType = $this->remarkType($labelUpper)) {
                    foreach ($cells as $col => $text) {
                        $remarks[] = ['col' => $col, 'type' => $this->remarkTypeFor($text, $remarkType), 'text' => $text];
                    }
                    $current = null;
                    continue;
                }

                $numbered = preg_match('/^\d{1,3}[.)]?\s+(.+)$/u', $label, $m) === 1;
                $name = $numbered ? trim($m[1]) : trim($label);

                $parsedCells = array_map(fn ($t) => $this->parseCell($t), $cells);
                $hasShift = collect($parsedCells)->contains(fn ($c) => in_array($c['kind'], ['shift', 'leave'], true));
                $allNumeric = $cells && collect($cells)->every(fn ($t) => preg_match('/^\d+(\.\d+)?$/', $t));

                if ($allNumeric || ($label !== '' && ! $cells && ! $numbered && $current === null)) {
                    // Headcount totals under the grid (OPENING, CLOSING, TOTAL…).
                    $current = null;
                    continue;
                }

                if ($name !== '' && ($numbered || $hasShift) && ! $allNumeric) {
                    $rows[] = [
                        'name'     => $name,
                        'position' => null,
                        'cells'    => $parsedCells,
                    ];
                    $current = array_key_last($rows);
                    continue;
                }

                if ($current !== null) {
                    // A line under a person: the position on the left, and the
                    // station each shift is worked at under that shift.
                    if ($label !== '' && $rows[$current]['position'] === null) {
                        $rows[$current]['position'] = $label;
                    }
                    foreach ($cells as $col => $text) {
                        $cell = &$rows[$current]['cells'][$col];
                        $cell ??= $this->parseCell('');
                        $cell['station'] = isset($cell['station']) && $cell['station'] !== ''
                            ? $cell['station'] . ' ' . $text
                            : $text;
                        unset($cell);
                    }
                }
            }
        }

        if (! $sawText) {
            throw new RosterPdfException(
                'This PDF has no readable text — it looks like a scan or a photo. '
                . 'Open the roster in Excel and use File → Save as PDF instead.'
            );
        }

        if (! $columns) {
            throw new RosterPdfException(
                'Could not find the day columns. The roster needs a header row with the dates '
                . '(e.g. 28-Sep) or the day names (MONDAY … SUNDAY).'
            );
        }

        if (! $rows) {
            throw new RosterPdfException('Found the day columns but no staff rows underneath them.');
        }

        $dates = $this->resolveDates($headerDates, $headerDays, count($columns), $title, $today);

        // Number the cells by date rather than column, so everything
        // downstream speaks the same language as the roster grid.
        foreach ($rows as &$row) {
            $byDate = [];
            foreach ($dates as $col => $date) {
                $byDate[$date] = $row['cells'][$col] ?? $this->parseCell('');
                $byDate[$date]['station'] ??= null;
            }
            $row['cells'] = $byDate;
        }
        unset($row);

        $dayRemarks = [];
        foreach ($remarks as $remark) {
            $date = $dates[$remark['col']] ?? null;
            if (! $date) {
                continue;
            }
            // One remark per day in the roster, so several notes on a day
            // are joined and the strongest type wins.
            if (isset($dayRemarks[$date])) {
                $dayRemarks[$date]['text'] .= ' · ' . $remark['text'];
                if ($dayRemarks[$date]['type'] === 'custom') {
                    $dayRemarks[$date]['type'] = $remark['type'];
                }
            } else {
                $dayRemarks[$date] = ['type' => $remark['type'], 'text' => $remark['text']];
            }
        }

        return [
            'week_start' => $dates[0] ?? null,
            'week_end'   => end($dates) ?: null,
            'dates'      => array_values($dates),
            'title'      => trim(implode(' · ', array_filter(array_map('trim', $title)))),
            'rows'       => $rows,
            'remarks'    => $dayRemarks,
        ];
    }

    /**
     * What one cell of the sheet says.
     *
     * @return array{kind: string, raw: string, start: ?string, end: ?string, leave: ?string, station: ?string}
     *         kind is shift | leave | note | blank. A note is something the
     *         sheet says that is neither times nor leave ("MITEC EVENT"): the
     *         person is working, the times are for a human to fill in.
     */
    public function parseCell(string $text): array
    {
        $raw = trim(preg_replace('/\s+/u', ' ', $text));
        $cell = ['kind' => 'blank', 'raw' => $raw, 'start' => null, 'end' => null, 'leave' => null, 'station' => null];

        if ($raw === '' || $raw === '-') {
            return $cell;
        }

        $upper = $this->upper($raw);
        $word = trim(preg_replace('/[^A-Z ]/', '', $upper));
        if (isset(self::LEAVE_WORDS[$word])) {
            return ['kind' => 'leave', 'leave' => self::LEAVE_WORDS[$word]] + $cell;
        }

        if ($times = $this->parseTimeRange($upper)) {
            return ['kind' => 'shift', 'start' => $times[0], 'end' => $times[1]] + $cell;
        }

        return ['kind' => 'note'] + $cell;
    }

    /**
     * "7AM-3.30PM", "12.00PM-10.30PM", "7:30 am – 4pm", "0700-1530", "9-5".
     *
     * @return array{0: string, 1: string}|null  24-hour "H:i" start and end
     */
    public function parseTimeRange(string $text): ?array
    {
        $t = strtoupper(str_replace(['–', '—', ' TO '], '-', $text));
        $t = preg_replace('/\s+/', '', $t);

        $part = '(\d{1,2})(?:[.:]?(\d{2}))?(AM|PM|A|P)?';
        if (! preg_match('/^' . $part . '-' . $part . '$/', $t, $m)) {
            return null;
        }

        [$h1, $m1, $ap1, $h2, $m2, $ap2] = [
            (int) $m[1], (int) ($m[2] ?? 0), $this->meridiem($m[3] ?? ''),
            (int) $m[4], (int) ($m[5] ?? 0), $this->meridiem($m[6] ?? ''),
        ];

        if ($m1 > 59 || $m2 > 59) {
            return null;
        }

        // No AM/PM anywhere: either 24-hour ("0700-1530", "14-22") or a
        // lazy "9-5". Hours above 12 settle it; otherwise read it as a day
        // shift, which is what "9-5" means on every roster ever written.
        if ($ap1 === null && $ap2 === null) {
            if ($h1 > 23 || $h2 > 24) {
                return null;
            }
            if ($h1 > 12 || $h2 > 12 || str_starts_with($m[1], '0')) {
                return [$this->hm($h1 % 24, $m1), $this->hm($h2 % 24, $m2)];
            }
            $ap1 = $h1 >= 7 && $h1 < 12 ? 'AM' : 'PM';
            $ap2 = $h2 < $h1 || $h2 === 12 || $ap1 === 'PM' ? 'PM' : 'AM';
        }

        if ($h1 < 1 || $h1 > 12 || $h2 < 1 || $h2 > 12) {
            return null;
        }

        // One side missing its AM/PM ("7-3.30PM"): pick whichever reading
        // gives a believable shift length.
        if ($ap1 === null) {
            $ap1 = $this->plausibleMeridiem($h1, $m1, $h2, $m2, $ap2, startMissing: true);
        }
        if ($ap2 === null) {
            $ap2 = $this->plausibleMeridiem($h2, $m2, $h1, $m1, $ap1, startMissing: false);
        }

        return [$this->hm($this->to24($h1, $ap1), $m1), $this->hm($this->to24($h2, $ap2), $m2)];
    }

    private function plausibleMeridiem(int $h, int $m, int $otherH, int $otherM, string $otherAp, bool $startMissing): string
    {
        $other = $this->to24($otherH, $otherAp) * 60 + $otherM;
        foreach (['AM', 'PM'] as $ap) {
            $mine = $this->to24($h, $ap) * 60 + $m;
            $length = $startMissing ? $other - $mine : $mine - $other;
            if ($length < 0) {
                $length += 1440;
            }
            if ($length >= 180 && $length <= 16 * 60) {
                return $ap;
            }
        }

        return $otherAp;
    }

    private function meridiem(string $s): ?string
    {
        return match ($s) {
            'AM', 'A' => 'AM',
            'PM', 'P' => 'PM',
            default   => null,
        };
    }

    private function to24(int $h, string $ap): int
    {
        return $ap === 'AM' ? ($h === 12 ? 0 : $h) : ($h === 12 ? 12 : $h + 12);
    }

    private function hm(int $h, int $m): string
    {
        return sprintf('%02d:%02d', $h, $m);
    }

    /**
     * Group text pieces into lines, top of the page first, each line's
     * pieces left to right. Pieces that are pure whitespace are dropped.
     */
    private function lines(array $items): array
    {
        $items = array_values(array_filter($items, fn ($i) => trim($i['text']) !== ''));
        usort($items, fn ($a, $b) => [$b['y'], $a['x']] <=> [$a['y'], $b['x']]);

        $lines = [];
        foreach ($items as $item) {
            $last = array_key_last($lines);
            if ($last !== null && abs($lines[$last][0]['y'] - $item['y']) <= self::LINE_TOLERANCE) {
                $lines[$last][] = $item;
            } else {
                $lines[] = [$item];
            }
        }

        foreach ($lines as &$line) {
            usort($line, fn ($a, $b) => $a['x'] <=> $b['x']);
        }

        return $lines;
    }

    /**
     * Is this line the header that fixes the day columns? Either the dates
     * ("28-Sep", "28/9", "28 Sep") or the day names, at least five of them.
     *
     * @return array{0: string, 1: array<int, array{center: float, text: string}>}|null
     */
    private function headerColumns(array $line): ?array
    {
        $days = [];
        $dates = [];

        foreach ($line as $item) {
            $text = trim($item['text']);
            $upper = $this->upper($text);
            $center = $this->center($item);

            if (isset(self::DAY_NAMES[substr($upper, 0, 3)]) && preg_match('/^[A-Z]{3,9}$/', $upper)
                && (strlen($upper) === 3 || str_starts_with($this->fullDayName(substr($upper, 0, 3)), $upper))) {
                $days[] = ['center' => $center, 'text' => $upper];
            } elseif (preg_match('/^\d{1,2}[\-\/ .](\d{1,2}|[A-Za-z]{3,9})([\-\/ .]\d{2,4})?$/', $text)) {
                $dates[] = ['center' => $center, 'text' => $text];
            }
        }

        if (count($dates) >= 5) {
            return ['dates', $dates];
        }
        if (count($days) >= 5) {
            return ['days', $days];
        }

        return null;
    }

    private function fullDayName(string $abbr): string
    {
        return [
            'MON' => 'MONDAY', 'TUE' => 'TUESDAY', 'WED' => 'WEDNESDAY', 'THU' => 'THURSDAY',
            'FRI' => 'FRIDAY', 'SAT' => 'SATURDAY', 'SUN' => 'SUNDAY',
        ][$abbr];
    }

    /**
     * Split a line into its left-hand label and the text under each day.
     * Anything right of the last day (a weekly OT total) is dropped.
     *
     * @return array{0: string, 1: array<int, string>}
     */
    private function split(array $line, array $columns): array
    {
        $centers = array_column($columns, 'center');
        $pitch = count($centers) > 1 ? ($centers[count($centers) - 1] - $centers[0]) / (count($centers) - 1) : 100;
        $firstEdge = $centers[0] - $pitch / 2;
        $lastEdge = $centers[count($centers) - 1] + $pitch / 2;

        $label = [];
        $cells = [];
        foreach ($line as $item) {
            $c = $this->center($item);
            if ($c < $firstEdge) {
                $label[] = $item['text'];
                continue;
            }
            if ($c > $lastEdge) {
                continue;
            }
            $col = 0;
            $best = INF;
            foreach ($centers as $i => $center) {
                if (abs($center - $c) < $best) {
                    $best = abs($center - $c);
                    $col = $i;
                }
            }
            $cells[$col] = isset($cells[$col]) ? $cells[$col] . ' ' . $item['text'] : $item['text'];
        }

        $cells = array_filter(
            array_map(fn ($t) => trim(preg_replace('/\s+/u', ' ', $t)), $cells),
            fn ($t) => $t !== ''
        );
        ksort($cells);

        return [trim(preg_replace('/\s+/u', ' ', implode(' ', $label))), $cells];
    }

    private function center(array $item): float
    {
        return $item['x'] + mb_strlen(trim($item['text'])) * self::HALF_CHAR;
    }

    private function remarkType(string $label): ?string
    {
        foreach (self::REMARK_LABELS as $word => $type) {
            if ($label === $word || str_starts_with($label, $word . ' ') || str_starts_with($label, $word . '/')) {
                return $type;
            }
        }

        return null;
    }

    /** A stocktake written in the holiday row is still a stocktake. */
    private function remarkTypeFor(string $text, string $rowType): string
    {
        $upper = $this->upper($text);
        if (str_contains($upper, 'STOCK TAKE') || str_contains($upper, 'STOCKTAKE')) {
            return 'stocktake';
        }
        if ($rowType === 'public_holiday' && ! preg_match('/HOLIDAY|\bPH\b|\bDAY\b|RAYA|NEW YEAR|MERDEKA|DEEPAVALI|CHRISTMAS|WESAK|NATIONAL/', $upper)) {
            // Excel rosters reuse the holiday strip for anything that
            // affects the whole day ("WIP MEETING"); call that what it is.
            return 'custom';
        }

        return $rowType;
    }

    /**
     * Turn the header into seven real dates. A date header gives day and
     * month but rarely the year, so the year is the one that puts the week
     * nearest today — and, when day names are printed too, makes them agree.
     *
     * @return array<int, string> Y-m-d per column
     */
    private function resolveDates(array $dateTexts, array $dayTexts, int $count, array $title, Carbon $today): array
    {
        $first = $dateTexts[0] ?? null;

        if ($first !== null) {
            [$day, $month, $year] = $this->splitDate($first);
            if ($day && $month) {
                $years = $year ? [$year] : [$today->year - 1, $today->year, $today->year + 1];
                $wantedDow = isset($dayTexts[0]) ? self::DAY_NAMES[substr($dayTexts[0], 0, 3)] : null;

                $best = null;
                foreach ($years as $y) {
                    if (! checkdate($month, $day, $y)) {
                        continue;
                    }
                    $candidate = Carbon::create($y, $month, $day)->startOfDay();
                    if ($wantedDow && $candidate->dayOfWeekIso !== $wantedDow) {
                        continue;
                    }
                    if (! $best || abs($candidate->diffInDays($today, false)) < abs($best->diffInDays($today, false))) {
                        $best = $candidate;
                    }
                }

                if ($best) {
                    $start = $best;
                }
            }
        }

        if (! isset($start)) {
            throw new RosterPdfException(
                'Could not work out which week this roster is for. Make sure the header shows the dates, e.g. 28-Sep.'
            );
        }

        if ($start->dayOfWeekIso !== 1) {
            throw new RosterPdfException(
                'Rosters in Servora run Monday to Sunday, but this sheet starts on '
                . $start->format('l, j M') . '. Adjust the sheet to start on a Monday and upload again.'
            );
        }

        $dates = [];
        for ($i = 0; $i < min($count, 7); $i++) {
            $dates[$i] = $start->copy()->addDays($i)->format('Y-m-d');
        }

        return $dates;
    }

    /** @return array{0: ?int, 1: ?int, 2: ?int} day, month, year */
    private function splitDate(string $text): array
    {
        $parts = preg_split('/[\-\/ .]+/', trim($text));
        $day = (int) ($parts[0] ?? 0);
        $monthPart = $parts[1] ?? '';
        $month = ctype_digit($monthPart)
            ? (int) $monthPart
            : (Carbon::hasFormat(ucfirst(strtolower(substr($monthPart, 0, 3))), 'M')
                ? Carbon::createFromFormat('!M', ucfirst(strtolower(substr($monthPart, 0, 3))))->month
                : null);
        $year = isset($parts[2]) ? (int) $parts[2] : null;
        if ($year !== null && $year < 100) {
            $year += 2000;
        }

        return [$day ?: null, $month && $month <= 12 ? $month : null, $year];
    }

    private function upper(string $s): string
    {
        return mb_strtoupper(trim(preg_replace('/\s+/u', ' ', $s)));
    }
}
