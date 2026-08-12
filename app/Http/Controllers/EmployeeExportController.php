<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Outlet;
use App\Models\Section;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * PDF and Excel exports of the Employee list. Both honour the same query
 * string filters as the Livewire list (search / outlet / section / status)
 * and the same outlet-access scoping, so what you see is what you export.
 */
class EmployeeExportController extends Controller
{
    public function pdf(Request $request)
    {
        [$employees, $filters] = $this->fetch($request);

        $company    = Auth::user()->company;
        $brandName  = $company?->brand_name ?: $company?->name;
        $logoBase64 = $company?->logoDataUri();

        $canViewPay = Employee::canViewPay();

        $pdf = Pdf::loadView('pdf.employees', compact(
            'employees', 'filters', 'brandName', 'logoBase64', 'canViewPay'
        ))->setPaper('a4', 'landscape');

        return $pdf->stream('Employees-' . now()->format('Y-m-d') . '.pdf');
    }

    /**
     * One employee, as a form.
     *
     * Laid out like a job application rather than like the list export: this is
     * read a field at a time by somebody checking a record, filing it, or
     * asking the person to sign it — not scanned for a total.
     *
     * The same field-level gates the edit screen applies apply here. Pay needs
     * hr.compensation and employment standing needs hr.employment, because a
     * PDF that prints what the screen hides would undo both gates with a button.
     */
    public function detailsPdf(Employee $employee)
    {
        abort_unless(
            in_array((int) $employee->outlet_id, Auth::user()->accessibleOutletIds(), true),
            403,
            'You do not have access to this employee.'
        );

        $employee->load(['outlet', 'section', 'superior']);

        $company = Auth::user()->company;

        $pdf = Pdf::loadView('pdf.employee-details', [
            'employee'         => $employee,
            'brandName'        => $company?->brand_name ?: $company?->name,
            'logoBase64'       => $company?->logoDataUri(),
            'photoBase64'      => $this->photoDataUri($employee),
            'canViewPay'       => Employee::canViewPay(),
            'canViewEmployment' => Auth::user()->canDo('hr.employment'),
            'generatedBy'      => Auth::user()->name,
        ])->setPaper('a4', 'portrait');

        return $pdf->stream(
            'Employee-' . \Illuminate\Support\Str::slug($employee->name) . '.pdf'
        );
    }

    /**
     * The photograph, inlined.
     *
     * dompdf fetches remote images over HTTP by default, which on a company
     * subdomain means the PDF renderer authenticating to our own app — it
     * cannot, so the frame would come out empty. Reading the file and inlining
     * it keeps the request inside this process.
     */
    private function photoDataUri(Employee $employee): ?string
    {
        if (blank($employee->photo_path)) {
            return null;
        }

        /*
         * THE PRIVATE DISK FIRST, because that is where staff photographs
         * actually live — EmployeeForm stores them under employee-photos/ on
         * `local`, deliberately, since a photograph of a member of staff is
         * not something to serve from a public URL.
         *
         * This read `public` only, so every photograph failed to appear and
         * failed SILENTLY: Storage::exists() on the wrong disk is false, the
         * method returned null, and the template rendered its empty frame as
         * though the employee simply had no photo. Thirteen records had one.
         *
         * Both disks are tried rather than one being named, for the same
         * reason PurgesStoredFiles searches: a hard-coded disk is a bug that
         * shows up as nothing happening.
         */
        foreach (['local', 'public'] as $disk) {
            if (! Storage::disk($disk)->exists($employee->photo_path)) {
                continue;
            }

            $contents = Storage::disk($disk)->get($employee->photo_path);
            $mime     = Storage::disk($disk)->mimeType($employee->photo_path) ?: 'image/jpeg';

            return 'data:' . $mime . ';base64,' . base64_encode($contents);
        }

        return null;
    }

