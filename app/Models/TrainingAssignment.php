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
        'company_id', 'training_course_id', 'training_path_id', 'training_quiz_id',
        'employee_id', 'outlet_id', 'section_id',
        'due_on', 'is_mandatory', 'note', 'assigned_by',
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

    /** Set when the requirement is one specific questionnaire. */
    public function quiz(): BelongsTo
    {
        return $this->belongsTo(TrainingQuiz::class, 'training_quiz_id');
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
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
     * Assignments that land on this employee.
     *
     * Theirs by name, plus anything required of an audience they fall inside.
     * The audience columns NARROW each other rather than compete, so a row is
     * only skipped when it names something this person is not: an assignment
     * for the kitchen at Bangsar reaches a Bangsar cook and neither a Bangsar
     * waiter nor a cook at Damansara.
     *
     * A null column means "any", which is what makes the four useful shapes
     * fall out of one condition rather than four branches — see the migration.
     * The employee's own nulls are handled too: somebody with no posting is
     * reached only by an assignment that names no outlet, because an outlet
     * requirement is not something you can meet from outside it.
     */
    public function scopeForEmployee(Builder $query, Employee $employee): Builder
    {
        return $query->where(function ($q) use ($employee) {
            $q->where('employee_id', $employee->id);

            $q->orWhere(function ($audience) use ($employee) {
                // Not an individual assignment for somebody else.
                $audience->whereNull('employee_id');

                $audience->where(fn ($o) => $o->whereNull('outlet_id')
                    ->when($employee->outlet_id, fn ($m) => $m->orWhere('outlet_id', $employee->outlet_id)));

                $audience->where(fn ($s) => $s->whereNull('section_id')
                    ->when($employee->section_id, fn ($m) => $m->orWhere('section_id', $employee->section_id)));
            });
        });
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->whereNotNull('due_on')->whereDate('due_on', '<', now()->toDateString());
    }

    public function targetName(): string
    {
        return $this->quiz?->title ?? $this->course?->title ?? $this->path?->name ?? 'Removed item';
    }

    public function targetKind(): string
    {
        return match (true) {
            $this->training_quiz_id !== null => 'Quiz',
            $this->training_path_id !== null => 'Learning path',
            default                          => 'Course',
        };
    }

    /**
     * Who this lands on, said the way a manager would say it.
     *
     * "BOH at Bangsar" rather than two fields the reader has to combine
     * themselves — the whole point of allowing the pair is that the pair is one
     * idea.
     */
    public function audienceName(): string
    {
        if ($this->employee) {
            return $this->employee->name;
        }

        $section = $this->section?->name;
        $outlet  = $this->outlet?->name;

        return match (true) {
            $section && $outlet => $section . ' at ' . $outlet,
            (bool) $section     => $section . ' — every branch',
            (bool) $outlet      => $outlet,
            default             => 'Everyone',
        };
    }
}
