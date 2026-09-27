<?php

namespace App\Services\Hr;

use App\Models\Employee;
use App\Models\Roster;
use App\Models\RosterDayRemark;
use App\Models\RosterEntry;
use App\Models\RosterNameAlias;
use App\Models\RosterSetting;
use App\Models\RosterStation;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Turns what RosterPdfParser read into roster entries.
 *
 * Split in two on purpose. suggest() and review() only look — they propose
 * who each sheet row is and show what would be written — so the screen can
 * be redrawn as often as the manager changes a dropdown. apply() is the one
 * write, in a transaction, and it trusts nothing it was not handed back.
 *
 * What an import may overwrite is narrow: only the week's draft, and only
 * the rows of the people on the sheet. Somebody added to the draft by hand
 * who is not on the sheet keeps their week.
 */
class RosterPdfImport
{
    /**
     * Who each sheet row most likely is.
     *
     * A remembered alias wins outright. Otherwise the sheet name is compared
     * with every active employee at the outlet; a match is only proposed when
     * exactly one person fits best, because a confident wrong guess is worse
     * than an empty dropdown — the manager would have to notice it.
     *
     * @return array<int, array{employee_id: ?int, how: string, candidates: array<int, int>}>
     */
    public function suggest(array $rows, int $outletId, ?int $sectionId): array
    {
        $employees = $this->employees($outletId);
        $aliases = RosterNameAlias::where('outlet_id', $outletId)
            ->whereIn('employee_id', $employees->pluck('id'))
            ->pluck('employee_id', 'alias');

        $taken = [];
        $out = [];

        foreach ($rows as $i => $row) {
            $key = RosterNameAlias::key($row['name']);

            if (isset($aliases[$key]) && ! isset($taken[$aliases[$key]])) {
                $taken[$aliases[$key]] = true;
                $out[$i] = ['employee_id' => (int) $aliases[$key], 'how' => 'remembered', 'candidates' => []];
                continue;
            }

            $scored = $employees
                ->map(fn ($e) => ['id' => $e->id, 'section_id' => $e->section_id, 'score' => $this->score($row['name'], $e->name)])
                ->filter(fn ($s) => $s['score'] > 0 && ! isset($taken[$s['id']]))
                ->sortByDesc('score')
                ->values();

            $best = $scored->first()['score'] ?? 0;
            $top = $scored->where('score', $best)->values();

            // Two people fit equally well: the one in this roster's section
            // is the likelier reading of an FOH sheet.
            if ($top->count() > 1 && $sectionId) {
                $inSection = $top->where('section_id', $sectionId)->values();
                if ($inSection->count() === 1) {
                    $top = $inSection;
                }
            }

            if ($top->count() === 1) {
                $taken[$top[0]['id']] = true;
                $out[$i] = ['employee_id' => $top[0]['id'], 'how' => 'name', 'candidates' => []];
            } else {
                $out[$i] = [
                    'employee_id' => null,
                    'how'         => $top->isEmpty() ? 'none' : 'ambiguous',
                    'candidates'  => $top->pluck('id')->take(5)->all(),
                ];
            }
        }

        return $out;
    }

    /**
     * How well a sheet name ("AS-SYAFIQ") fits an employee's full name.
     * 0 means not at all. Higher tiers are stricter.
     */
    public function score(string $sheetName, string $fullName): int
    {
        $sheet = RosterNameAlias::key($sheetName);
        $full = RosterNameAlias::key($fullName);
        if ($sheet === '' || $full === '') {
            return 0;
        }
        if ($sheet === $full) {
            return 100;
        }

        $tokens = array_values(array_filter(array_map(
            fn ($t) => RosterNameAlias::key($t),
            preg_split('/[\s\-\'\.\/,@]+/u', $fullName)
        )));

        // One word of the name, or two neighbouring words written together
        // ("AS-SYAFIQ" for "As Syafiq", "NURAINI" for "Nur Aini").
        foreach ($tokens as $n => $token) {
            if ($token === $sheet) {
                return 80;
            }
            if (isset($tokens[$n + 1]) && $token . $tokens[$n + 1] === $sheet) {
                return 80;
            }
        }

        // Every word on the sheet appears in the name ("SITI AMINAH").
        $sheetWords = array_values(array_filter(array_map(
            fn ($t) => RosterNameAlias::key($t),
            preg_split('/[\s\-\'\.\/,@]+/u', $sheetName)
        )));
        if (count($sheetWords) > 1 && ! array_diff($sheetWords, $tokens)) {
            return 70;
        }

        // A nickname cut from the front of a word ("FARHAN" → "FARHANA" is
        // not this, so only when the sheet name is a decent length).
        if (strlen($sheet) >= 4) {
            foreach ($tokens as $token) {
                if (str_starts_with($token, $sheet)) {
                    return 40;
                }
            }
        }

        return 0;
    }