    public function excel(Request $request)
    {
        [$employees, $filters] = $this->fetch($request);

        $company   = Auth::user()->company;
        $brandName = $company?->brand_name ?: $company?->name;

        // Salary and service points only reach the file for permitted users —
        // the sheet's column layout shifts accordingly.
        $canViewPay = Employee::canViewPay();

        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()->setCreator(Auth::user()->name)->setTitle('Employees');
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Employees');

        $headers = [
            'No.', 'Name', 'Staff ID', 'Designation', 'Section', 'Outlet', 'E-mail', 'Phone',
            'Join Date', 'Employment Status', 'Food Handler', 'Cert No', 'Typhoid Card', 'Halal Training',
        ];
        if ($canViewPay) {
            $headers[] = 'Service Points';
            $headers[] = 'Basic Salary';
            $headers[] = 'Pay Type';
        }
        $headers[] = 'Status';

        $lastCol = Coordinate::stringFromColumnIndex(count($headers));

        // Title block
        $sheet->setCellValueExplicit('A1', $brandName . ' — Employee List', DataType::TYPE_STRING);
        $sheet->mergeCells('A1:' . $lastCol . '1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFEEF2FF');
        $sheet->getRowDimension(1)->setRowHeight(24);

        $subtitle = 'Generated ' . now()->format('d M Y, h:i A') . ' · ' . $employees->count() . ' employee(s)';
        if (! empty($filters)) {
            $subtitle .= ' · Filters: ' . implode(' · ', $filters);
        }
        $sheet->setCellValueExplicit('A2', $subtitle, DataType::TYPE_STRING);
        $sheet->mergeCells('A2:' . $lastCol . '2');
        $sheet->getStyle('A2')->getFont()->setSize(9)->getColor()->setARGB('FF6B7280');

        // Header row
        $headerRow = 4;
        foreach ($headers as $i => $h) {
            $sheet->setCellValueExplicit([$i + 1, $headerRow], $h, DataType::TYPE_STRING);
        }
        $headerRange = 'A' . $headerRow . ':' . $lastCol . $headerRow;
        $sheet->getStyle($headerRange)->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle($headerRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF1F2937');
        $sheet->getStyle($headerRange)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getRowDimension($headerRow)->setRowHeight(20);

        // Data rows
        $row = $headerRow;
        foreach ($employees as $i => $emp) {
            $row++;

            $employment = $emp->employmentStatusLabel();
            if ($employment && $emp->employmentStatusDetail()) {
                $employment .= ' (' . $emp->employmentStatusDetail() . ')';
            }

            $typhoid = $emp->typhoid_card ? 'Yes' : 'No';
            if ($emp->typhoid_card && $emp->typhoid_expired_on) {
                $typhoid .= $emp->typhoid_expired_on->isBefore(today())
                    ? ' (expired ' . $emp->typhoid_expired_on->format('d M Y') . ')'
                    : ' (until ' . $emp->typhoid_expired_on->format('d M Y') . ')';
            }

            $halal = $emp->halal_training ? 'Yes' : 'No';
            if ($emp->halal_training && $emp->halal_training_date) {
                $halal .= ' (attended ' . $emp->halal_training_date->format('d M Y') . ')';
            }

            $values = [
                $i + 1,
                $emp->name,
                $emp->staff_id,
                $emp->designation,
                $emp->section?->name,
                $emp->outlet?->name,
                $emp->email,
                $emp->phone,
                $emp->join_date?->format('Y-m-d'),
                $employment,
                $emp->food_handler_certified ? 'Certified' : 'No',
                $emp->food_handler_cert_no,
                $typhoid,
                $halal,
            ];
            if ($canViewPay) {
                $values[] = $emp->service_points_entitlement !== null ? (float) $emp->service_points_entitlement : null;
                $values[] = $emp->basic_salary !== null ? (float) $emp->basic_salary : null;
                $values[] = Employee::PAY_TYPES[$emp->pay_type] ?? null;
            }
            $values[] = $emp->is_active ? 'Active' : 'Inactive';

            foreach ($values as $col => $value) {
                if ($col === 0 || is_float($value)) {
                    // Row number, service points and salary are real numbers.
                    $sheet->setCellValue([$col + 1, $row], $value);
                } else {
                    // Explicit strings so names/serials starting with "=" can't
                    // be interpreted as formulas.
                    $sheet->setCellValueExplicit([$col + 1, $row], (string) ($value ?? ''), DataType::TYPE_STRING);
                }
            }

            if ($row % 2 === 0) {
                $sheet->getStyle('A' . $row . ':' . $lastCol . $row)->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFF9FAFB');
            }
        }

        if ($row > $headerRow) {
            $sheet->getStyle('A' . $headerRow . ':' . $lastCol . $row)->getBorders()->getAllBorders()
                ->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FFE5E7EB');
            $sheet->setAutoFilter($headerRange);
            // Service Points and Basic Salary columns: 2-decimal number format.
            if ($canViewPay) {
                foreach (['Service Points', 'Basic Salary'] as $numericHeader) {
                    $c = Coordinate::stringFromColumnIndex(array_search($numericHeader, $headers, true) + 1);
                    $sheet->getStyle($c . ($headerRow + 1) . ':' . $c . $row)
                        ->getNumberFormat()->setFormatCode('#,##0.00');
                }
            }
        }

        $sheet->freezePane('A' . ($headerRow + 1));
        foreach (range('A', $lastCol) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $filename = 'Employees-' . now()->format('Y-m-d') . '.xlsx';
        $tmp = tempnam(sys_get_temp_dir(), 'empxlsx');
        (new Xlsx($spreadsheet))->save($tmp);

        return response()->download($tmp, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    /**
     * Employees matching the request filters, restricted to the user's
     * accessible outlets — mirrors App\Livewire\Hr\Employees::render().
     * Returns [employees, active-filter labels].
     */
    private function fetch(Request $request): array
    {
        $user = Auth::user();

        $accessible = $user->accessibleOutletIds();

        $query = Employee::with(['outlet', 'section'])
            ->whereIn('outlet_id', $accessible ?: [0])
            ->orderBy('name');

        // Don't even read pay columns for users without hr.compensation.
        if (! Employee::canViewPay($user)) {
            $query->select(array_values(array_diff(
                Schema::getColumnListing('employees'),
                Employee::SENSITIVE_PAY_ATTRIBUTES
            )));
        }

        $filters = [];

        $search = trim((string) $request->input('search', ''));
        if ($search !== '') {
            $s = '%' . $search . '%';
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', $s)
                  ->orWhere('staff_id', 'like', $s)
                  ->orWhere('email', 'like', $s)
                  ->orWhere('designation', 'like', $s);
            });
            $filters[] = 'Search: "' . $search . '"';
        }

        $outletFilter = (string) $request->input('outlet', '');
        if ($outletFilter !== '' && in_array((int) $outletFilter, $accessible, true)) {
            $query->where('outlet_id', (int) $outletFilter);
            $outlet = Outlet::find((int) $outletFilter);
            if ($outlet) $filters[] = 'Outlet: ' . $outlet->name;
        }

        $sectionFilter = (string) $request->input('section', '');
        if ($sectionFilter !== '') {
            $query->where('section_id', (int) $sectionFilter);
            $section = Section::find((int) $sectionFilter);
            if ($section) $filters[] = 'Section: ' . $section->name;
        }

        $status = (string) $request->input('status', '');
        if ($status === 'active')   { $query->where('is_active', true);  $filters[] = 'Status: Active'; }
        if ($status === 'inactive') { $query->where('is_active', false); $filters[] = 'Status: Inactive'; }

        $employmentStatus = (string) $request->input('employment_status', '');
        if ($employmentStatus === 'none') {
            $query->whereNull('employment_status');
            $filters[] = 'Employment: No Status';
        } elseif ($employmentStatus === 'exclude_outsourcing') {
            $query->where(function ($q) {
                $q->whereNull('employment_status')->orWhere('employment_status', '!=', 'outsourcing');
            });
            $filters[] = 'Employment: All Exclude Outsourcing';
        } elseif ($employmentStatus !== '' && isset(Employee::EMPLOYMENT_STATUSES[$employmentStatus])) {
            $query->where('employment_status', $employmentStatus);
            $filters[] = 'Employment: ' . Employee::EMPLOYMENT_STATUSES[$employmentStatus];
        }

        return [$query->get(), $filters];
    }

}
