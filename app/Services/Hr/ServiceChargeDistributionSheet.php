<?php

namespace App\Services\Hr;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * The service charge distribution as a spreadsheet.
 *
 * The same document as the distribution PDF and built from the same gather(),
 * so the two cannot disagree about what a period paid — which is the whole
 * reason the distribution is computed in one service and rendered in several.
 *
 * It carries MORE than the PDF, for the reason the payroll sheet does: a page
 * runs out of width and a sheet does not, and the point of a spreadsheet here
 * is to sort, filter and cross-total against something else. Two of those
 * extra columns matter most — the staff id, so a row can be matched against a
 * payroll or a bank list, and a STATUS that says in words why a share is nil.
 * On the PDF that reason is a footnote and a shade of grey; a filter cannot
 * read either.
 *
 * WHAT IT IS NOT: a payment instruction. It is the working behind one, and a
 * pool that has not been calculated says so on its own face rather than only
 * on the screen it came from.
 */
class ServiceChargeDistributionSheet
{
    /**
     * Every column, in order: heading, kind, and how to read it off a row.
     *
     * One list rather than a header list beside a value list, the same shape
     * as the payroll sheet and for the same reason: two lists drift, and a
     * value pushed where a heading was not shifts every column after it. That
     * reads as wrong figures rather than as a missing column.
     *
     * @return array<int, array{0: string, 1: string, 2: callable}>
     */
    private function columns(array $sc): array
    {
        $showDays = (int) ($sc['minDays'] ?? 0) > 0;
        $hasLate  = (bool) ($sc['hasLate'] ?? false);

        return array_values(array_filter([
            ['Name',           'text',  fn ($r) => $r['employee']->name],
            ['Staff ID',       'text',  fn ($r) => $r['employee']->staff_id],
            ['Outlet',         'text',  fn ($r) => $r['employee']->outlet?->name],
            ['Section',        'text',  fn ($r) => $r['employee']->section?->name],
            // Why a nil is nil, in words a filter can find. Three different
            // reasons produce the same zero and they mean different things to
            // whoever reads this.
            ['Status',         'text',  fn ($r) => $this->status($r)],
            ['Service Points', 'qty',   fn ($r) => (float) $r['points']],
            // Only where a minimum applies, and then always: a zero row on a
            // document somebody signs has to carry the count it was judged on.
            $showDays ? ['Working Days', 'int', fn ($r) => (int) ($r['workDays'] ?? 0)] : null,
            ['MC Days',        'int',   fn ($r) => (int) $r['mcDays']],
            ['Absent Days',    'int',   fn ($r) => (int) $r['absDays']],
            ['Deduction %',    'qty',   fn ($r) => (float) $r['dedPct']],
            ['Gross (RM)',     'money', fn ($r) => (float) $r['gross']],
            ['Deduction (RM)', 'money', fn ($r) => -1 * (float) $r['dedAmt']],
            $hasLate ? ['Late (min)', 'int',   fn ($r) => (int) $r['lateMins']] : null,
            $hasLate ? ['Late (RM)',  'money', fn ($r) => -1 * (float) $r['lateAmt']] : null,
            ['Special (RM)',   'money', fn ($r) => -1 * (float) $r['specialAmt']],
            ['Special reason', 'text',  fn ($r) => $r['specialNote']],
            ['Net (RM)',       'money', fn ($r) => (float) $r['net']],
        ]));
    }

    /**
     * The reason a row pays what it pays.
     *
     * Ordered the way the screen orders it, and for the same reason: "paid
     * from KLCC" and "excluded" both produce a zero and mean opposite things —
     * one person is getting nothing, the other is getting it from another
     * branch. Saying "excluded" for the second would read as a lost payment.
     */
    private function status(array $r): string
    {
        return match (true) {
            (bool) ($r['elsewhere'] ?? false)    => 'Paid from ' . ($r['employee']->serviceChargeOutlet?->name ?? 'another outlet'),
            (bool) ($r['notInPool'] ?? false)    => 'Not in this calculation',
            (bool) ($r['belowMinDays'] ?? false) => 'Under the minimum working days',
            (bool) $r['excluded']                => 'Excluded from this pool',
            (float) $r['points'] <= 0            => 'No service points',
            default                              => 'Paid',
        };
    }

