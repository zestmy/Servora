<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Outlet;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Central writer + reader for the audit trail.
 *
 * Writes are fed by {@see \App\Observers\AuditObserver} (generic CRUD) and by
 * explicit {@see self::log()} calls for semantic domain events (approve,
 * reject, receive, …). Reads go through {@see self::query()}, which applies the
 * viewer's company + outlet boundaries.
 */
class AuditLogService
{
    /**
     * Record a model lifecycle event with before/after snapshots.
     * Called by the observer; $old/$new are already filtered to real changes.
     */
    public static function record(Model $model, string $event, ?array $old, ?array $new): void
    {
        // Auditing must never break the primary operation. Any failure here is
        // logged and swallowed so the user's action still completes.
        try {
            $actor = self::actor();

            AuditLog::create([
                'company_id'     => $model->getAttribute('company_id') ?? $actor['company_id'],
                'outlet_id'      => self::outletFor($model),
                'user_id'        => $actor['id'],
                'user_name'      => $actor['name'],
                'guard'          => $actor['guard'],
                'event'          => $event,
                'auditable_type' => $model->getMorphClass(),
                'auditable_id'   => $model->getKey(),
                'old_values'     => $old ?: null,
                'new_values'     => $new ?: null,
                'ip_address'     => self::ip(),
                'user_agent'     => Str::limit((string) (request()?->userAgent() ?? ''), 480, ''),
                'created_at'     => now(),
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Audit log write failed', [
                'event' => $event,
                'type'  => $model->getMorphClass(),
                'id'    => $model->getKey(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Log a semantic domain event (e.g. 'approved', 'rejected', 'received')
     * from inside a service or Livewire action. Pass whatever context is useful
     * as $new (and optionally the prior state as $old).
     */
    public static function log(Model $model, string $event, ?array $new = null, ?array $old = null): void
    {
        self::record($model, $event, $old, $new);
    }

    /**
     * Record a deletion with a before-snapshot. Use at bulk-delete call sites
     * that mutate via the query builder (Model::whereIn(...)->delete()) and so
     * bypass the model-event observer — fetch the rows and call this per row
     * before deleting, so "who deleted what" is never lost.
     */
    public static function logDeletion(Model $model): void
    {
        $excluded = self::excludedFor(get_class($model));
        $old = array_diff_key($model->getAttributes(), array_flip($excluded));

        self::record($model, 'deleted', $old, null);
    }

    /**
     * A filtered, permission-bounded query for the viewer and exports.
     *
     * Company isolation comes from AuditLog's CompanyScope. Outlet scoping is
     * applied here: users who cannot view all outlets see only logs for their
     * outlets plus company-wide (null-outlet) entries.
     *
     * $filters keys: date_from, date_to, user_id, outlet_id, type, event, search.
     */
    public static function query(array $filters, User $user): Builder
    {
        $q = AuditLog::query()->with('user');

        if (! $user->canViewAllOutlets()) {
            $ids = self::accessibleOutletIds($user);
            $q->where(function ($w) use ($ids) {
                $w->whereNull('outlet_id');
                if (! empty($ids)) {
                    $w->orWhereIn('outlet_id', $ids);
                }
            });
        }

        if (! empty($filters['date_from'])) {
            $q->whereDate('created_at', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $q->whereDate('created_at', '<=', $filters['date_to']);
        }
        if (! empty($filters['user_id'])) {
            $q->where('user_id', (int) $filters['user_id']);
        }
        if (! empty($filters['outlet_id'])) {
            // Only honour an outlet the user may actually see.
            $allowed = $user->canViewAllOutlets()
                ? true
                : in_array((int) $filters['outlet_id'], self::accessibleOutletIds($user), true);
            if ($allowed) {
                $q->where('outlet_id', (int) $filters['outlet_id']);
            }
        }
        if (! empty($filters['type'])) {
            $q->where('auditable_type', $filters['type']);
        }
        if (! empty($filters['event'])) {
            $q->where('event', $filters['event']);
        }
        if (! empty($filters['search'])) {
            $term = trim($filters['search']);
            $q->where(function ($w) use ($term) {
                $w->where('user_name', 'like', "%{$term}%")
                  ->orWhere('auditable_id', $term)
                  ->orWhere('event', 'like', "%{$term}%");
            });
        }

        return $q->orderByDesc('created_at')->orderByDesc('id');
    }

    /** Friendly label for a morph class (falls back to a humanised basename). */
    public static function label(string $type): string
    {
        return self::moduleLabels()[$type]
            ?? Str::headline(class_basename($type));
    }

    /** Map of morph class → human label, for the module filter and rows. */
    public static function moduleLabels(): array
    {
        $map = [];
        foreach ((array) config('audit.models', []) as $class) {
            if (class_exists($class)) {
                $map[$class] = Str::headline(class_basename($class));
            }
        }

        return $map;
    }

    /** Attributes that must never be written for this model. */
    public static function excludedFor(string $class): array
    {
        return array_merge(
            (array) config('audit.global_exclude', []),
            (array) (config('audit.model_exclude', [])[$class] ?? [])
        );
    }

    // ── internals ────────────────────────────────────────────────────────────

    /** Resolve the acting user across the web + lms guards, and console. */
    private static function actor(): array
    {
        if ($u = Auth::guard('web')->user()) {
            return ['id' => $u->id, 'name' => $u->name, 'guard' => 'web', 'company_id' => $u->company_id];
        }

        // LMS users live in a separate table; user_id has an FK to `users`, so
        // we keep the name for accountability but leave user_id null.
        if ($u = Auth::guard('lms')->user()) {
            return ['id' => null, 'name' => $u->name ?? 'LMS user', 'guard' => 'lms', 'company_id' => $u->company_id ?? null];
        }

        return ['id' => null, 'name' => 'System', 'guard' => null, 'company_id' => null];
    }

    private static function outletFor(Model $model): ?int
    {
        $outletId = $model->getAttribute('outlet_id');

        return $outletId ? (int) $outletId : null;
    }

    private static function ip(): ?string
    {
        try {
            return request()?->ip();
        } catch (\Throwable) {
            return null;
        }
    }

    private static function accessibleOutletIds(User $user): array
    {
        return $user->canViewAllOutlets()
            ? Outlet::where('company_id', $user->company_id)->pluck('id')->all()
            : $user->outlets()->pluck('outlets.id')->all();
    }
}
