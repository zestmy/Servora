<?php

namespace App\Models;

use App\Models\Concerns\PurgesStoredFiles;
use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One body of training material.
 *
 * Where the prose came from is recorded but not enforced after import: an
 * SOP-backed course can be edited freely, and `resync()` is an explicit act
 * rather than something that happens behind the author's back. A recipe that
 * changes its method should not silently rewrite the course somebody spent an
 * afternoon shaping around it.
 */
class TrainingCourse extends Model
{
    use PurgesStoredFiles;
    use SoftDeletes;

    public const SOURCE_TYPES = [
        'sop'    => 'From an SOP',
        'upload' => 'From a document',
        'text'   => 'Written here',
    ];

    public const STATUSES = [
        'draft'     => 'Draft',
        'published' => 'Published',
        'archived'  => 'Archived',
    ];

    protected $fillable = [
        'company_id', 'title', 'summary', 'category',
        'source_type', 'source_recipe_id', 'source_filename', 'source_path',
        'content', 'cover_path', 'video_url', 'estimated_minutes',
        'status', 'is_compliance', 'recertify_months', 'created_by',
    ];

    protected $casts = [
        'estimated_minutes' => 'integer',
        'recertify_months'  => 'integer',
        'is_compliance'     => 'boolean',
    ];

    /**
     * Mirrors the column defaults, and this one crashes without it.
     *
     * Eloquent does not read DB-side defaults back after an insert, and a cast
     * is not applied to an attribute that was never set — so on a course
     * created without `is_compliance`, reading it returns NULL rather than
     * false. QuizGeneratorService::draftQuizFor() copies it straight into the
     * quiz's `issues_certificate`, which is NOT NULL, and the insert dies.
     *
     * It hid because the course FORM always sends the key, so every path a
     * person can click was fine; it only surfaced the first time a course was
     * created from code that left it out. Same trap as TrainingSessionPlayer's
     * counters and the one LabelSet documents.
     */
    protected $attributes = [
        'source_type'       => 'text',
        'status'            => 'draft',
        'estimated_minutes' => 5,
        'is_compliance'     => false,
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope());

        // The cover art and any uploaded source document belong to the row.
        static::deleting(function (self $course) {
            if ($course->isForceDeleting()) {
                self::purgeStoredFile($course->cover_path);
                self::purgeStoredFile($course->source_path);
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function sourceRecipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class, 'source_recipe_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function outlets(): BelongsToMany
    {
        return $this->belongsToMany(Outlet::class, 'training_course_outlets')->withTimestamps();
    }

    public function quizzes(): HasMany
    {
        return $this->hasMany(TrainingQuiz::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(TrainingAssignment::class);
    }

    public function pathItems(): HasMany
    {
        return $this->hasMany(TrainingPathItem::class);
    }

    public function certificates(): HasMany
    {
        return $this->hasMany(TrainingCertificate::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }

    /**
     * Courses a trainee holding these outlets may open.
     *
     * Identical in shape to Recipe::scopeVisibleToOutlets, and deliberately so:
     * a course with no outlets is company-wide, which is what "everyone does the
     * allergen course" has to mean. An empty list is the legacy unrestricted
     * trainee, exactly as it is for SOPs.
     */
    public function scopeVisibleToOutlets(Builder $query, array $outletIds): Builder
    {
        if (empty($outletIds)) {
            return $query;
        }

        return $query->where(function ($q) use ($outletIds) {
            $q->whereDoesntHave('outlets')
              ->orWhereHas('outlets', fn ($o) => $o->whereIn('outlets.id', $outletIds));
        });
    }

    /** The live quiz for this course, if there is one. */
    public function primaryQuiz(): ?TrainingQuiz
    {
        return $this->quizzes()->where('status', 'published')->orderBy('id')->first()
            ?? $this->quizzes()->orderBy('id')->first();
    }

    /**
     * Whether the document this course was built from can be READ by staff.
     *
     * PDFs only, and that is the honest limit rather than a first pass: a
     * browser renders a PDF and does not render a .docx, so embedding one would
     * be offering a download dressed as a page. The extracted text remains the
     * material for everything else — and remains available underneath the PDF,
     * because a phone that will not draw one still has to be able to read the
     * course.
     */
    public function sourceIsPdf(): bool
    {
        return $this->source_path
            && strtolower(pathinfo($this->source_path, PATHINFO_EXTENSION)) === 'pdf';
    }

    public function sourceLabel(): string
    {
        return self::SOURCE_TYPES[$this->source_type] ?? 'Written here';
    }

    /**
     * Roughly how long the material takes to read, in whole minutes.
     *
     * 200 words a minute is the usual figure for adult reading of familiar
     * material, and everything here is familiar material. Floored at one so a
     * three-line course never advertises itself as taking no time at all.
     */
    public static function readingMinutes(?string $content): int
    {
        $words = str_word_count(strip_tags((string) $content));

        return max(1, (int) ceil($words / 200));
    }
}