    /**
     * Write the workbook and return the temp file it was saved to.
     *
     * @param  array<string, mixed>  $data  the return of AttendanceExportController::gather()
     */
    public function write(array $data, string $exportedBy): string
    {
        $sc      = $data['serviceCharge'];
        $rows    = collect($sc['rows']);
        $columns = $this->columns($sc);

        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()
            ->setCreator($exportedBy)
            ->setTitle('Service Charge Distribution');

        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Distribution');

        // Column 1 is the row number, so the declared columns start at 2.
        $firstDataCol = 2;
        $lastCol = Coordinate::stringFromColumnIndex(count($columns) + 1);

        // ── Title block ──────────────────────────────────────────────────
        $sheet->setCellValueExplicit(
            'A1',
            trim(($data['brandName'] ?? '') . ' — Service Charge Distribution'),
            DataType::TYPE_STRING
        );
        $sheet->mergeCells('A1:' . $lastCol . '1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFECFDF5');
        $sheet->getRowDimension(1)->setRowHeight(24);

        $sheet->setCellValueExplicit('A2', implode(' · ', array_filter([
            $data['from']->format('d M Y') . ' – ' . $data['to']->format('d M Y'),
            $data['outletName'] ?? 'All outlets',
            ($sc['minDays'] ?? 0) > 0 ? 'Minimum ' . $sc['minDays'] . ' working days' : null,
            ($sc['frozen'] ?? false) && ($sc['calculatedAt'] ?? null)
                ? 'Calculated ' . $sc['calculatedAt']->format('d M Y, h:i A')
                    . (($sc['calculatedBy'] ?? null) ? ' by ' . $sc['calculatedBy'] : '')
                : null,
            'Exported ' . now()->format('d M Y, h:i A') . ' by ' . $exportedBy,
        ])), DataType::TYPE_STRING);
        $sheet->mergeCells('A2:' . $lastCol . '2');
        $sheet->getStyle('A2')->getFont()->setSize(9)->getColor()->setARGB('FF6B7280');

        /*
         * A POOL THAT HAS NOT BEEN CALCULATED SAYS SO, above the headings.
         *
         * Its RM/point is worked out live and moves whenever staff change,
         * which is the whole reason calculating freezes it. A sheet taken off
         * one is a snapshot of figures that have not been agreed, and a
         * caveat that lives only on the screen the file came from is lost the
         * moment the file is emailed to somebody else.
         */
        $headerRow = 4;

        if (! ($sc['frozen'] ?? false)) {
            $sheet->setCellValueExplicit(
                'A3',
                'NOT CALCULATED — these figures are worked out live and will move if staff, points or '
                    . 'attendance change. Press Save & Calculate on the attendance record to fix them.',
                DataType::TYPE_STRING
            );
            $sheet->mergeCells('A3:' . $lastCol . '3');
            $sheet->getStyle('A3')->getFont()->setBold(true)->getColor()->setARGB('FF92400E');
            $sheet->getStyle('A3')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFEF3C7');
            $sheet->getRowDimension(3)->setRowHeight(18);
        }

        // ── Header row ───────────────────────────────────────────────────
        $sheet->setCellValueExplicit('A' . $headerRow, 'No.', DataType::TYPE_STRING);

        foreach ($columns as $i => [$label, $kind, $read]) {
            $sheet->setCellValueExplicit([$i + $firstDataCol, $headerRow], $label, DataType::TYPE_STRING);
        }

        $headerRange = 'A' . $headerRow . ':' . $lastCol . $headerRow;
        $sheet->getStyle($headerRange)->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle($headerRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF115E59');
        $sheet->getStyle($headerRange)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
        $sheet->getRowDimension($headerRow)->setRowHeight(28);

        // ── Data rows ────────────────────────────────────────────────────
        $row = $headerRow;

        foreach ($rows as $i => $r) {
            $row++;

            $sheet->setCellValue('A' . $row, $i + 1);

            foreach ($columns as $c => [$label, $kind, $read]) {
                $value = $read($r);

                if ($kind === 'text') {
                    // Explicit strings, so a staff id keeps its leading zeros
                    // and a name beginning "=" is not read as a formula.
                    $sheet->setCellValueExplicit(
                        [$c + $firstDataCol, $row],
                        (string) ($value ?? ''),
                        DataType::TYPE_STRING
                    );
                } else {
                    $sheet->setCellValue([$c + $firstDataCol, $row], $kind === 'int' ? (int) $value : (float) $value);
                }
            }

            if ($row % 2 === 0) {
                $sheet->getStyle('A' . $row . ':' . $lastCol . $row)->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFF9FAFB');
            }
        }

        $lastDataRow = $row;

        // ── Staff total ──────────────────────────────────────────────────
        if ($rows->isNotEmpty()) {
            $row++;
            $sheet->setCellValueExplicit('A' . $row, 'STAFF TOTAL', DataType::TYPE_STRING);

            foreach ($columns as $c => [$label, $kind, $read]) {
                // Not the percentage: a column of per-person rates has no
                // total, and one printed under it would be read as an average
                // it is not.
                if (! in_array($kind, ['money', 'qty', 'int'], true) || $label === 'Deduction %') {
                    continue;
                }

                $col = Coordinate::stringFromColumnIndex($c + $firstDataCol);

                /*
                 * SUBTOTAL(9,…) rather than SUM, and a formula rather than a
                 * figure computed here — whoever opens this will filter it by
                 * section or by outlet, and SUBTOTAL re-totals what the filter
                 * left. A pasted constant would state the total of rows no
                 * longer on screen.
                 */
                $sheet->setCellValue(
                    $col . $row,
                    '=SUBTOTAL(9,' . $col . ($headerRow + 1) . ':' . $col . $lastDataRow . ')'
                );
            }

            $totalRange = 'A' . $row . ':' . $lastCol . $row;
            $sheet->getStyle($totalRange)->getFont()->setBold(true);
            $sheet->getStyle($totalRange)->getFill()
                ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFECFDF5');
            $sheet->getStyle($totalRange)->getBorders()->getTop()
                ->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FF9CA3AF');
        }

        $lastTableRow = $row;

        // ── Funds ────────────────────────────────────────────────────────
        // Below the total rather than among the staff rows: they are paid at
        // the same rate out of the same pool, but they are not people, and a
        // filter on Section would drop them from a total that must include
        // them for the allocation to add up.
        foreach ($sc['funds'] ?? [] as $fund) {
            $row++;
            $sheet->setCellValueExplicit('A' . $row, 'FUND', DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('B' . $row, $fund['name'], DataType::TYPE_STRING);
            $sheet->setCellValue([count($columns) + 1, $row], (float) $fund['amount']);
            $sheet->getStyle('A' . $row . ':' . $lastCol . $row)->getFont()->setItalic(true);
        }

        // ── The pool itself ──────────────────────────────────────────────
        // The arithmetic above the table, so the sheet can answer "why is a
        // point worth this" without opening the screen it came from.
        $row += 2;
        $summaryTop = $row;

        $summary = array_filter([
            ['Service charge collected (RM)', (float) $sc['collected']],
            ($sc['retentionPct'] ?? 0) > 0
                ? ['Company retention (' . rtrim(rtrim(number_format((float) $sc['retentionPct'], 2, '.', ''), '0'), '.') . '%) (RM)', -1 * (float) $sc['retentionAmt']]
                : null,
            ['Distributable (RM)',            (float) $sc['distributable']],
            ['Staff points',                  (float) $sc['staffPoints']],
            ($sc['fundPoints'] ?? 0) > 0 ? ['Fund points', (float) $sc['fundPoints']] : null,
            ['Total points',                  (float) $sc['totalPoints']],
            ['RM per point',                  (float) $sc['perPoint']],
            ['Allocated (RM)',                (float) $sc['allocated']],
            // What the rounding left behind. Named, because a pool that does
            // not add up to its own collected figure is the first thing
            // somebody queries.
            ['Undistributed remainder (RM)',  round((float) $sc['distributable'] - (float) $sc['allocated'], 2)],
        ]);

        foreach ($summary as [$label, $value]) {
            $sheet->setCellValueExplicit('A' . $row, $label, DataType::TYPE_STRING);
            $sheet->mergeCells('A' . $row . ':B' . $row);
            $sheet->setCellValue('C' . $row, $value);
            $sheet->getStyle('C' . $row)->getNumberFormat()->setFormatCode('#,##0.00');
            $row++;
        }

        $sheet->getStyle('A' . $summaryTop . ':A' . ($row - 1))->getFont()->setBold(true);

        // ── Formats and furniture ────────────────────────────────────────
        if ($lastDataRow > $headerRow) {
            $sheet->getStyle('A' . $headerRow . ':' . $lastCol . $lastTableRow)->getBorders()->getAllBorders()
                ->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FFE5E7EB');

            // The filter covers the data only — a totals row inside the range
            // gets sorted in among the employees.
            $sheet->setAutoFilter('A' . $headerRow . ':' . $lastCol . $lastDataRow);

            foreach ($columns as $c => [$label, $kind, $read]) {
                if ($kind === 'text') {
                    continue;
                }

                $col    = Coordinate::stringFromColumnIndex($c + $firstDataCol);
                $format = $kind === 'int' ? '0' : '#,##0.00';

                $sheet->getStyle($col . ($headerRow + 1) . ':' . $col . $lastTableRow)
                    ->getNumberFormat()->setFormatCode($format);
            }
        }

        // Frozen below the headings and to the right of the name, so scrolling
        // out to the money columns keeps saying whose row it is.
        $sheet->freezePane('C' . ($headerRow + 1));

        foreach (range(1, count($columns) + 1) as $i) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setAutoSize(true);
        }

        $tmp = tempnam(sys_get_temp_dir(), 'scdist');
        (new Xlsx($spreadsheet))->save($tmp);

        return $tmp;
    }

    /** Calculated or not is in the NAME as well as the banner, so two exports
     *  of one period cannot land in a downloads folder under one filename. */
    public function filename(array $data): string
    {
        return 'Service-Charge-Distribution-'
            . $data['from']->format('Y-m-d') . '-to-' . $data['to']->format('Y-m-d')
            . (($data['serviceCharge']['frozen'] ?? false) ? '' : '-NOT-CALCULATED')
            . '.xlsx';
    }
}
