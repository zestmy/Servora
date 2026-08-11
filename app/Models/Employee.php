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
        'break_minutes', 'ic_number', 'bank_name', 'bank_account_no', 'bank_account_name',
        'daily_working_hours', 'reports_to_id', 'allow_byod', 'allow_anywhere',
        'service_charge_outlet_id', 'overtime_as_time_off',
        // Particulars — each picked from a managed list, see HrOption::TYPES.
        'gender', 'nationality', 'race', 'religion', 'marital_status', 'education_level',
        'emergency_contact_name', 'emergency_contact_relationship',
        'emergency_contact_phone', 'emergency_contact_phone_alt', 'emergency_contact_address',
        'photo_path',
    ];

    /**
     * Attributes only readable with the hr.compensation permission. Screens
     * that render employee data check this list rather than hard-coding the
     * column names, so adding a pay field here covers every caller.
     */
    public const SENSITIVE_PAY_ATTRIBUTES = [
        'service_points_entitlement', 'basic_salary', 'pay_type',
        /*
         * BANK DETAILS ARE NOT ON THIS LIST, by decision on 2026-08-11: they
         * moved to the Personal tab of the employee form, where the people who
         * keep staff records current can maintain an account number without
         * being shown the company's payroll to do it.
         *
         * The trade was made with the consequence stated: an account number,
         * and a holder's name that is often a family member's, are now visible
         * to anyone who may edit an employee. Reversing it is putting these
         * three back:
         *     'bank_name', 'bank_account_no', 'bank_account_name'
         * and moving the fields back across the tabs in employee-form.blade.
         *
         * What has NOT changed: the employee details PDF still prints bank
         * details only inside its Pay & Bank section, which is gated on
         * hr.compensation, and no list or export renders them.
         */
    ];

    /**
     * The name to put on a salary transfer.
     *
     * Blank means "the account is their own", which is the normal case and the
     * reason this is a fallback rather than a required field.
     */
    public function payeeName(): string
    {
        return filled($this->bank_account_name)
            ? (string) $this->bank_account_name
            : (string) $this->name;
    }

    /** Whether the account is held by somebody other than the employee. */
    public function bankAccountIsThirdParty(): bool
    {
        return filled($this->bank_account_name)
            && mb_strtolower(trim((string) $this->bank_account_name)) !== mb_strtolower(trim((string) $this->name));
    }

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
     * Whether this person is expected to punch on their own phone.
     *
     * The outlet sets the rule and the employee is the exception to it, which
     * is the whole reason allow_byod is nullable — null inherits, so an outlet
     * can be switched to its kiosk without editing every member of staff, and
     * the people who genuinely need a phone stay marked as such through the
     * change.
     *
     * Read at the moment of the punch rather than cached anywhere: somebody
     * moved to another outlet inherits that outlet's rule the same day.
     *
     * A false answer never refuses a punch — it flags one. Nothing in this
     * feature is allowed to leave a person unable to record that they turned
     * up for work.
     */
    public function canUseOwnDevice(?Outlet $outlet = null): bool
    {
        if ($this->allow_byod !== null) {
            return (bool) $this->allow_byod;
        }

        $outlet ??= $this->outlet;

        // No outlet resolved is not a licence to guess. Somebody whose posting
        // is missing has bigger problems — outletFor() refuses the punch
        // outright — and answering "yes" here would be inventing a permission
        // out of an absence.
        return $outlet ? ! $outlet->expectsKiosk() : true;
    }

    /**
     * Whether the outlet's geofence applies to this person at all.
     *
     * For staff whose work is not at the outlet — area managers touring
     * branches, drivers, an offsite catering crew — every punch is legitimately
     * outside the fence, so measuring them against it produces a flag a manager
     * has to dismiss twice a day forever.
     *
     * What it switches off is the JUDGEMENT, never the RECORD. Coordinates,
     * accuracy and the distance from the outlet are all still computed and
     * stored on the punch exactly as they are for everybody else — a manager
     * looking at a driver's day can still see where each punch was made. This
     * only stops that distance being treated as a problem.
     *
     * It is also narrow on purpose: it exempts somebody from the GEOFENCE, not
     * from providing a location. `require_gps` still applies, because "your job
     * is not at the outlet" is a reason to stop measuring the distance, not a
     * reason to stop knowing where somebody was.
     */
    public function canClockAnywhere(): bool
    {
        return (bool) $this->allow_anywhere;
    }

    /**
     * How this person's approved overtime is settled unless told otherwise.
     *
     * A default that the claim then carries for itself. Deliberately NOT read
     * at payroll time from the employee record: a claim approved for payroll
     * last month must stay a payroll claim even if the person is moved onto
     * time-off terms today, and resolving it live would rewrite the past every
     * time somebody edited an employee.
     */
    public function overtimeSettlementDefault(): string
    {
        return $this->overtime_as_time_off
            ? OvertimeClaim::SETTLE_TIME_OFF
            : OvertimeClaim::SETTLE_PAYROLL;
    }

    /** The outlet whose service charge pool pays this person. */
    public function serviceChargeOutlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class, 'service_charge_outlet_id');
    }

    /**
     * Which pool pays them — the override if set, otherwise their posting.
     *
     * One accessor, used by every query and by the arithmetic, so "which pool"
     * is answered the same way in the divisor as in the rows. Those two
     * drifting apart is the failure this feature can most easily cause: the
     * RM/point is pool ÷ total points, so a person counted in one and not the
     * other silently misprices EVERYBODY in that outlet.
     */
    public function serviceChargeOutletId(): ?int
    {
        return $this->service_charge_outlet_id ?: $this->outlet_id;
    }

    /** Whether they are paid from somewhere other than where they are posted. */
    public function serviceChargeIsElsewhere(): bool
    {
        return $this->service_charge_outlet_id !== null
            && (int) $this->service_charge_outlet_id !== (int) $this->outlet_id;
    }

    /**
     * Employees a given outlet's service charge pool pays.
     *
     * Redirected staff come IN, and their home outlet loses them — the OR is
     * written so that each person matches exactly one outlet, which is what
     * makes the two pools' divisors add up to the whole company. A null
     * $outletId is the all-outlets pool and matches everybody.
     */
    public function scopeForServiceChargeOutlet(\Illuminate\Database\Eloquent\Builder $query, ?int $outletId): \Illuminate\Database\Eloquent\Builder
    {
        if ($outletId === null) {
            return $query;
        }

        return $query->where(function ($q) use ($outletId) {
            $q->where('employees.service_charge_outlet_id', $outletId)
                ->orWhere(function ($q) use ($outletId) {
                    $q->whereNull('employees.service_charge_outlet_id')
                        ->where('employees.outlet_id', $outletId);
                });
        });
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
     * The same idea as labelPinFingerprint(), for sessions opened by email.
     *
     * Changing the address on someone's record ends the sessions opened with the
     * old one — the analogue of a reissued PIN ending the sessions opened with
     * the one before it.
     */
    public function emailFingerprint(): ?string
    {
        return filled($this->email) ? hash('sha256', mb_strtolower(trim((string) $this->email))) : null;
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
        // Nullable on purpose: null is "whatever the outlet says", which is
        // what nearly every row holds. See canUseOwnDevice().
        'allow_byod'             => 'boolean',
        // NOT nullable — there is no per-outlet rule for it to inherit.
        // See canClockAnywhere().
        'allow_anywhere'         => 'boolean',
        'overtime_as_time_off'   => 'boolean',
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
        'daily_working_hours'    => 'decimal:2',
        'break_minutes'              => 'integer',
        'basic_salary'               => 'decimal:2',
        'label_pin_set_at'           => 'datetime',
        'pin_login_disabled_at'      => 'datetime',
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

    /** This employee's superior — who their leave goes to. */
    public function superior(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'reports_to_id');
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Employee::class, 'reports_to_id');
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

    /** True when the employment status is resigned, whatever the date. */
    public function hasResigned(): bool
    {
        return $this->employment_status === 'resigned';
    }

    /**
     * Whether a resignation has actually taken effect — i.e. the leaving date
     * is behind us. The date itself is the last working day, so someone
     * resigning on the 31st is still employed on the 31st.
     *
     * Static so the same rule can be applied to a form payload before the row
     * exists; every write path goes through this rather than re-deriving it.
     */
    public static function resignationTookEffect(?string $status, $date, ?Carbon $on = null): bool
    {
        if ($status !== 'resigned' || ! $date) {
            return false;
        }

        return Carbon::parse($date)->startOfDay()->lt(($on ?? Carbon::today())->copy()->startOfDay());
    }

    /** Instance form of {@see resignationTookEffect()}. */
    public function resignationIsEffective(?Carbon $on = null): bool
    {
        return static::resignationTookEffect($this->employment_status, $this->employment_status_date, $on);
    }

    /**
     * Resigned staff still flagged active whose leaving date has passed —
     * the set `hr:apply-resignations` deactivates each night.
     */
    public function scopeResignationDue($query, ?Carbon $on = null)
    {
        return $query->where('employees.is_active', true)
            ->where('employees.employment_status', 'resigned')
            ->whereNotNull('employees.employment_status_date')
            ->whereDate('employees.employment_status_date', '<', ($on ?? Carbon::today())->toDateString());
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

    /** Scanned paperwork — application form, IC, typhoid card, and the rest. */
    public function documents(): HasMany
    {
        return $this->hasMany(EmployeeDocument::class)->latest();
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
