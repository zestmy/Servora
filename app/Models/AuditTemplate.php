<?php

namespace App\Models;

use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * An audit form: the definition an outlet audit is started from.
 *
 * Not to be confused with AuditLog, the activity trail. See the create
 * migration for the shape and why sections have a scoring mode.
 */
class AuditTemplate extends Model
{
    use SoftDeletes;

    /**
     * Header field types the builder offers.
     *
     * Two people-pickers, on purpose: `employee` lists the AUDITED OUTLET's
     * staff (the shift officer on duty), `auditor` lists the company's
     * APPOINTED AUDITORS (Settings ▸ Appointed Auditors) — the QA person from
     * head office who is at every outlet and on none of their rosters.
     */
    public const HEADER_TYPES = ['text', 'number', 'time', 'textarea', 'employee', 'auditor'];

    public const HEADER_TYPE_LABELS = [
        'text'     => 'Text',
        'number'   => 'Number',
        'time'     => 'Time',
        'textarea' => 'Notes',
        'employee' => 'Outlet employee',
        'auditor'  => 'Appointed auditor',
    ];

    protected $fillable = [
        'company_id', 'name', 'code', 'description', 'alt_language', 'is_active',
        'version', 'header_fields', 'requires_acknowledgement', 'created_by',
    ];

    protected $casts = [
        'is_active'                => 'boolean',
        'requires_acknowledgement' => 'boolean',
        'version'                  => 'integer',
        'header_fields'            => 'array',
    ];

    // A template created with only a name is a usable template: the column
    // defaults exist in the schema, but a model created in the same request
    // is read before it is re-fetched, and start() copies these onto the audit.
    protected $attributes = [
        'is_active'                => true,
        'requires_acknowledgement' => true,
        'version'                  => 1,
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope());
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function sections(): HasMany
    {
        return $this->hasMany(AuditTemplateSection::class)->orderBy('sort_order');
    }

    public function audits(): HasMany
    {
        return $this->hasMany(Audit::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    public function scopeOrdered(Builder $q): Builder
    {
        return $q->orderBy('name');
    }

    /** Header field definitions, always as a clean list. */
    public function headerFieldList(): array
    {
        return collect($this->header_fields ?? [])
            ->filter(fn ($f) => is_array($f) && ! empty($f['label']))
            ->map(fn ($f) => [
                'key'      => $f['key'] ?? \Illuminate\Support\Str::slug($f['label'], '_'),
                'label'    => $f['label'],
                'type'     => in_array($f['type'] ?? 'text', self::HEADER_TYPES, true) ? $f['type'] : 'text',
                'required' => (bool) ($f['required'] ?? false),
            ])
            ->values()
            ->all();
    }

    /** Sum of leaf points across every section, whatever the scoring mode. */
    public function totalPoints(): int
    {
        return (int) AuditTemplateItem::query()
            ->whereIn('audit_template_section_id', $this->sections()->pluck('id'))
            ->whereNotIn('id', function ($q) {
                $q->select('parent_id')->from('audit_template_items')->whereNotNull('parent_id');
            })
            ->sum('points');
    }
}
