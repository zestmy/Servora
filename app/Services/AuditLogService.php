<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Outlet;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Eloquent\SoftDeletes;
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
     * Diff two keyed sets of line items and log added / removed / quantity-
     * changed events against the parent record, so line-level history shows up
     * in the parent's activity timeline. Each set is keyed by a stable identity
     * (e.g. ingredient_id); each value is ['item' => string, 'quantity' => ?float,
     * 'unit' => ?string]. Call from a save() path with the DB state captured
     * before the delete-and-recreate and the incoming state after.
     */
    public static function logLineChanges(Model $parent, array $before, array $after): void
    {
        foreach ($after as $key => $line) {
            if (! array_key_exists($key, $before)) {
                self::log($parent, 'line_added', self::linePayload($line));
                continue;
            }

            $old = $before[$key];
            $qtyChanged  = self::num($old['quantity'] ?? null) !== self::num($line['quantity'] ?? null);
            $unitChanged = ($old['unit'] ?? null) !== ($line['unit'] ?? null);
            if ($qtyChanged || $unitChanged) {
                self::log($parent, 'line_updated', self::linePayload($line), self::linePayload($old));
            }
        }

        foreach ($before as $key => $line) {
            if (! array_key_exists($key, $after)) {
                self::log($parent, 'line_removed', null, self::linePayload($line));
            }
        }
    }

    /**
     * Higher-level line diff for forms whose rows carry an ingredient_id or a
     * recipe_id (wastage, transfers, staff meals, prep items, …). Pass the DB
     * rows captured before the delete-and-recreate as $beforeRows and the
     * incoming rows as $afterRows; each row is an array with any of
     * ingredient_id / recipe_id / uom_id / quantity. Names and UOM labels are
     * resolved in one query each, then handed to logLineChanges().
     */
    public static function logItemLineChanges(Model $parent, array $beforeRows, array $afterRows): void
    {
        $ingIds = $recIds = $uomIds = [];
        foreach (array_merge($beforeRows, $afterRows) as $r) {
            if (! empty($r['ingredient_id'])) $ingIds[] = (int) $r['ingredient_id'];
            if (! empty($r['recipe_id']))     $recIds[] = (int) $r['recipe_id'];
            if (! empty($r['uom_id']))        $uomIds[] = (int) $r['uom_id'];
        }

        $labels = self::itemLabels($ingIds, $recIds);
        $uoms   = self::uomLabels($uomIds);

        $build = function (array $rows) use ($labels, $uoms) {
            $map = [];
            foreach ($rows as $r) {
                $ing = (int) ($r['ingredient_id'] ?? 0);
                $rec = (int) ($r['recipe_id'] ?? 0);
                $key = $ing ? 'ing:' . $ing : ($rec ? 'rec:' . $rec : null);
                if ($key === null) continue;

                $map[$key] = [
                    'item'     => $labels[$key] ?? $key,
                    'quantity' => isset($r['quantity']) ? (float) $r['quantity'] : null,
                    'unit'     => $uoms[(int) ($r['uom_id'] ?? 0)] ?? null,
                ];
            }

            return $map;
        };

        self::logLineChanges($parent, $build($beforeRows), $build($afterRows));
    }

    /** Resolve ing:{id}/rec:{id} keys → display names in one query each. */
    public static function itemLabels(array $ingredientIds, array $recipeIds = []): array
    {
        $labels = [];

        if ($ids = array_filter(array_unique(array_map('intval', $ingredientIds)))) {
            foreach (\App\Models\Ingredient::whereIn('id', $ids)->pluck('name', 'id') as $id => $name) {
                $labels['ing:' . $id] = $name;
            }
        }
        if ($ids = array_filter(array_unique(array_map('intval', $recipeIds)))) {
            foreach (\App\Models\Recipe::whereIn('id', $ids)->pluck('name', 'id') as $id => $name) {
                $labels['rec:' . $id] = $name;
            }
        }

        return $labels;
    }

    /** Resolve UOM ids → abbreviations. */
    public static function uomLabels(array $uomIds): array
    {
        $ids = array_filter(array_unique(array_map('intval', $uomIds)));

        return $ids ? \App\Models\UnitOfMeasure::whereIn('id', $ids)->pluck('abbreviation', 'id')->all() : [];
    }

    private static function linePayload(array $line): array
    {
        return array_filter([
            'item'     => $line['item'] ?? null,
            'quantity' => isset($line['quantity']) ? self::num($line['quantity']) : null,
            'unit'     => $line['unit'] ?? null,
        ], fn ($v) => $v !== null && $v !== '');
    }

    private static function num($value): ?float
    {
        return is_numeric($value) ? round((float) $value, 4) : null;
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
        $q = AuditLog::query()->with(['user', 'outlet']);

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

    /**
     * The most recent audit entries for one record, in chronological order
     * (oldest first) for the activity-timeline snippet on edit forms. Returns
     * an empty collection when there is no id (i.e. a create form).
     */
    public static function recentFor(string $type, $id, int $limit = 6)
    {
        if (! $id) {
            return collect();
        }

        return AuditLog::with('user')
            ->where('auditable_type', $type)
            ->where('auditable_id', $id)
            ->orderByDesc('created_at')->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->reverse()
            ->values();
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

    /**
     * Attributes checked, in priority order, to derive a human-readable label
     * for an audited record (name for master data, document number for
     * transactional records).
     */
    public const LABEL_CANDIDATES = [
        'name', 'po_number', 'pr_number', 'grn_number', 'do_number', 'sto_number',
        'transfer_number', 'request_number', 'order_number', 'credit_note_number',
        'invoice_number', 'reference_number', 'title', 'code',
    ];

    /**
     * Foreign-key attribute → [related model, label column]. Used to translate
     * raw ids in before/after snapshots into names in the viewer and exports.
     */
    private const FK_LABELS = [
        'outlet_id'               => [\App\Models\Outlet::class, 'name'],
        'from_outlet_id'          => [\App\Models\Outlet::class, 'name'],
        'to_outlet_id'            => [\App\Models\Outlet::class, 'name'],
        'delivery_outlet_id'      => [\App\Models\Outlet::class, 'name'],
        'supplier_id'             => [\App\Models\Supplier::class, 'name'],
        'ingredient_id'           => [\App\Models\Ingredient::class, 'name'],
        'recipe_id'               => [\App\Models\Recipe::class, 'name'],
        'prep_recipe_id'          => [\App\Models\Recipe::class, 'name'],
        'ingredient_category_id'  => [\App\Models\IngredientCategory::class, 'name'],
        'sales_category_id'       => [\App\Models\SalesCategory::class, 'name'],
        'department_id'           => [\App\Models\Department::class, 'name'],
        'section_id'              => [\App\Models\Section::class, 'name'],
        'employee_id'             => [\App\Models\Employee::class, 'name'],
        'tax_rate_id'             => [\App\Models\TaxRate::class, 'name'],
        'uom_id'                  => [\App\Models\UnitOfMeasure::class, 'name'],
        'base_uom_id'             => [\App\Models\UnitOfMeasure::class, 'name'],
        'recipe_uom_id'           => [\App\Models\UnitOfMeasure::class, 'name'],
        'secondary_recipe_uom_id' => [\App\Models\UnitOfMeasure::class, 'name'],
        'yield_uom_id'            => [\App\Models\UnitOfMeasure::class, 'name'],
        'kitchen_id'              => [\App\Models\CentralKitchen::class, 'name'],
        'default_kitchen_id'      => [\App\Models\CentralKitchen::class, 'name'],
        'cpu_id'                  => [\App\Models\CentralPurchasingUnit::class, 'name'],
        'default_cpu_id'          => [\App\Models\CentralPurchasingUnit::class, 'name'],
        'user_id'                 => [\App\Models\User::class, 'name'],
        'created_by'              => [\App\Models\User::class, 'name'],
        'approved_by'             => [\App\Models\User::class, 'name'],
        'received_by'             => [\App\Models\User::class, 'name'],
        'submitted_by'            => [\App\Models\User::class, 'name'],
        'purchase_order_id'       => [\App\Models\PurchaseOrder::class, 'po_number'],
        'parent_po_id'            => [\App\Models\PurchaseOrder::class, 'po_number'],
        'purchase_request_id'     => [\App\Models\PurchaseRequest::class, 'pr_number'],
        'delivery_order_id'       => [\App\Models\DeliveryOrder::class, 'do_number'],
        'goods_received_note_id'  => [\App\Models\GoodsReceivedNote::class, 'grn_number'],
        'procurement_invoice_id'  => [\App\Models\ProcurementInvoice::class, 'invoice_number'],
        'stock_transfer_order_id' => [\App\Models\StockTransferOrder::class, 'sto_number'],
    ];

    /**
     * Resolve display names for a page of audit rows, keyed "type:id".
     *
     * Live (and soft-deleted) records are looked up in one query per model
     * type; hard-deleted records fall back to the name captured in the log's
     * own snapshots, so "who deleted what" stays readable forever. Rows whose
     * record has no name-like attribute are simply absent from the map.
     */
    public static function recordLabels(iterable $logs): array
    {
        $byType = [];
        foreach ($logs as $log) {
            if ($log->auditable_id) {
                $byType[$log->auditable_type][(int) $log->auditable_id] = true;
            }
        }

        $labels = [];
        foreach ($byType as $type => $ids) {
            $class = Relation::getMorphedModel($type) ?? $type;
            if (! class_exists($class)) {
                continue;
            }

            try {
                foreach (self::lookupQuery($class)->whereIn('id', array_keys($ids))->get() as $row) {
                    if ($label = self::labelFromAttributes($row->getAttributes())) {
                        $labels[$type . ':' . $row->getKey()] = $label;
                    }
                }
            } catch (\Throwable) {
                // A resolution failure must never break the viewer; #id remains.
            }
        }

        foreach ($logs as $log) {
            $key = $log->auditable_type . ':' . $log->auditable_id;
            if (! isset($labels[$key])
                && ($label = self::labelFromAttributes(array_merge((array) $log->old_values, (array) $log->new_values)))) {
                $labels[$key] = $label;
            }
        }

        return $labels;
    }

    /**
     * Resolve foreign-key values appearing in a page of before/after snapshots
     * to display names: ['supplier_id' => [5 => 'ABC Foods'], …]. One query per
     * related model, shared across attributes pointing at the same model.
     */
    public static function foreignLabels(iterable $logs): array
    {
        $wanted = []; // class => [id => true]
        foreach ($logs as $log) {
            foreach (array_merge((array) $log->old_values, (array) $log->new_values) as $k => $v) {
                if (isset(self::FK_LABELS[$k]) && is_numeric($v)) {
                    $wanted[self::FK_LABELS[$k][0]][(int) $v] = true;
                }
            }
        }

        $byClass = [];
        foreach ($wanted as $class => $ids) {
            $column = null;
            foreach (self::FK_LABELS as [$c, $col]) {
                if ($c === $class) { $column = $col; break; }
            }

            try {
                $byClass[$class] = self::lookupQuery($class)
                    ->whereIn('id', array_keys($ids))
                    ->pluck($column, 'id')
                    ->all();
            } catch (\Throwable) {
                // Ignore; those values render as raw ids.
            }
        }

        $out = [];
        foreach (self::FK_LABELS as $key => [$class, $col]) {
            if (! empty($byClass[$class])) {
                $out[$key] = $byClass[$class];
            }
        }

        return $out;
    }

    /** Whether snapshot attribute $key is a known foreign key we can name. */
    public static function isForeignKey(string $key): bool
    {
        return isset(self::FK_LABELS[$key]);
    }

    /** Base lookup query for label resolution, including soft-deleted rows. */
    private static function lookupQuery(string $class): Builder
    {
        $q = $class::query();

        return in_array(SoftDeletes::class, class_uses_recursive($class), true)
            ? $q->withTrashed()
            : $q;
    }

    /** First non-empty name-like attribute, or null. */
    private static function labelFromAttributes(array $attributes): ?string
    {
        foreach (self::LABEL_CANDIDATES as $attr) {
            $v = $attributes[$attr] ?? null;
            if (is_string($v) && trim($v) !== '') {
                return trim($v);
            }
        }

        return null;
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
