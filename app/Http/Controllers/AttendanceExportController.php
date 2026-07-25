<?php

namespace App\Http\Controllers;

use App\Livewire\Hr\AttendanceRecords;
use App\Models\AttendanceCode;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\Outlet;
use App\Models\ServiceChargePeriod;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * PDF export of the Attendance Record grid. Honours the same query string
 * filters and outlet-access scoping as the Livewire grid, so what you see
 * is what you export.
 */
class AttendanceExportController extends Controller
{
    public function pdf(Request $request)
    {
        $user = Auth::user();

        $accessible = $user->canViewAllOutlets()
            ? Outlet::where('company_id', $user->company_id)->pluck('id')->map(fn ($id) => (int) $id)->all()
            : $user->outlets()->pluck('outlets.id')->map(fn ($id) => (int) $id)->all();

        // Period — clamped to the grid's maximum like the Livewire component.
        try {
            $from = Carbon::parse((string) $request->get('from'))->startOfDay();
        } catch (\Throwable $e) {
            $from = now()->startOfMonth();
        }
        try {
            $to = Carbon::parse((string) $request->get('to'))->startOfDay();
        } catch (\Throwable $e) {
            $to = now()->endOfMonth()->startOfDay();
        }
        if ($to->lt($from)) $to = $from->copy();
        if ($from->diffInDays($to) >= AttendanceRecords::MAX_DAYS) {
            $to = $from->copy()->addDays(AttendanceRecords::MAX_DAYS - 1);
        }

        $query = Employee::with(['outlet', 'section'])
            ->whereIn('outlet_id', $accessible ?: [0])
            ->where('is_active', true)
            ->orderByRaw('sort_order IS NULL')
            ->orderBy('sort_order')
            ->orderBy('name');

        $search = trim((string) $request->get('search', ''));
        if ($search !== '') {
            $s = '%' . $search . '%';
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', $s)
                  ->orWhere('staff_id', 'like', $s)
                  ->orWhere('designation', 'like', $s);
            });
        }
        $outletFilter = (string) $request->get('outlet', '');
        if ($outletFilter !== '' && in_array((int) $outletFilter, $accessible, true)) {
            $query->where('outlet_id', (int) $outletFilter);
        }
        $sectionFilter = (string) $request->get('section', '');
        if ($sectionFilter !== '') {
            $query->where('section_id', (int) $sectionFilter);
        }
        $employmentLabel  = null;
        $employmentStatus = (string) $request->get('employment_status', '');
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

        $cellMap = AttendanceRecord::whereIn('employee_id', $employees->pluck('id'))
            ->whereBetween('work_date', [$from, $to])
            ->get()
            ->mapWithKeys(fn ($r) => [$r->employee_id . ':' . $r->work_date->format('Y-m-d') => $r->attendance_code_id]);

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
        $logoBase64 = $this->companyLogoBase64($company);

        $outletName = $outletFilter !== '' ? Outlet::find((int) $outletFilter)?->name : null;

        // Optional Service Charge section: included when the grid's panel is
        // open (service_charge=1) AND a pool has been saved for this exact
        // period + outlet selection (same key as the Livewire panel).
        $serviceCharge = null;
        if ($request->boolean('service_charge')) {
            $scOutletId = ($outletFilter !== '' && in_array((int) $outletFilter, $accessible, true))
                ? (int) $outletFilter : null;
            $scRow = ServiceChargePeriod::where('outlet_id', $scOutletId)
                ->whereDate('period_from', $from)
                ->whereDate('period_to', $to)
                ->first();
            if ($scRow) {
                $serviceCharge = ServiceChargePeriod::distribute($scRow, $employees, $codes, $cellMap);
            }
        }

        $pdf = Pdf::loadView('pdf.attendance', compact(
            'employees', 'dates', 'from', 'to', 'codesById', 'cellMap',
            'legendCodes', 'brandName', 'logoBase64', 'outletName', 'employmentLabel',
            'serviceCharge'
        ))->setPaper('a4', 'landscape');

        return $pdf->stream('Attendance-' . $from->format('Y-m-d') . '-to-' . $to->format('Y-m-d') . '.pdf');
    }

    private function companyLogoBase64($company): ?string
    {
        if (! $company?->logo) return null;
        try {
            $path = Storage::disk('public')->path($company->logo);
            if (file_exists($path)) {
                $mime = mime_content_type($path);
                return 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($path));
            }
        } catch (\Throwable $e) {
        }
        return null;
    }
}
