<?php

namespace App\Http\Controllers;

use App\Livewire\Hr\AttendanceRecords;
use App\Models\AttendanceCode;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\Outlet;
use App\Services\Hr\ServiceChargeDistribution;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * PDF export of the Attendance Record grid. Honours the same query string
 * filters and outlet-access scoping as the Livewire grid, so what you see
 * is what you export.
 */
class AttendanceExportController extends Controller
{
    /**
     * The attendance grid. Just the grid.
     *
     * The service charge distribution used to be appended to it, which put a
     * day-by-day matrix for forty people and a payout table on the same sheet
     * and left both cramped. They answer different questions and get signed by
     * different people, so distribution() is its own document now.
     */
    public function pdf(Request $request)
    {
        $data = $this->gather($request);

        $pdf = Pdf::loadView('pdf.attendance', $data)->setPaper('a4', 'landscape');

        return $pdf->stream('Attendance-' . $data['from']->format('Y-m-d') . '-to-' . $data['to']->format('Y-m-d') . '.pdf');
    }

    /**
     * The service charge distribution, on its own sheet.
     *
     * Landscape like the grid it came out of: the table's column weights were
     * tuned for that width, and re-flowing them into portrait would re-cramp
     * the thing this split exists to uncramp.
     */
    public function distribution(Request $request)
    {
        $data = $this->gather($request, forceServiceCharge: true);

        abort_unless($data['canManageServiceCharge'], 403);
        abort_if($data['serviceCharge'] === null, 404,
            'No service charge has been saved for this period and outlet.');

        $pdf = Pdf::loadView('pdf.service-charge-distribution', $data)->setPaper('a4', 'landscape');

        return $pdf->stream('Service-Charge-Distribution-' . $data['from']->format('Y-m-d') . '-to-' . $data['to']->format('Y-m-d') . '.pdf');
    }

    /**
     * One service charge payout slip per employee.
     *
     * Built from the SAME gather() the grid export uses, so a slip and the
     * table it came from cannot disagree about what someone is owed — which
     * is the only reason to hand one to a member of staff.
     */
    public function payout(Request $request)
    {
        $data = $this->gather($request, forceServiceCharge: true);

        abort_unless($data['canManageServiceCharge'], 403);
        abort_if($data['serviceCharge'] === null, 404,
            'No service charge has been saved for this period and outlet.');

        // Only people with a share get a slip: a page reading RM0.00 because
        // someone has no service points is not a payout, it is confusing.
        $rows = collect($data['serviceCharge']['rows'])
            ->filter(fn ($r) => $r['points'] > 0)
            ->values();

        abort_if($rows->isEmpty(), 404, 'Nobody in this view has service points for this period.');

        $pdf = Pdf::loadView('pdf.service-charge-payout', [
            'rows'          => $rows,
            'serviceCharge' => $data['serviceCharge'],
            'from'          => $data['from'],
            'to'            => $data['to'],
            'brandName'     => $data['brandName'],
            'logoBase64'    => $data['logoBase64'],
            'outletName'    => $data['outletName'],
            'lateRate'      => (float) \App\Models\ClockSetting::forCompany(Auth::user()->company_id)->late_rate_per_minute,
            'exportedBy'    => Auth::user()->name ?? Auth::user()->email,
        ])->setPaper('a4', 'portrait');

        return $pdf->download('Service-Charge-Payout-' . $data['from']->format('Y-m-d') . '.pdf');
    }

