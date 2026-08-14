<?php

namespace App\Models;

use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * "This outlet / this person must complete this course / this path by then."
 *
 * Exactly one of course/path is set, and exactly one of trainee/outlet — see
 * the migration note for why the outlet form is the one that survives turnover.
 */
class TrainingAssignment extends Model
{
    protected $fillable = [
        'company_id', 'training_course_id', 'training_path_id',
        'employee_id', 'outlet_id', 'due_on', 'is_mandatory', 'note', 'assigned_by',
    ];

    protected $casts = [
        'due_on'       => 'date',
        'is_mandatory' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope());
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(TrainingCourse::class, 'training_course_id');
    }

    public function path(): BelongsTo
    {
        return $this->belongsTo(TrainingPath::class, 'training_path_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    /**
     * Assignments that land on this employee: theirs by name, plus everything
     * required of the outlet they are posted to.
     */
    public function scopeForEmployee(Builder $query, Employee $employee): Builder
    {
        return $query->where(function ($q) use ($employee) {
            $q->where('employee_id', $employee->id);

            if ($employee->outlet_id) {
                $q->orWhere('outlet_id', $employee->outlet_id);
            }
        });
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->whereNotNull('due_on')->whereDate('due_on', '<', now()->toDateString());
    }

    public function targetName(): string
    {
        return $this->course?->title ?? $this->path?->name ?? 'Removed item';
    }

    public function audienceName(): string
    {
        return $this->employee?->name ?? $this->outlet?->name ?? 'Everyone';
    }
}
