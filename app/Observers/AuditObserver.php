<?php

namespace App\Observers;

use App\Services\AuditLogService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Generic auditing observer registered against every model in config('audit.models').
 *
 * Captures created / updated / deleted / restored / force-deleted events with
 * before→after snapshots, filtered to genuine, non-excluded changes so the
 * trail reflects user intent rather than machine churn.
 *
 * Note: mass operations that bypass Eloquent events (e.g.
 * Model::where(...)->delete() / ->update()) are NOT captured here — the codebase
 * overwhelmingly mutates per-model, and those few bulk paths should call
 * AuditLogService::log() explicitly.
 */
class AuditObserver
{
    public function created(Model $model): void
    {
        AuditLogService::record($model, 'created', null, $this->snapshot($model, $model->getAttributes()));
    }

    public function updated(Model $model): void
    {
        // deleted_at is globally excluded, so a soft delete or restore (whose
        // only change is deleted_at) filters down to nothing and is skipped here
        // — those are recorded by deleted()/restored() instead.
        $changed = $this->filter($model, $model->getChanges());
        if (empty($changed)) {
            return; // nothing meaningful changed (e.g. only timestamps/excluded)
        }

        $keys = array_keys($changed);
        $old  = array_intersect_key($model->getOriginal(), array_flip($keys));

        AuditLogService::record($model, 'updated', $this->castValues($old), $this->castValues($changed));
    }

    public function deleted(Model $model): void
    {
        // A force-delete on a soft-deletes model fires BOTH deleted and
        // forceDeleted — let forceDeleted() own that case to avoid a double row.
        if ($this->usesSoftDeletes($model)
            && method_exists($model, 'isForceDeleting')
            && $model->isForceDeleting()) {
            return;
        }

        AuditLogService::record($model, 'deleted', $this->snapshot($model, $model->getOriginal()), null);
    }

    public function forceDeleted(Model $model): void
    {
        AuditLogService::record($model, 'force_deleted', $this->snapshot($model, $model->getOriginal()), null);
    }

    public function restored(Model $model): void
    {
        AuditLogService::record($model, 'restored', null, $this->snapshot($model, $model->getAttributes()));
    }

    // ── helpers ──────────────────────────────────────────────────────────────

    /** Filter an attribute map to auditable keys and normalise its values. */
    private function snapshot(Model $model, array $attributes): array
    {
        return $this->castValues($this->filter($model, $attributes));
    }

    /** Drop excluded attributes from an attribute map. */
    private function filter(Model $model, array $attributes): array
    {
        $excluded = AuditLogService::excludedFor(get_class($model));

        return array_diff_key($attributes, array_flip($excluded));
    }

    /** Coerce each value to a JSON-friendly scalar without touching the model. */
    private function castValues(array $attributes): array
    {
        $out = [];
        foreach ($attributes as $key => $value) {
            if ($value instanceof \DateTimeInterface) {
                $value = $value->format('Y-m-d H:i:s');
            } elseif ($value instanceof \BackedEnum) {
                $value = $value->value;
            } elseif (is_object($value) && method_exists($value, '__toString')) {
                $value = (string) $value;
            }
            $out[$key] = $value;
        }

        return $out;
    }

    private function usesSoftDeletes(Model $model): bool
    {
        return in_array(SoftDeletes::class, class_uses_recursive($model), true);
    }
}
