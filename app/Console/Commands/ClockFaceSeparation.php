<?php

namespace App\Console\Commands;

use App\Models\EmployeeFaceDescriptor;
use App\Models\Outlet;
use App\Scopes\CompanyScope;
use App\Services\Hr\FaceSeparation;
use Illuminate\Console\Command;

/**
 * Measures whether staff faces can be told apart, before anything is built
 * that depends on the answer.
 *
 * Clocking in today uses VERIFICATION: the PIN says who you are and the face
 * check tests that one claim. Recognising somebody from a camera alone is
 * IDENTIFICATION, which searches the outlet and keeps the closest match — a
 * strictly harder problem that gets harder as headcount rises.
 *
 * How much harder is not something to reason about. It depends on these
 * particular people, photographed on these phones, in the light of these
 * rooms. This runs the measurement on the faces already enrolled and reports
 * whether a usable threshold exists at all.
 *
 * READ-ONLY. Runs against production safely and writes nothing.
 *
 * Use --anonymise when the output is going anywhere public — a deploy log,
 * for instance. Staff names are not something to leave in CI output.
 */
class ClockFaceSeparation extends Command
{
    protected $signature = 'clock:face-separation
                            {--outlet= : Restrict to one outlet id}
                            {--anonymise : Replace names with short ids — use for logs}';

    protected $description = 'Measure how well enrolled faces separate, per outlet';

    public function handle(): int
    {
        $rows = EmployeeFaceDescriptor::withoutGlobalScope(CompanyScope::class)
            ->where('model_version', EmployeeFaceDescriptor::MODEL_VERSION)
            ->with(['employee:id,name,outlet_id'])
            ->get(['id', 'employee_id', 'descriptor']);

        if ($rows->isEmpty()) {
            $this->warn('No enrolled faces at the current model version. Nothing to measure.');

            return self::SUCCESS;
        }

        $byOutlet = $rows
            ->filter(fn ($row) => $row->employee?->outlet_id)
            ->when($this->option('outlet'), fn ($c) => $c
                ->filter(fn ($row) => (int) $row->employee->outlet_id === (int) $this->option('outlet')))
            ->groupBy(fn ($row) => $row->employee->outlet_id);

        $names = Outlet::withoutGlobalScope(CompanyScope::class)
            ->whereIn('id', $byOutlet->keys())
            ->pluck('name', 'id');

        foreach ($byOutlet as $outletId => $outletRows) {
            $this->report(
                $this->option('anonymise') ? "outlet #{$outletId}" : ($names[$outletId] ?? "outlet #{$outletId}"),
                $this->people($outletRows),
            );
        }

        return self::SUCCESS;
    }

    /** @return array<int, array{id: int, label: string, captures: array}> */
    private function people($rows): array
    {
        return $rows
            ->groupBy('employee_id')
            ->map(fn ($captures, $employeeId) => [
                'id'    => (int) $employeeId,
                'label' => $this->option('anonymise')
                    ? "#{$employeeId}"
                    : ($captures->first()->employee?->name ?? "#{$employeeId}"),
                'captures' => $captures
                    ->pluck('descriptor')
                    ->filter(fn ($d) => EmployeeFaceDescriptor::isValidDescriptor($d))
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();
    }

    private function report(string $outlet, array $people): void
    {
        $this->newLine();
        $this->line("── {$outlet} " . str_repeat('─', max(0, 50 - strlen($outlet))));

        $thin = array_filter($people, fn ($p) => count($p['captures']) < 2);

        if ($thin) {
            // Not a footnote. One capture cannot be cross-validated, and it is
            // also the enrolment most likely to fail at the door.
            $this->line(sprintf(
                '  %d of %d have fewer than 2 captures and are excluded: %s',
                count($thin), count($people),
                implode(', ', array_column($thin, 'label'))
            ));
        }

        $result = FaceSeparation::leaveOneOut($people);

        if ($result['probes'] === 0) {
            $this->warn('  Not enough captures to measure.');

            return;
        }

        $rate = $result['correct'] / $result['probes'] * 100;

        $this->line(sprintf('  people enrolled : %d', $result['people']));
        $this->line(sprintf(
            '  top-1 correct   : %d/%d  (%.1f%%)',
            $result['correct'], $result['probes'], $rate
        ));

        foreach (['genuine' => 'same person   ', 'impostor' => 'nearest other ', 'margin' => 'margin        '] as $key => $label) {
            $s = $result[$key];

            if (($s['n'] ?? 0) === 0) {
                continue;
            }

            $this->line(sprintf(
                '  %s  : min %.3f · p05 %.3f · median %.3f · p95 %.3f · max %.3f',
                $label, $s['min'], $s['p05'], $s['median'], $s['p95'], $s['max']
            ));
        }

        foreach ($result['collisions'] as $clash) {
            $this->error(sprintf(
                '  COLLISION: %s and %s at %.3f — closer to each other than to themselves.',
                $clash['a'], $clash['b'], $clash['distance']
            ));
        }

        $advice = FaceSeparation::recommend($result);

        $this->newLine();

        if ($advice['safe']) {
            $this->info(sprintf(
                '  Recommended: threshold %.3f, margin %.3f', $advice['threshold'], $advice['margin']
            ));
        } else {
            $this->error('  NOT SAFE for identification here.');
        }

        $this->line('  ' . wordwrap($advice['reason'], 76, "\n  "));
    }
}
