<?php

namespace App\Models;

use App\Scopes\CompanyScope;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Employee extends Model
{
    protected $fillable = [
        'company_id', 'outlet_id', 'section_id', 'staff_id',
        'name', 'designation',
        'email', 'phone', 'is_active',
        'join_date', 'date_of_birth', 'food_handler_certified', 'food_handler_cert_no', 'food_handler_expired_on',
        'typhoid_card', 'typhoid_valid_from', 'typhoid_expired_on',
        'employment_status', 'employment_status_date', 'outsourcing_company',
        'halal_training', 'halal_training_date', 'halal_training_expired_on',
        'service_points_entitlement', 'basic_salary', 'pay_type', 'sort_order',
        'break_minutes',
    ];

    /**
     * Attributes only readable with the hr.compensation permission. Screens
     * that render employee data check this list rather than hard-coding the
     * column names, so adding a pay field here covers every caller.
     */
    public const SENSITIVE_PAY_ATTRIBUTES = [
        'service_points_entitlement', 'basic_salary', 'pay_type',
    ];

    /**
     * Never mass-assignable and never serialised. The label PIN is written
     * only through setLabelPin(), which hashes it.
     */
    protected $hidden = ['label_pin'];

    // ── Label staff PIN ───────────────────────────────────────────────────

    /** Hash and store a PIN. Pass null to revoke access entirely. */
    public function setLabelPin(?string $pin): void
    {
        $this->forceFill([
            'label_pin'        => $pin === null ? null : \Illuminate\Support\Facades\Hash::make($pin),
            'label_pin_set_at' => $pin === null ? null : now(),
        ])->save();
    }

    public function hasLabelPin(): bool
    {
        return filled($this->label_pin);
    }

    public function verifyLabelPin(string $pin): bool
    {
        return $this->hasLabelPin()
            && \Illuminate\Support\Facades\Hash::check($pin, $this->label_pin);
    }

    /**
     * Identifies WHICH PIN a session was opened with.
     *
     * Stored in the session at sign-in and re-checked on every request, so
     * changing or revoking a PIN drops every session opened under the old
     * one. That is what makes a session last exactly "until the PIN changes"
     * rather than needing an expiry.
     *
     * A hash of the hash — the stored bcrypt digest never reaches the session.
     */
    public function labelPinFingerprint(): ?string
    {
        return $this->hasLabelPin() ? hash('sha256', (string) $this->label_pin) : null;
    }

    /**
     * Phone dial codes for the form's country selector, keyed by ISO-2.
     * Dial values are unique so an edited number maps back to one entry.
     */
    public const PHONE_COUNTRY_CODES = [
        'MY' => '+60',  'SG' => '+65',  'ID' => '+62',  'TH' => '+66',
        'PH' => '+63',  'VN' => '+84',  'BN' => '+673', 'KH' => '+855',
        'MM' => '+95',  'LA' => '+856', 'CN' => '+86',  'HK' => '+852',
        'TW' => '+886', 'JP' => '+81',  'KR' => '+82',  'IN' => '+91',
        'BD' => '+880', 'PK' => '+92',  'NP' => '+977', 'LK' => '+94',
        'AU' => '+61',  'NZ' => '+64',  'GB' => '+44',  'US' => '+1',
        'AE' => '+971', 'SA' => '+966', 'QA' => '+974',
    ];

    public const EMPLOYMENT_STATUSES = [
        'probation'          => 'Probation',
        'confirmed'          => 'Confirmed',
        'extended_probation' => 'Extended Probation',
        'partimer'           => 'Partimer',
        'outsourcing'        => 'Outsourcing',
        'resigned'           => 'Resigned',
    ];

    /**
     * Statuses whose `employment_status_date` is required, and the label the
     * form puts on the date field for each.
     *
     * Outsourcing takes a company instead of a date; a part-timer has neither
     * an until nor a since, so both stay out of this list and their date is
     * nulled on save.
     */
    public const EMPLOYMENT_STATUS_DATE_LABELS = [
        'probation'          => 'Probation — Until',
        'confirmed'          => 'Confirmed — On',
        'extended_probation' => 'Probation Extended — Until',
        'resigned'           => 'Resigned — On',
    ];

    /**
     * The compliance documents tracked per employee, in the order they are
     * reported on the Employees card and in the reminder email.
     *
     * `held` is the boolean saying the employee has the document at all;
     * `expires` is the date it lapses. Every screen, export and the scheduled
     * reminder reads this list rather than naming the columns, so a fourth
     * document is a new entry here plus a migration — not a hunt through the
     * views for the three places that would otherwise disagree.
     */
    public const COMPLIANCE_DOCUMENTS = [
        'typhoid' => [
            'label'   => 'Typhoid Card',
            'held'    => 'typhoid_card',
            'expires' => 'typhoid_expired_on',
        ],
        'food_handler' => [
            'label'   => 'Food Handler',
            'held'    => 'food_handler_certified',
            'expires' => 'food_handler_expired_on',
        ],
        'halal' => [
            'label'   => 'Halal Training',
            'held'    => 'halal_training',
            'expires' => 'halal_training_expired_on',
        ],
    ];

    /** How basic_salary is expressed. */
    public const PAY_TYPES = [
        'monthly' => 'Monthly',
        'daily'   => 'Daily',
        'hourly'  => 'Hourly',
    ];

    /** Short suffix for a salary figure, e.g. "/ mth". */
    public const PAY_TYPE_SUFFIXES = [
        'monthly' => '/ mth',
        'daily'   => '/ day',
        'hourly'  => '/ hr',
    ];

    protected $casts = [
        'is_active'              => 'boolean',
        'join_date'              => 'date',
        'date_of_birth'          => 'date',
        'employment_status_date' => 'date',
        'food_handler_certified' => 'boolean',
        'food_handler_expired_on' => 'date',
        'typhoid_card'           => 'boolean',
        'typhoid_valid_from'     => 'date',
        'typhoid_expired_on'     => 'date',
        'halal_training'         => 'boolean',
        'halal_training_date'    => 'date',
        'halal_training_expired_on' => 'date',
        'service_points_entitlement' => 'decimal:2',
        'break_minutes'              => 'integer',
        'basic_salary'               => 'decimal:2',
        'label_pin_set_at'           => 'datetime',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope());
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    /**
     * The shared staff order: the manual drag order from the Employees list
     * first, then anything never dragged, alphabetically.
     *
     * Every screen that lists staff uses this so the Employees list,
     * Attendance Record grid and Duty Roster read top-to-bottom the same way —
     * reordering in one is meant to be visible in all of them.
     */
    public function scopeInListOrder($query)
    {
        return $query
            ->orderByRaw('employees.sort_order IS NULL')
            ->orderBy('employees.sort_order')
            ->orderBy('employees.name');
    }

    /**
     * Staff whose records still need processing for a period starting $from:
     * everyone active, plus anyone who resigned on or after that date.
     *
     * A resignation does not end the paperwork. Attendance for the days worked
     * up to the leaving date still has to be completed and the last OT claims
     * approved and paid, so filtering on is_active alone made someone vanish
     * from the very month their final pay depends on. They drop off by
     * themselves once the period being viewed starts after they left.
     */
    public function scopeEmployedDuring($query, $from)
    {
        return $query->where(function ($q) use ($from) {
            $q->where('employees.is_active', true)
              ->orWhere(function ($r) use ($from) {
                  $r->where('employees.employment_status', 'resigned')
                    ->whereNotNull('employees.employment_status_date')
                    ->whereDate('employees.employment_status_date', '>=', $from);
              });
        });
    }

    /** True once the resignation date has passed. */
    public function hasResigned(): bool
    {
        return $this->employment_status === 'resigned';
    }

    /**
     * The last date this employee can have attendance or an OT claim against
     * them, or null if they are still employed.
     */
    public function employedUntil(): ?Carbon
    {
        return $this->hasResigned() ? $this->employment_status_date : null;
    }

    /**
     * Whether the given (default: current) user may read salary and service
     * point data. The single gate for every employee screen, export and import
     * so pay visibility can never drift between them.
     */
    public static function canViewPay($user = null): bool
    {
        $user ??= \Illuminate\Support\Facades\Auth::user();

        return (bool) $user?->can('hr.compensation');
    }

    /** Salary formatted with its pay-type suffix, e.g. "3,000.00 / mth". */
    public function salaryLabel(): ?string
    {
        if ($this->basic_salary === null) return null;

        $suffix = static::PAY_TYPE_SUFFIXES[$this->pay_type] ?? null;

        return trim(number_format((float) $this->basic_salary, 2) . ' ' . $suffix);
    }

    public function employmentStatusLabel(): ?string
    {
        return static::EMPLOYMENT_STATUSES[$this->employment_status] ?? null;
    }

    /**
     * Secondary line for the employment status: the until/since date for
     * probation states, the leaving date for a resignation, or the provider
     * name for outsourcing.
     */
    public function employmentStatusDetail(): ?string
    {
        return match ($this->employment_status) {
            'probation', 'extended_probation' => $this->employment_status_date
                ? 'until ' . $this->employment_status_date->format('d M Y') : null,
            'confirmed' => $this->employment_status_date
                ? 'since ' . $this->employment_status_date->format('d M Y') : null,
            'resigned' => $this->employment_status_date
                ? 'on ' . $this->employment_status_date->format('d M Y') : null,
            'outsourcing' => $this->outsourcing_company,
            default => null,
        };
    }

    /** Employee certifications from the company's managed training catalogue. */
    public function certifications(): HasMany
    {
        return $this->hasMany(EmployeeCertification::class);
    }

    /** Allowances and deductions assigned to this employee. */
    public function payComponents(): HasMany
    {
        return $this->hasMany(EmployeePayComponent::class);
    }

    /** Basic salary change history, newest first. */
    public function salaryRevisions(): HasMany
    {
        return $this->hasMany(SalaryRevision::class)->orderByDesc('effective_on');
    }
}
