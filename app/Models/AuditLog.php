<?php

namespace App\Models;

use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Immutable audit-trail entry. Rows are append-only: the model blocks updates
 * and deletes at the application layer (tamper-proofing). Company isolation is
 * enforced by CompanyScope so the viewer only ever surfaces the caller's own
 * company. Administrative retention pruning goes through the raw query builder
 * in the audit:prune command, deliberately bypassing this guard.
 */
class AuditLog extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'company_id', 'outlet_id', 'user_id', 'user_name', 'guard',
        'event', 'auditable_type', 'auditable_id',
        'old_values', 'new_values', 'ip_address', 'user_agent', 'created_at',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope());

        // Append-only: reject any attempt to mutate or delete an existing entry.
        static::updating(function () {
            throw new \RuntimeException('Audit logs are immutable and cannot be modified.');
        });
        static::deleting(function () {
            throw new \RuntimeException('Audit logs are immutable and cannot be deleted.');
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    /** Best-available actor name, even after the user row is gone. */
    public function actorName(): string
    {
        return $this->user?->name ?? $this->user_name ?? 'System';
    }

    /**
     * A short, human-readable phrase for this entry (without the actor), e.g.
     * "Created", "Selling price increased", "Status changed to approved".
     * Used by the activity-timeline snippet on edit forms.
     */
    public function summary(): string
    {
        switch ($this->event) {
            case 'created':       return 'Created';
            case 'restored':      return 'Restored';
            case 'deleted':
            case 'force_deleted': return 'Deleted';
            case 'merged':        return 'Merged';
            case 'updated':       return $this->describeUpdate();
            case 'line_added':    return ($this->new_values['item'] ?? 'Item') . ' added';
            case 'line_removed':  return ($this->old_values['item'] ?? 'Item') . ' removed';
            case 'line_updated':  return $this->describeLineUpdate();
            case 'line_received': return $this->describeLineReceived();
            case 'line_variance': return $this->describeLineVariance();
            default:              return ucwords(str_replace('_', ' ', $this->event));
        }
    }

    private function describeLineUpdate(): string
    {
        $item = $this->new_values['item'] ?? $this->old_values['item'] ?? 'Item';
        $from = $this->old_values['quantity'] ?? null;
        $to   = $this->new_values['quantity'] ?? null;

        if (is_numeric($from) && is_numeric($to) && (float) $from !== (float) $to) {
            return (float) $to > (float) $from
                ? "Quantity of {$item} increased"
                : "Quantity of {$item} decreased";
        }

        return "{$item} updated";
    }

    private function describeLineReceived(): string
    {
        $item = $this->new_values['item'] ?? 'Item';
        $qty  = $this->new_values['quantity'] ?? null;
        $unit = $this->new_values['unit'] ?? '';

        return $qty !== null
            ? trim("Received {$qty} {$unit} of {$item}")
            : "{$item} received";
    }

    private function describeLineVariance(): string
    {
        $item = $this->new_values['item'] ?? 'Item';
        $v    = $this->new_values['variance'] ?? null;
        $unit = $this->new_values['unit'] ?? '';

        if ($v === null) {
            return "{$item} counted";
        }

        $sign = (float) $v > 0 ? '+' : '';

        return trim("Variance on {$item}: {$sign}{$v} {$unit}");
    }

    private function describeUpdate(): string
    {
        $old = $this->old_values ?? [];
        $new = $this->new_values ?? [];
        $keys = array_values(array_unique(array_merge(array_keys($old), array_keys($new))));

        // "supplier_id" reads as "Supplier", not "Supplier Id" — the value shown
        // alongside is a name, not a raw id.
        $headline = fn ($k) => \Illuminate\Support\Str::headline(preg_replace('/_id$/', '', $k));

        if (count($keys) !== 1) {
            $labels = array_map($headline, array_slice($keys, 0, 2));
            $suffix = count($keys) > 2 ? ' +' . (count($keys) - 2) . ' more' : '';

            return trim('Updated ' . implode(', ', $labels) . $suffix);
        }

        $key   = $keys[0];
        $label = $headline($key);
        $from  = $old[$key] ?? null;
        $to    = $new[$key] ?? null;

        if ($key === 'status' && $to !== null) {
            return "Status changed to " . ucwords(str_replace('_', ' ', (string) $to));
        }

        if (is_numeric($from) && is_numeric($to)) {
            if ((float) $to > (float) $from) return "{$label} increased";
            if ((float) $to < (float) $from) return "{$label} decreased";
        }

        if (is_bool($to) || in_array($to, [0, 1, '0', '1'], true)) {
            return $to ? "{$label} enabled" : "{$label} disabled";
        }

        return "{$label} updated";
    }
}