    /**
     * Everything the review screen needs to say what will happen.
     *
     * @param array<int, int|string|null> $assign row index => employee id ('' / null = skip)
     */
    public function review(array $parsed, int $outletId, ?int $sectionId, array $assign): array
    {
        $roster = $sectionId ? $this->existingRoster($outletId, $sectionId, $parsed['week_start']) : null;
        $shifts = $this->shifts($outletId, $sectionId);
        $stations = $this->stations($outletId);

        $shiftCount = 0;
        $leaveCount = 0;
        $noteCells = 0;
        $unknownStations = [];

        foreach ($parsed['rows'] as $i => $row) {
            if (empty($assign[$i])) {
                continue;
            }
            foreach ($row['cells'] as $cell) {
                match ($cell['kind']) {
                    'shift' => $shiftCount++,
                    'leave' => $leaveCount++,
                    'note'  => $noteCells++,
                    default => null,
                };
                if ($cell['kind'] !== 'leave' && $cell['station']) {
                    $key = $this->stationKey($cell['station']);
                    if (! isset($stations[$key])) {
                        $unknownStations[$key] ??= $cell['station'];
                    }
                }
            }
        }

        $chosen = array_filter(array_map(fn ($v) => $v ? (int) $v : null, $assign));
        $duplicates = array_keys(array_filter(array_count_values($chosen), fn ($n) => $n > 1));

        $notOnSheet = collect();
        if ($sectionId) {
            $notOnSheet = $this->employees($outletId)
                ->where('section_id', $sectionId)
                ->whereNotIn('id', $chosen)
                ->pluck('name');
        }

        $blocked = null;
        if (! $sectionId) {
            $blocked = 'Choose which section this roster is for.';
        } elseif ($roster && ! $roster->isDraft()) {
            $blocked = "This week's roster is already {$roster->status_label}. Revert it to Draft before importing over it.";
        } elseif ($duplicates) {
            $blocked = 'The same employee is picked for more than one row.';
        } elseif (! $chosen) {
            $blocked = 'Pick at least one employee to import.';
        }

        return [
            'roster'           => $roster,
            'replacing'        => $roster ? RosterEntry::where('roster_id', $roster->id)->whereIn('employee_id', $chosen)->count() : 0,
            'staff'            => count($chosen),
            'unassigned'       => count($parsed['rows']) - count($chosen),
            'shifts'           => $shiftCount,
            'leave'            => $leaveCount,
            'notes'            => $noteCells,
            'unknown_stations' => $unknownStations,
            'duplicates'       => $duplicates,
            'not_on_sheet'     => $notOnSheet,
            'shift_names'      => $this->shiftNamesByTime($shifts),
            'blocked'          => $blocked,
        ];
    }

