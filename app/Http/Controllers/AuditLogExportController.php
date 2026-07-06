<?php

namespace App\Http\Controllers;

use App\Services\AuditLogService;
use App\Services\CsvExportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

/**
 * Filtered exports of the audit trail (CSV for analysis, PDF for auditors).
 * Reuses AuditLogService::query so exports honour the exact same company +
 * outlet boundaries and filters as the on-screen viewer.
 */
class AuditLogExportController extends Controller
{
    private function filters(Request $request): array
    {
        return [
            'search'    => $request->query('search', ''),
            'date_from' => $request->query('date_from', ''),
            'date_to'   => $request->query('date_to', ''),
            'user_id'   => $request->query('user_id', ''),
            'outlet_id' => $request->query('outlet_id', ''),
            'type'      => $request->query('type', ''),
            'event'     => $request->query('event', ''),
        ];
    }

    private function summarise(array $old = null, array $new = null, array $fkLabels = []): string
    {
        $old = $old ?? [];
        $new = $new ?? [];
        $keys = array_values(array_unique(array_merge(array_keys($old), array_keys($new))));

        $parts = [];
        foreach ($keys as $k) {
            $b = array_key_exists($k, $old) ? $this->scalar($old[$k], $k, $fkLabels) : '';
            $a = array_key_exists($k, $new) ? $this->scalar($new[$k], $k, $fkLabels) : '';
            $parts[] = trim("{$k}: {$b} → {$a}");
        }

        return implode('; ', $parts);
    }

    private function scalar($v, string $key = '', array $fkLabels = []): string
    {
        if (is_null($v)) return '';
        if (is_bool($v)) return $v ? 'true' : 'false';
        if (is_array($v)) return json_encode($v);
        if (is_numeric($v) && isset($fkLabels[$key][(int) $v])) {
            return $fkLabels[$key][(int) $v] . " (#{$v})";
        }

        return (string) $v;
    }

    public function csv(Request $request)
    {
        $user = $request->user();
        $logs = AuditLogService::query($this->filters($request), $user)
            ->limit(50000)->get();

        $recordLabels = AuditLogService::recordLabels($logs);
        $fkLabels     = AuditLogService::foreignLabels($logs);

        $headers = ['Timestamp', 'User', 'Guard', 'Action', 'Module', 'Record', 'Record #', 'Branch', 'Changes', 'IP Address'];

        $rows = $logs->map(fn ($log) => [
            $log->created_at?->format('Y-m-d H:i:s'),
            $log->actorName(),
            $log->guard ?? 'web',
            ucwords(str_replace('_', ' ', $log->event)),
            AuditLogService::label($log->auditable_type),
            $recordLabels[$log->auditable_type . ':' . $log->auditable_id] ?? '',
            $log->auditable_id,
            $log->outlet?->name ?? '',
            $this->summarise($log->old_values, $log->new_values, $fkLabels),
            $log->ip_address ?? '',
        ]);

        return CsvExportService::download('audit-logs-' . now()->format('Ymd-His') . '.csv', $headers, $rows);
    }

    public function pdf(Request $request)
    {
        $user = $request->user();
        $logs = AuditLogService::query($this->filters($request), $user)
            ->limit(5000)->get();

        $company = $user->company;

        $pdf = Pdf::loadView('pdf.audit-logs', [
            'logs'         => $logs,
            'recordLabels' => AuditLogService::recordLabels($logs),
            'fkLabels'     => AuditLogService::foreignLabels($logs),
            'company'      => $company,
            'generatedBy'  => $user->name,
            'generatedAt'  => now(),
        ])->setPaper('a4', 'landscape');

        return $pdf->download('audit-logs-' . now()->format('Ymd-His') . '.pdf');
    }
}
