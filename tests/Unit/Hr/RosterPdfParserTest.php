<?php

namespace Tests\Unit\Hr;

use App\Services\Hr\RosterPdfException;
use App\Services\Hr\RosterPdfParser;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The reading half of the roster PDF import, with no PDF involved: text
 * pieces placed where Excel puts them, so the layout rules can be pinned
 * down one at a time. The end-to-end file test is RosterPdfImportTest.
 */
class RosterPdfParserTest extends TestCase
{
    private RosterPdfParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new RosterPdfParser();
    }

    public static function timeRanges(): array
    {
        return [
            'excel style'          => ['7AM-3.30PM', ['07:00', '15:30']],
            'padded minutes'       => ['12.00PM-10.30PM', ['12:00', '22:30']],
            'half past on both'    => ['7.30AM-4.00PM', ['07:30', '16:00']],
            'long day'             => ['10.30AM-10.30PM', ['10:30', '22:30']],
            'colons and spaces'    => ['7:30 am - 4 pm', ['07:30', '16:00']],
            'en dash'              => ['9AM–9PM', ['09:00', '21:00']],
            '24 hour'              => ['0700-1530', ['07:00', '15:30']],
            '24 hour short'        => ['14-22', ['14:00', '22:00']],
            'start missing am/pm'  => ['7-3.30PM', ['07:00', '15:30']],
            'bare nine to five'    => ['9-5', ['09:00', '17:00']],
            'overnight'            => ['10PM-6AM', ['22:00', '06:00']],
        ];
    }

    #[DataProvider('timeRanges')]
    public function test_it_reads_the_ways_people_write_a_shift(string $text, array $expected): void
    {
        $this->assertSame($expected, $this->parser->parseTimeRange($text));
    }

    public function test_things_that_are_not_times_are_not_times(): void
    {
        foreach (['MITEC EVENT', 'OFF DAY', '13PM-2PM', '7AM', '', 'BAR AM'] as $text) {
            $this->assertNull($this->parser->parseTimeRange($text), $text);
        }
    }

    public function test_leave_words_map_to_leave_types(): void
    {
        $expect = [
            'OFF DAY' => 'off', 'off' => 'off', 'AL' => 'al', 'MC' => 'mc',
            'RPH' => 'rph', 'RDO' => 'rdo', 'CLAIM HOUR' => 'ch', 'Claim Hours' => 'ch',
        ];

        foreach ($expect as $text => $leave) {
            $cell = $this->parser->parseCell($text);
            $this->assertSame('leave', $cell['kind'], $text);
            $this->assertSame($leave, $cell['leave'], $text);
        }
    }

    /**
     * Text with no times is kept, not thrown away: "MITEC EVENT" means the
     * person is working somewhere, and the manager needs to see that.
     */
    public function test_other_text_is_a_note_and_empty_is_blank(): void
    {
        $this->assertSame('note', $this->parser->parseCell('MITEC EVENT')['kind']);
        $this->assertSame('MITEC EVENT', $this->parser->parseCell('  MITEC   EVENT ')['raw']);
        $this->assertSame('blank', $this->parser->parseCell('   ')['kind']);
        $this->assertSame('blank', $this->parser->parseCell('-')['kind']);
    }

    /** One sheet laid out as Excel does it: centred cells, two lines per person. */
    private function sheet(array $dates = ['28-Sep', '29-Sep', '30-Sep', '1-Oct', '2-Oct', '3-Oct', '4-Oct']): array
    {
        $x = fn (int $col, string $text) => 200 + $col * 100 - mb_strlen($text) * 2.5;
        $row = function (float $y, string $label, array $cells) use ($x) {
            $items = [['x' => 30, 'y' => $y, 'text' => $label]];
            foreach ($cells as $col => $text) {
                $items[] = ['x' => $x($col, $text), 'y' => $y, 'text' => $text];
            }
            return $items;
        };

        return array_merge(
            [['x' => 400, 'y' => 700, 'text' => 'DUTY ROSTER']],
            $row(670, 'DATE', $dates),
            $row(655, 'WEEK 40', ['MONDAY', 'TUESDAY', 'WEDNESDAY', 'THURSDAY', 'FRIDAY', 'SATURDAY', 'SUNDAY']),
            [['x' => 900, 'y' => 655, 'text' => 'OT']],
            $row(640, 'PUBLIC HOLIDAY', [1 => 'WIP MEETING', 2 => 'STOCK TAKE']),
            $row(628, 'EVENTS', [4 => 'BIG EVENT', 5 => 'FAIR', 6 => 'FAIR SETUP']),
            $row(610, '1 ALPHA', ['7AM-3.30PM', '7AM-3.30PM', 'OFF DAY', '12PM-8.30PM', '12PM-8.30PM', 'MITEC EVENT', 'AL']),
            [['x' => 890, 'y' => 610, 'text' => '4.5']],
            $row(598, 'MANAGER', [0 => 'MOD AM', 1 => 'MOD AM', 4 => 'MOD PM']),
            $row(580, '2 BRAVO', ['OFF DAY', '10.30AM-10.30PM', '10.30AM-10.30PM', '10.30AM-10.30PM', 'OFF', '9AM-9PM', '9AM-9PM']),
            $row(568, 'BARISTA', [1 => 'BAR PM']),
            $row(550, 'OPENING', ['3', '4', '4', '4', '4', '5', '5']),
            $row(535, 'TOTAL WORKING', ['12', '12', '13', '12', '16', '13', '14']),
            $row(520, 'NOTICE:', []),
            $row(508, '1. Roster changes require prior approval', []),
        );
    }

    public function test_it_reads_a_whole_sheet(): void
    {
        $result = $this->parser->parsePages([$this->sheet()], Carbon::parse('2026-09-26'));

        $this->assertSame('2026-09-28', $result['week_start']);
        $this->assertSame('2026-10-04', $result['week_end']);
        $this->assertCount(2, $result['rows'], 'totals and the notice are not staff');

        [$alpha, $bravo] = $result['rows'];
        $this->assertSame('ALPHA', $alpha['name']);
        $this->assertSame('MANAGER', $alpha['position']);

        $mon = $alpha['cells']['2026-09-28'];
        $this->assertSame(['shift', '07:00', '15:30', 'MOD AM'], [$mon['kind'], $mon['start'], $mon['end'], $mon['station']]);
        $this->assertSame('leave', $alpha['cells']['2026-09-30']['kind']);
        $this->assertSame('note', $alpha['cells']['2026-10-03']['kind']);
        $this->assertSame('al', $alpha['cells']['2026-10-04']['leave']);
        $this->assertNull($alpha['cells']['2026-10-01']['station']);

        $this->assertSame('BARISTA', $bravo['position']);
        $this->assertSame('BAR PM', $bravo['cells']['2026-09-29']['station']);
        $this->assertSame('off', $bravo['cells']['2026-10-02']['leave']);
    }

    public function test_day_rows_become_remarks_of_the_right_kind(): void
    {
        $remarks = $this->parser->parsePages([$this->sheet()], Carbon::parse('2026-09-26'))['remarks'];

        $this->assertSame(['type' => 'custom', 'text' => 'WIP MEETING'], $remarks['2026-09-29']);
        $this->assertSame('stocktake', $remarks['2026-09-30']['type']);
        $this->assertSame(['type' => 'event', 'text' => 'BIG EVENT'], $remarks['2026-10-02']);
        $this->assertArrayNotHasKey('2026-09-28', $remarks);
    }

    /**
     * "28-Sep" has no year. In early January, a late-December sheet is last
     * year's; the day names keep the guess honest.
     */
    public function test_the_year_is_the_one_nearest_today_that_fits_the_day_names(): void
    {
        $dates = ['29-Dec', '30-Dec', '31-Dec', '1-Jan', '2-Jan', '3-Jan', '4-Jan'];
        $result = $this->parser->parsePages([$this->sheet($dates)], Carbon::parse('2026-01-05'));

        $this->assertSame('2025-12-29', $result['week_start']);
        $this->assertSame('2026-01-04', $result['week_end']);
    }

    public function test_a_sheet_that_does_not_start_on_monday_is_refused_plainly(): void
    {
        $dates = ['27-Sep', '28-Sep', '29-Sep', '30-Sep', '1-Oct', '2-Oct', '3-Oct'];

        $this->expectException(RosterPdfException::class);
        $this->expectExceptionMessage('Monday to Sunday');

        // No day-name row this time, so the date alone decides.
        $sheet = array_values(array_filter($this->sheet($dates), fn ($i) => $i['y'] !== 655.0 && $i['y'] !== 655));
        $this->parser->parsePages([$sheet], Carbon::parse('2026-09-26'));
    }

    public function test_a_scan_with_no_text_says_so(): void
    {
        $this->expectException(RosterPdfException::class);
        $this->expectExceptionMessage('no readable text');

        $this->parser->parsePages([[]]);
    }

    public function test_a_page_without_day_columns_says_what_it_looked_for(): void
    {
        $this->expectException(RosterPdfException::class);
        $this->expectExceptionMessage('day columns');

        $this->parser->parsePages([[['x' => 10, 'y' => 10, 'text' => 'Quarterly report']]]);
    }
}