    /**
     * Write the sheet into the roster.
     *
     * @param array<int, int|string|null> $assign     row index => employee id
     * @param array<int, string>          $newStations station labels to create (as they appeared on the sheet)
     * @return array{roster: Roster, created: bool, staff: int, entries: int, stations: int, remarks: int}
     */
    public function apply(array $parsed, int $outletId, int $sectionId, array $assign, array $newStations, User $user): array
    {
        return DB::transaction(function () use ($parsed, $outletId, $sectionId, $assign, $newStations, $user) {
            $roster = $this->existingRoster($outletId, $sectionId, $parsed['week_start']);
            $created = false;

            if ($roster && ! $roster->isDraft()) {
                throw new RosterPdfException("This week's roster is already {$roster->status_label}. Revert it to Draft before importing over it.");
            }

            if (! $roster) {
                $roster = Roster::create([
                    'company_id'      => $user->company_id,
                    'created_by'      => $user->id,
                    'outlet_id'       => $outletId,
                    'section_id'      => $sectionId,
                    'week_start_date' => $parsed['week_start'],
                    'week_end_date'   => $parsed['week_end'],
                    'status'          => Roster::STATUS_DRAFT,
                    'revision'        => 1,
                ]);
                $created = true;
            }

            // Only people who actually work at this outlet can be written —
            // the ids came back from the browser.
            $validIds = $this->employees($outletId)->pluck('id')->flip();

            $stationIds = $this->stations($outletId);
            $wanted = collect($newStations)->map(fn ($s) => $this->stationKey($s))->flip();
            $madeStations = 0;
            $nextOrder = (int) RosterStation::where('outlet_id', $outletId)->max('sort_order') + 1;

            foreach ($parsed['rows'] as $row) {
                foreach ($row['cells'] as $cell) {
                    if (! $cell['station'] || $cell['kind'] === 'leave') {
                        continue;
                    }
                    $key = $this->stationKey($cell['station']);
                    if (! isset($stationIds[$key]) && isset($wanted[$key])) {
                        $stationIds[$key] = RosterStation::create([
                            'outlet_id'  => $outletId,
                            'name'       => mb_substr(trim($cell['station']), 0, 100),
                            'sort_order' => $nextOrder++,
                            'is_active'  => true,
                        ])->id;
                        $madeStations++;
                    }
                }
            }

            $shifts = $this->shifts($outletId, $sectionId);
            $byTime = $shifts->keyBy(fn ($s) => $s->start_time->format('H:i') . '-' . $s->end_time->format('H:i'));
            $settings = RosterSetting::firstOrCreate(
                ['outlet_id' => $outletId],
                ['normal_hours' => 8.00, 'rest_duration' => 60]
            );

            $existingOrder = RosterEntry::where('roster_id', $roster->id)
                ->selectRaw('employee_id, MIN(sort_order) as pos')
                ->groupBy('employee_id')
                ->pluck('pos', 'employee_id');
            $nextPos = $existingOrder->isEmpty() ? 0 : (int) $existingOrder->max() + 1;

            $staff = 0;
            $entries = 0;

            foreach ($parsed['rows'] as $i => $row) {
                $employeeId = (int) ($assign[$i] ?? 0);
                if (! $employeeId || ! isset($validIds[$employeeId])) {
                    continue;
                }

                // Someone already on the draft keeps their place in it;
                // new people are added in the order the sheet lists them.
                $pos = isset($existingOrder[$employeeId]) ? (int) $existingOrder[$employeeId] : $nextPos++;

                RosterEntry::where('roster_id', $roster->id)->where('employee_id', $employeeId)->delete();

                foreach ($row['cells'] as $date => $cell) {
                    if ($cell['kind'] === 'blank') {
                        continue;
                    }

                    $data = [
                        'roster_id'     => $roster->id,
                        'employee_id'   => $employeeId,
                        'day_date'      => $date,
                        'sort_order'    => $pos,
                        'rest_duration' => (int) $settings->rest_duration,
                        'is_off_day'    => false,
                    ];

                    if ($cell['kind'] === 'leave') {
                        $data['is_off_day'] = true;
                        $data['leave_type'] = $cell['leave'];
                    } else {
                        $stationKey = $cell['station'] ? $this->stationKey($cell['station']) : null;
                        $data['station_id'] = $stationKey ? ($stationIds[$stationKey] ?? null) : null;

                        // A station the manager chose not to create is still
                        // information; keep it where a person will read it.
                        $notes = [];
                        if ($cell['kind'] === 'note') {
                            $notes[] = $cell['raw'];
                        }
                        if ($cell['station'] && ! $data['station_id']) {
                            $notes[] = $cell['station'];
                        }
                        $data['notes'] = $notes ? mb_substr(implode(' · ', $notes), 0, 255) : null;

                        if ($cell['kind'] === 'shift') {
                            $data['shift_start'] = $cell['start'] . ':00';
                            $data['shift_end'] = $cell['end'] . ':00';
                            if ($shift = $byTime->get($cell['start'] . '-' . $cell['end'])) {
                                $data['shift_id'] = $shift->id;
                                $data['rest_duration'] = (int) $shift->rest_duration;
                                $data['normal_hours'] = $shift->normal_hours !== null ? (float) $shift->normal_hours : null;
                            }
                        }
                    }

                    RosterEntry::create($data);
                    $entries++;
                }

                RosterNameAlias::updateOrCreate(
                    ['outlet_id' => $outletId, 'alias' => RosterNameAlias::key($row['name'])],
                    ['employee_id' => $employeeId]
                );
                $staff++;
            }

            $remarks = 0;
            foreach ($parsed['remarks'] as $date => $remark) {
                RosterDayRemark::updateOrCreate(
                    ['roster_id' => $roster->id, 'day_date' => $date],
                    ['remark_type' => $remark['type'], 'remark_text' => mb_substr($remark['text'], 0, 255)]
                );
                $remarks++;
            }

            $roster->update(['last_edited_by' => $user->id, 'last_edited_at' => now()]);

            return [
                'roster'   => $roster,
                'created'  => $created,
                'staff'    => $staff,
                'entries'  => $entries,
                'stations' => $madeStations,
                'remarks'  => $remarks,
            ];
        });
    }

    /** Active staff at the outlet, in the order every staff list uses. */
    public function employees(int $outletId): Collection
    {
        return Employee::where('outlet_id', $outletId)
            ->where('is_active', true)
            ->inListOrder()
            ->get(['id', 'name', 'section_id', 'designation']);
    }

    public function existingRoster(int $outletId, int $sectionId, string $weekStart): ?Roster
    {
        return Roster::where('outlet_id', $outletId)
            ->where('section_id', $sectionId)
            ->whereDate('week_start_date', $weekStart)
            ->first();
    }

    private function shifts(int $outletId, ?int $sectionId): Collection
    {
        return Shift::for($outletId, $sectionId)->active()->ordered()->get();
    }

    /** "07:00-15:30" => "AM" for the shift templates that match a time exactly. */
    private function shiftNamesByTime(Collection $shifts): array
    {
        return $shifts
            ->mapWithKeys(fn ($s) => [$s->start_time->format('H:i') . '-' . $s->end_time->format('H:i') => $s->name])
            ->all();
    }

    /** @return array<string, int> station key => id */
    private function stations(int $outletId): array
    {
        return RosterStation::where('outlet_id', $outletId)
            ->get(['id', 'name'])
            ->mapWithKeys(fn ($s) => [$this->stationKey($s->name) => $s->id])
            ->all();
    }

    public function stationKey(string $name): string
    {
        return mb_strtoupper(trim(preg_replace('/\s+/u', ' ', $name)));
    }
}
