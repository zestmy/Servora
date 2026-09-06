<?php

namespace App\Http\Controllers;

use App\Services\GroupSummaryExcelWriter;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * The same staff meal summary as a workbook, via the shared GroupSummaryExcelWriter.
 *
 * Extends the PDF controller so both are built from one load() — see
 * WastageSummaryExcelController for the same reasoning.
 */
class StaffMealSummaryExcelController extends StaffMealSummaryController
{
    public function __invoke(Request $request)
    {
        $data = $this->load($request);

        $spreadsheet = app(GroupSummaryExcelWriter::class)->write($data);
        $filename    = 'Staff-Meal-Summary-' . $data['scope']['from'] . '-to-' . $data['scope']['to'] . '.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->setPreCalculateFormulas(false);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