    /**
     * Everything both exports need: the scoped employee list, the attendance
     * grid behind it, and the service charge distribution.
     *
     * @param  bool  $forceServiceCharge  the payout slips and the distribution
     *         sheet are ABOUT the pool, so they ask for it; the grid never
     *         does, since it no longer carries the section.
     */
    private function gather(Request $request, bool $forceServiceCharge = false): array
    {
        $user = Auth::user();

        $accessible = $user->accessibleOutletIds();

        // Period — clamped to the grid's maximum like the Livewire component.
        try {
            $from = Carbon::parse((string) $request->input('from'))->startOfDay();
        } catch (\Throwable $e) {
            $from = now()->startOfMonth();
        }
        try {
            $to = Carbon::parse((string) $request->input('to'))->startOfDay();
        } catch (\Throwable $e) {
            $to = now()->endOfMonth()->startOfDay();
        }
        if ($to->lt($from)) $to = $from->copy();
        if ($from->diffInDays($to) >= AttendanceRecords::MAX_DAYS) {
            $to = $from->copy()->addDays(AttendanceRecords::MAX_DAYS - 1);
        }

        // Same rule as the grid: active staff plus anyone who resigned during
        // the exported period, so the PDF matches what was on screen.
        $query = Employee::with(['outlet', 'section'])
            ->whereIn('outlet_id', $accessible ?: [0])
            ->employedDuring($from->toDateString(), $to->toDateString())
            ->orderByRaw('sort_order IS NULL')
            ->orderBy('sort_order')
            ->orderBy('name');

        // pay_type is kept even without hr.compensation — it decides which of
        // the two tables a row goes on, and is a rostering fact rather than a
        // pay figure. See the same carve-out in AttendanceRecords.
        if (! Employee::canViewPay($user)) {
            $keep = ['pay_type'];

            // Service points come back for anyone who may run the service
            // charge — the distribution is arithmetic over them. Same
            // carve-out as the Livewire grid.
            if ($user->can('hr.attendance.service_charge')) {
                $keep[] = 'service_points_entitlement';
            }

            $query->select(array_values(array_diff(
                Schema::getColumnListing('employees'),
                array_diff(Employee::SENSITIVE_PAY_ATTRIBUTES, $keep)
            )));
        }

        $search = trim((string) $request->input('search', ''));
        if ($search !== '') {
            $s = '%' . $search . '%';
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', $s)
                  ->orWhere('staff_id', 'like', $s)
                  ->orWhere('designation', 'like', $s);
            });
        }
        $outletFilter = (string) $request->input('outlet', '');
        if ($outletFilter !== '' && in_array((int) $outletFilter, $accessible, true)) {
            $query->where('outlet_id', (int) $outletFilter);
        }
        $sectionFilter = (string) $request->input('section', '');
        if ($sectionFilter !== '') {
            $query->where('section_id', (int) $sectionFilter);
        }
        $employmentLabel  = null;
        $employmentStatus = (string) $request->input('employment_status', '');
        if ($employmentStatus === 'none') {
            $query->whereNull('employment_status');
            $employmentLabel = 'No Employment Status';
        } elseif ($employmentStatus === 'exclude_outsourcing') {
            $query->where(function ($q) {
                $q->whereNull('employment_status')->orWhere('employment_status', '!=', 'outsourcing');
            });
            $employmentLabel = 'All Exclude Outsourcing';
        } elseif ($employmentStatus !== '' && isset(Employee::EMPLOYMENT_STATUSES[$employmentStatus])) {
            $query->where('employment_status', $employmentStatus);
            $employmentLabel = Employee::EMPLOYMENT_STATUSES[$employmentStatus];
        }

        $employees = $query->get();

        $codes     = AttendanceCode::orderBy('sort_order')->orderBy('code')->get();
        $codesById = $codes->keyBy('id');

        $records = AttendanceRecord::whereIn('employee_id', $employees->pluck('id'))
            ->whereBetween('work_date', [$from, $to])
            ->get();

        // Codes only — the shape ServiceChargePeriod::distribute() expects. An
        // hours cell carries no code and must not appear here as a null.
        $cellMap = $records
            ->filter(fn ($r) => $r->attendance_code_id !== null)
            ->mapWithKeys(fn ($r) => [$r->employee_id . ':' . $r->work_date->format('Y-m-d') => $r->attendance_code_id]);

        $hoursMap = $records
            ->filter(fn ($r) => $r->hours !== null)
            ->mapWithKeys(fn ($r) => [$r->employee_id . ':' . $r->work_date->format('Y-m-d') => (float) $r->hours]);

        $hourTotals = [];
        foreach ($hoursMap as $key => $hours) {
            $empId = (int) strtok($key, ':');
            $hourTotals[$empId] = round(($hourTotals[$empId] ?? 0) + $hours, 2);
        }

        // Two tables on the page, for the same reason the screen has two: one
        // is a register of who was in, the other is the count that gets
        // multiplied by a rate.
        $hourlyEmployees  = $employees->filter(fn ($e) => $e->pay_type === 'hourly')->values();
        $monthlyEmployees = $employees->reject(fn ($e) => $e->pay_type === 'hourly')->values();

        $dates = [];
        for ($d = $from->copy(); $d->lte($to); $d->addDay()) {
            $dates[] = $d->copy();
        }

        // Legend shows active codes plus any inactive ones still present in
        // the exported range, so every cell on the page is explained.
        $usedCodeIds = collect($cellMap)->unique()->all();
        $legendCodes = $codes->filter(fn ($c) => $c->is_active || in_array($c->id, $usedCodeIds, true))->values();

        $company    = $user->company;
        $brandName  = $company?->brand_name ?: $company?->name;
        $logoBase64 = $company?->logoDataUri();

        $outletName = $outletFilter !== '' ? Outlet::find((int) $outletFilter)?->name : null;

        // The distribution, for the callers that are ABOUT it. A pool is
        // keyed on the exact period + outlet selection (same key as the
        // Livewire panel), so this is null when none has been saved.
        // The distribution rides on its own ability rather than on salary
        // visibility, matching the Livewire grid — see the note on
        // hr.attendance.service_charge in config/permissions.php.
        $canViewPay = Employee::canViewPay($user);
        $canManageServiceCharge = (bool) $user->can('hr.attendance.service_charge');

        /*
         * THE SERVICE CHARGE SECTION IS NOT A VIEW OF THE GRID ABOVE IT.
         *
         * REPORTED AS: three people paid from KLCC's pool were missing from
         * the KLCC payout list. They were correct everywhere else — set up
         * right, in the frozen calculation at RM1,033.50 each, present on the
         * payout report — and absent only here.
         *
         * Because this block used $employees, and $employees is the GRID's
         * list: filtered to the selected outlet by home outlet_id, and
         * narrowed further by the section, employment and search boxes. Staff
         * posted to HQ and the Central Kitchen but paid from KLCC's pool are
         * not in it, so a frozen distribution mapped over that list simply had
         * no row for them.
         *
         * The Livewire grid had this exact bug and was fixed by giving the
         * panel its own pool-scoped query; the export was never given the same
         * fix. So rather than a third hand-rolled copy of "who does this pool
         * pay", it now calls the service the payout report calls. The two
         * cannot disagree about what a period paid, which is the whole reason
         * that service exists.
         *
         * It also fixes the divisor, which had the same fault in a quieter
         * form: the old query ANDed on the exporter's accessible outlets, so a
         * KLCC-only manager dropped redirected staff out of the RM/point base
         * while a manager who could see HQ kept them — the same pool printing
         * two different rates depending on who pressed the button. Frozen
         * periods were spared it; unfrozen ones were not.
         */
        $serviceCharge = null;
        if ($canManageServiceCharge && $forceServiceCharge) {
            $scOutletId = ($outletFilter !== '' && in_array((int) $outletFilter, $accessible, true))
                ? (int) $outletFilter : null;

            $serviceCharge = app(ServiceChargeDistribution::class)->forPeriod(
                $user->company_id, $accessible, $from, $to, $scOutletId
            );
        }

        return compact(
            'employees', 'monthlyEmployees', 'hourlyEmployees',
            'dates', 'from', 'to', 'codesById', 'cellMap', 'hoursMap', 'hourTotals',
            'legendCodes', 'brandName', 'logoBase64', 'outletName', 'employmentLabel',
            'serviceCharge', 'canViewPay', 'canManageServiceCharge'
        );
    }

}
