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
        $logoBase64 = $this->companyLogoBase64($company);

        $canViewPay = Employee::canViewPay();

        $pdf = Pdf::loadView('pdf.employees', compact(
            'employees', 'filters', 'brandName', 'logoBase64', 'canViewPay'
        ))->setPaper('a4', 'landscape');

        return $pdf->stream('Employees-' . now()->format('Y-m-d') . '.pdf');
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

        $search = trim((string) $request->get('search', ''));
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

        $outletFilter = (string) $request->get('outlet', '');
        if ($outletFilter !== '' && in_array((int) $outletFilter, $accessible, true)) {
            $query->where('outlet_id', (int) $outletFilter);
            $outlet = Outlet::find((int) $outletFilter);
            if ($outlet) $filters[] = 'Outlet: ' . $outlet->name;
        }

        $sectionFilter = (string) $request->get('section', '');
        if ($sectionFilter !== '') {
            $query->where('section_id', (int) $sectionFilter);
            $section = Section::find((int) $sectionFilter);
            if ($section) $filters[] = 'Section: ' . $section->name;
        }

        $status = (string) $request->get('status', '');
        if ($status === 'active')   { $query->where('is_active', true);  $filters[] = 'Status: Active'; }
        if ($status === 'inactive') { $query->where('is_active', false); $filters[] = 'Status: Inactive'; }

        $employmentStatus = (string) $request->get('employment_status', '');
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
