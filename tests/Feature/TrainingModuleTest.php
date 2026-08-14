<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use App\Models\Outlet;
use App\Models\TrainingAssignment;
use App\Models\TrainingCourse;
use App\Models\TrainingQuestion;
use App\Models\TrainingQuiz;
use App\Models\TrainingSession;
use App\Models\User;
use App\Services\Training\CertificateService;
use App\Services\Training\LeaderboardService;
use App\Services\Training\LiveSessionService;
use App\Services\Training\ReportCardService;
use App\Services\Training\ScoringService;
use App\Services\Training\SelfPacedQuizService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Learning & Development, end to end.
 *
 * The cases here are the ones where being wrong is expensive rather than merely
 * visible: a quiz that can be re-answered after seeing a colleague's screen, a
 * leaderboard that ranks persistence instead of knowledge, a certificate issued
 * twice for the same course, and a course reachable by a trainee whose outlet
 * was never granted it.
 */
class TrainingModuleTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $outlet;
    private Outlet $otherOutlet;
    private User $manager;
    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'Training Co', 'slug' => Str::slug('Training Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);

        $this->outlet = Outlet::create([
            'company_id' => $this->company->id, 'name' => 'Bangsar', 'code' => 'BSR', 'is_active' => true,
        ]);

        $this->otherOutlet = Outlet::create([
            'company_id' => $this->company->id, 'name' => 'Damansara', 'code' => 'DMS', 'is_active' => true,
        ]);

        $this->manager = User::factory()->create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
        ]);

        $this->employee = Employee::create([
            'company_id' => $this->company->id,
            'outlet_id'  => $this->outlet->id,
            'name'       => 'Aisyah Rahman',
            'email'      => 'aisyah' . uniqid() . '@example.test',
            'is_active'  => true,
        ]);
    }

    /**
     * Sign an employee in the way the staff app does.
     *
     * The staff apps are NOT a Laravel guard — see App\Services\Staff\StaffSession
     * for why: an employee is not a login, and conflating the two would let a
     * PIN start behaving like an account. So there is no actingAs() for this,
     * and reaching for one would test something that does not exist.
     *
     * VIA EMAIL, not PIN, and that is the representative case rather than a
     * convenience: the session validates against the credential that opened it,
     * and on a real company most staff have an email and no PIN at all — 48 of
     * 57 at the one this was built against. Signing in with 'pin' here would
     * find no fingerprint and hand back null, which is exactly what would happen
     * to most of the workforce.
     */
    private function asStaff(Employee $employee): static
    {
        app(\App\Services\Staff\StaffSession::class)->signIn($employee, 'email');

        return $this;
    }

    private function course(array $overrides = []): TrainingCourse
    {
        return TrainingCourse::create(array_merge([
            'company_id' => $this->company->id,
            'title'      => 'Chiller discipline',
            'content'    => 'Chillers run between 0 and 4 degrees.',
            'status'     => 'published',
        ], $overrides));
    }

    /** A four-question quiz: three easy marks and one everybody gets wrong. */
    private function quiz(?TrainingCourse $course = null, array $overrides = []): TrainingQuiz
    {
        $quiz = TrainingQuiz::create(array_merge([
            'company_id'         => $this->company->id,
            'training_course_id' => ($course ?? $this->course())->id,
            'title'              => 'Chiller quiz',
            'status'             => 'published',
            'pass_mark'          => 70,
            'default_seconds'    => 20,
            'default_points'     => 1000,
            'shuffle_questions'  => false,
            'shuffle_options'    => false,
        ], $overrides));

        foreach (range(1, 4) as $n) {
            $quiz->questions()->create([
                'type'       => 'mcq',
                'prompt'     => "Question {$n}?",
                'options'    => ['Right', 'Wrong', 'Also wrong', 'Very wrong'],
                'correct'    => [0],
                'topic'      => $n === 4 ? 'Allergens' : 'Food safety',
                'sort_order' => $n,
            ]);
        }

        return $quiz->fresh('questions');
    }

    // ── Access ────────────────────────────────────────────────────────────

    /**
     * A course tagged to one branch is not visible from another. This is the
     * same rule the SOP library already applies, and the reason courses carry a
     * pivot rather than a nullable outlet_id.
     */
    public function test_a_course_tagged_to_another_outlet_is_invisible(): void
    {
        $mine   = $this->course(['title' => 'Mine']);
        $theirs = $this->course(['title' => 'Theirs']);
        $all    = $this->course(['title' => 'Everyone']);

        $mine->outlets()->sync([$this->outlet->id]);
        $theirs->outlets()->sync([$this->otherOutlet->id]);

        $visible = TrainingCourse::published()
            ->visibleToOutlets($this->employee->trainingOutletIds())
            ->pluck('title')
            ->all();

        sort($visible);

        // An untagged course is company-wide — that is what "everyone does the
        // allergen course" has to mean.
        $this->assertSame(['Everyone', 'Mine'], $visible);
        $this->assertNotContains('Theirs', $visible);
        $this->assertNotNull($all);
    }

    // ── Self-paced ────────────────────────────────────────────────────────

    public function test_a_self_paced_attempt_scores_passes_and_lands_on_the_record(): void
    {
        $quiz    = $this->quiz();
        $service = app(SelfPacedQuizService::class);

        $attempt = $service->startOrResume($quiz, $this->employee);

        foreach ($quiz->questions as $i => $question) {
            // Three right, the last one wrong: 75%, which clears a 70 pass mark.
            $service->answer($attempt, $question, $i === 3 ? [1] : [0], 5.0);
        }

        $attempt = $service->finish($attempt);

        $this->assertSame(3, $attempt->correct_count);
        $this->assertSame(4, $attempt->question_count);
        $this->assertSame('75.00', (string) $attempt->percent);
        $this->assertTrue($attempt->passed);
        $this->assertNotNull($attempt->completed_at);

        // 5s of 20 → 87.5% of 1000, three times, plus the streak bonus at three
        // in a row. The wrong one is worth nothing.
        $this->assertGreaterThan(2000, $attempt->score);
    }

    /**
     * Being interrupted mid-quiz is normal on a shift. Resuming must not lose
     * the answers already given, and must not start the questions again.
     */
    public function test_an_interrupted_attempt_resumes_where_it_stopped(): void
    {
        $quiz    = $this->quiz();
        $service = app(SelfPacedQuizService::class);

        $attempt = $service->startOrResume($quiz, $this->employee);
        $service->answer($attempt, $quiz->questions[0], [0], 3.0);
        $service->answer($attempt, $quiz->questions[1], [0], 3.0);

        $resumed = $service->startOrResume($quiz, $this->employee);

        $this->assertSame($attempt->id, $resumed->id);
        $this->assertSame(2, $service->resumeIndex($resumed));
        $this->assertSame(2, $resumed->answers()->count());
    }

    /** A re-answer overwrites rather than double-counting — the unique index. */
    public function test_answering_the_same_question_twice_keeps_one_row(): void
    {
        $quiz    = $this->quiz();
        $service = app(SelfPacedQuizService::class);
        $attempt = $service->startOrResume($quiz, $this->employee);

        $service->answer($attempt, $quiz->questions[0], [1], 4.0);
        $service->answer($attempt, $quiz->questions[0], [0], 4.0);

        $this->assertSame(1, $attempt->answers()->count());
        $this->assertTrue($attempt->answers()->first()->is_correct);
    }

    public function test_the_attempt_allowance_is_enforced(): void
    {
        $quiz    = $this->quiz(null, ['max_attempts' => 1]);
        $service = app(SelfPacedQuizService::class);

        $attempt = $service->startOrResume($quiz, $this->employee);
        foreach ($quiz->questions as $question) {
            $service->answer($attempt, $question, [0], 2.0);
        }
        $service->finish($attempt);

        $this->expectException(\RuntimeException::class);
        $service->startOrResume($quiz->fresh(), $this->employee);
    }

    // ── Live ──────────────────────────────────────────────────────────────

    public function test_a_live_round_runs_from_lobby_to_podium(): void
    {
        $quiz     = $this->quiz();
        $sessions = app(LiveSessionService::class);

        $session = $sessions->open($quiz, $this->outlet->id, $this->manager->id, 'Tuesday briefing');

        $this->assertSame('lobby', $session->status);
        $this->assertSame(4, $session->questionCount());
        $this->assertMatchesRegularExpression('/^\d{6}$/', $session->pin);

        $signedIn  = $sessions->join($session, 'Aisyah', $this->employee);
        $anonymous = $sessions->join($session, 'Chef');

        // A nickname-only player still gets a scoreboard row and an attempt —
        // that is what makes the shift-briefing case work.
        $this->assertNull($anonymous->lms_user_id);
        $this->assertNotNull($anonymous->attempt);

        $sessions->start($session);
        $session->refresh();
        $this->assertSame('question', $session->status);

        $answer = $sessions->answer($session, $signedIn, [0]);
        $this->assertNotNull($answer);
        $this->assertTrue($answer->is_correct);

        // The anonymous player says nothing and should be recorded as having
        // missed it — otherwise a question everybody skipped looks unasked.
        $sessions->reveal($session->fresh());
        $this->assertSame(1, $anonymous->fresh()->attempt->answers()->count());
        $this->assertFalse($anonymous->fresh()->attempt->answers()->first()->is_correct);

        // Walk the rest of the room out.
        $session = $session->fresh();
        while (! $session->isLastQuestion()) {
            $sessions->next($session);
            $session = $session->fresh();
        }
        $sessions->end($session);
        $session->refresh();

        $this->assertSame('ended', $session->status);
        $this->assertNotNull($session->ended_at);

        $attempt = $signedIn->fresh()->attempt->fresh();
        $this->assertNotNull($attempt->completed_at);
        $this->assertSame(4, $attempt->question_count);
        $this->assertSame(1, $attempt->correct_count);
    }

    /**
     * A second tap must not rescore. Without this a player who glanced at a
     * neighbour's screen could answer again at leisure.
     */
    public function test_a_live_player_cannot_answer_the_same_question_twice(): void
    {
        $quiz     = $this->quiz();
        $sessions = app(LiveSessionService::class);
        $session  = $sessions->open($quiz, $this->outlet->id, $this->manager->id);
        $player   = $sessions->join($session, 'Aisyah', $this->employee);

        $sessions->start($session);
        $session->refresh();

        $this->assertNotNull($sessions->answer($session, $player, [1]));  // wrong
        $this->assertNull($sessions->answer($session, $player, [0]));     // ignored

        $this->assertFalse($player->fresh()->attempt->answers()->first()->is_correct);
        $this->assertSame(0, $player->fresh()->score);
    }

    /** The window is the server's, not the phone's. */
    public function test_an_answer_after_the_window_closes_is_refused(): void
    {
        $quiz     = $this->quiz(null, ['default_seconds' => 5]);
        $sessions = app(LiveSessionService::class);
        $session  = $sessions->open($quiz, $this->outlet->id, $this->manager->id);
        $player   = $sessions->join($session, 'Slow Hand');

        $sessions->start($session);

        $session->refresh()->forceFill([
            'question_started_at' => now()->subSeconds(30),
        ])->save();

        $this->assertNull($sessions->answer($session->fresh(), $player, [0]));
    }

    public function test_a_pin_only_matches_a_live_room(): void
    {
        $quiz     = $this->quiz();
        $sessions = app(LiveSessionService::class);
        $session  = $sessions->open($quiz, $this->outlet->id, $this->manager->id);

        $this->assertNotNull($sessions->findByPin($session->pin));

        $sessions->end($session);

        $this->assertNull($sessions->findByPin($session->pin));
    }

    // ── Leaderboard ───────────────────────────────────────────────────────

    /**
     * Best per quiz per person, not the sum of every attempt. Summing would
     * rank by persistence, and would make retrying — which is exactly what an
     * unsure trainee should do — the way to win.
     */
    public function test_the_leaderboard_counts_a_person_s_best_attempt_only(): void
    {
        $quiz    = $this->quiz();
        $service = app(SelfPacedQuizService::class);

        foreach ([[0, 1, 1, 1], [0, 0, 0, 0]] as $run) {
            $attempt = $service->startOrResume($quiz, $this->employee);
            foreach ($quiz->questions as $i => $question) {
                $service->answer($attempt, $question, [$run[$i]], 5.0);
            }
            $service->finish($attempt);
        }

        $board = app(LeaderboardService::class)->board($this->company->id, 'all');

        $this->assertCount(1, $board);
        $this->assertSame(1, $board[0]['quizzes']);
        $this->assertSame(100.0, $board[0]['accuracy']);
        $this->assertSame(1, $board[0]['rank']);
    }

    // ── Report card ───────────────────────────────────────────────────────

    public function test_the_report_card_names_the_topic_to_work_on(): void
    {
        $quiz    = $this->quiz();
        $service = app(SelfPacedQuizService::class);

        // Every "Allergens" question wrong, every "Food safety" one right.
        foreach (range(1, 2) as $ignored) {
            $attempt = $service->startOrResume($quiz, $this->employee);
            foreach ($quiz->questions as $question) {
                $service->answer($attempt, $question, $question->topic === 'Allergens' ? [1] : [0], 4.0);
            }
            $service->finish($attempt);
        }

        $card = app(ReportCardService::class)->for($this->employee);

        $this->assertSame(2, $card['attempts']);
        $this->assertCount(1, $card['weak_topics']);
        $this->assertSame('Allergens', $card['weak_topics'][0]['topic']);
        $this->assertSame(0.0, $card['weak_topics'][0]['accuracy']);
    }

    /**
     * An assignment on an OUTLET reaches everyone at that branch, including
     * people hired after it was set — which is the whole reason to prefer it
     * over naming individuals.
     */
    public function test_an_outlet_assignment_lands_on_a_trainee_who_was_never_named(): void
    {
        $course = $this->course();
        $this->quiz($course);

        TrainingAssignment::create([
            'company_id'         => $this->company->id,
            'training_course_id' => $course->id,
            'outlet_id'          => $this->outlet->id,
            'due_on'             => now()->subDay()->toDateString(),
        ]);

        $outstanding = app(ReportCardService::class)->outstanding($this->employee);

        $this->assertCount(1, $outstanding);
        $this->assertSame('Chiller discipline', $outstanding[0]['title']);
        $this->assertTrue($outstanding[0]['overdue']);
    }

    public function test_passing_clears_the_assignment(): void
    {
        $course = $this->course();
        $quiz   = $this->quiz($course);

        TrainingAssignment::create([
            'company_id'         => $this->company->id,
            'training_course_id' => $course->id,
            'outlet_id'          => $this->outlet->id,
        ]);

        $service = app(SelfPacedQuizService::class);
        $attempt = $service->startOrResume($quiz, $this->employee);
        foreach ($quiz->questions as $question) {
            $service->answer($attempt, $question, [0], 3.0);
        }
        $service->finish($attempt);

        $this->assertCount(0, app(ReportCardService::class)->outstanding($this->employee));
    }

    // ── Certificates ──────────────────────────────────────────────────────

    /**
     * One valid certificate per (trainee, course). A staff file holding four
     * certificates for the same course cannot answer "are they current?".
     */
    public function test_passing_twice_updates_one_certificate_and_keeps_its_serial(): void
    {
        $course = $this->course(['is_compliance' => true, 'recertify_months' => 12]);
        $quiz   = $this->quiz($course, ['issues_certificate' => true]);

        $service = app(SelfPacedQuizService::class);

        $serials = [];
        foreach (range(1, 2) as $ignored) {
            $attempt = $service->startOrResume($quiz, $this->employee);
            foreach ($quiz->questions as $question) {
                $service->answer($attempt, $question, [0], 3.0);
            }
            $service->finish($attempt);

            $serials[] = $this->employee->trainingCertificates()->first()->serial;
        }

        $this->assertSame(1, $this->employee->trainingCertificates()->count());
        $this->assertSame($serials[0], $serials[1]);
        $this->assertNotNull($this->employee->trainingCertificates()->first()->expires_on);
    }

    public function test_failing_issues_no_certificate(): void
    {
        $quiz = $this->quiz(null, ['issues_certificate' => true]);

        $service = app(SelfPacedQuizService::class);
        $attempt = $service->startOrResume($quiz, $this->employee);
        foreach ($quiz->questions as $question) {
            $service->answer($attempt, $question, [1], 3.0);
        }
        $service->finish($attempt);

        $this->assertSame(0, $this->employee->trainingCertificates()->count());
    }

    /** Serials get read down a phone, so the ambiguous glyphs are excluded. */
    public function test_a_serial_carries_no_ambiguous_characters(): void
    {
        $serial = app(CertificateService::class)->newSerial();

        $this->assertMatchesRegularExpression('/^CERT-\d{4}-[A-Z2-9]{8}$/', $serial);
        $this->assertDoesNotMatchRegularExpression('/[OIL01]/', substr($serial, 10));
    }

    /** A revoked certificate stops printing. */
    public function test_a_revoked_certificate_pdf_is_gone(): void
    {
        $course = $this->course(['is_compliance' => true]);
        $quiz   = $this->quiz($course, ['issues_certificate' => true]);

        $service = app(SelfPacedQuizService::class);
        $attempt = $service->startOrResume($quiz, $this->employee);
        foreach ($quiz->questions as $question) {
            $service->answer($attempt, $question, [0], 3.0);
        }
        $service->finish($attempt);

        $certificate = $this->employee->trainingCertificates()->first();

        app(CertificateService::class)->revoke($certificate);

        $this->asStaff($this->employee)
            ->get(route('training.certificates.pdf', $certificate->id))
            ->assertStatus(410);
    }

    /** A trainee may take their own certificate and nobody else's. */
    public function test_a_trainee_cannot_download_a_colleague_s_certificate(): void
    {
        $course = $this->course(['is_compliance' => true]);
        $quiz   = $this->quiz($course, ['issues_certificate' => true]);

        $service = app(SelfPacedQuizService::class);
        $attempt = $service->startOrResume($quiz, $this->employee);
        foreach ($quiz->questions as $question) {
            $service->answer($attempt, $question, [0], 3.0);
        }
        $service->finish($attempt);

        $certificate = $this->employee->trainingCertificates()->first();

        $colleague = Employee::create([
            'company_id' => $this->company->id,
            'outlet_id'  => $this->outlet->id,
            'name'       => 'Someone Else',
            'email'      => 'else' . uniqid() . '@example.test',
            'is_active'  => true,
        ]);

        $this->asStaff($colleague)
            ->get(route('training.certificates.pdf', $certificate->id))
            ->assertForbidden();
    }

    // ── Column defaults in memory ─────────────────────────────────────────

    /**
     * A course created WITHOUT is_compliance can still have a quiz drafted.
     *
     * The bug: Eloquent does not read DB-side defaults back after an insert,
     * and a cast is not applied to an attribute that was never set — so
     * `is_compliance` read as NULL, draftQuizFor() copied it into the quiz's
     * NOT NULL `issues_certificate`, and the insert died. It hid because the
     * course form always sends the key, so every path a person could click was
     * fine. This creates the course the way the form does not.
     */
    public function test_a_quiz_can_be_drafted_for_a_course_created_without_every_key(): void
    {
        $course = TrainingCourse::create([
            'company_id' => $this->company->id,
            'title'      => 'Minimal',
            'content'    => 'Something to be asked about.',
            // deliberately no is_compliance, status, source_type or minutes
        ]);

        $this->assertFalse($course->is_compliance, 'the model must mirror the column default');
        $this->assertSame('draft', $course->status);
        $this->assertSame('text', $course->source_type);

        $quiz = app(\App\Services\Training\QuizGeneratorService::class)->draftQuizFor($course);

        $this->assertFalse($quiz->issues_certificate);
        $this->assertTrue($quiz->speed_bonus);
        $this->assertDatabaseHas('training_quizzes', ['id' => $quiz->id, 'issues_certificate' => false]);
    }

    /**
     * ...and the same quiz shuffles, without being reloaded first.
     *
     * A null reads as false, so a quiz hosted in the request it was created in
     * would have dealt every player the same order and said nothing about it.
     */
    public function test_a_freshly_created_quiz_still_knows_it_shuffles(): void
    {
        $quiz = TrainingQuiz::create([
            'company_id' => $this->company->id,
            'title'      => 'Fresh',
        ]);

        $this->assertTrue($quiz->shuffle_questions);
        $this->assertTrue($quiz->shuffle_options);
        $this->assertSame(0, $quiz->max_attempts);
        $this->assertSame('draft', $quiz->status);
    }

    // ── Question language ─────────────────────────────────────────────────

    public function test_a_quiz_defaults_to_english_and_can_be_set_to_malay_or_indonesian(): void
    {
        $quiz = $this->quiz();

        $this->assertSame('en', $quiz->language);
        $this->assertSame('English', $quiz->languageLabel());

        $quiz->update(['language' => 'ms']);
        $this->assertSame('Bahasa Malaysia', $quiz->fresh()->languageLabel());

        $quiz->update(['language' => 'id']);
        $this->assertSame('Bahasa Indonesia', $quiz->fresh()->languageLabel());
    }

    /**
     * The true/false wording follows the quiz's language.
     *
     * One constant feeds both the AI prompt and the question editor, because a
     * hand-added "True/False" in an otherwise Malay paper is exactly the
     * inconsistency staff notice and authors do not.
     */
    public function test_true_false_wording_follows_the_language(): void
    {
        $this->assertSame(['True', 'False'], TrainingQuiz::booleanOptionsFor('en'));
        $this->assertSame(['Betul', 'Salah'], TrainingQuiz::booleanOptionsFor('ms'));
        $this->assertSame(['Benar', 'Salah'], TrainingQuiz::booleanOptionsFor('id'));

        // An unknown or missing tag falls back rather than returning nothing —
        // a two-option question with no options cannot be answered at all.
        $this->assertSame(['True', 'False'], TrainingQuiz::booleanOptionsFor(null));
        $this->assertSame(['True', 'False'], TrainingQuiz::booleanOptionsFor('zz'));
    }

    // ── Assignment audiences ──────────────────────────────────────────────

    /**
     * Outlet and section NARROW each other rather than compete.
     *
     * "The kitchen at Bangsar" is the commonest real requirement and neither
     * column expresses it alone, so the pair has to behave as one idea. The
     * case that matters is the near-miss: a Bangsar waiter and a Damansara cook
     * each match one half and must not be caught.
     */
    public function test_an_outlet_and_section_assignment_only_reaches_that_section_at_that_outlet(): void
    {
        $boh = \App\Models\Section::create(['company_id' => $this->company->id, 'name' => 'BOH', 'is_active' => true]);
        $foh = \App\Models\Section::create(['company_id' => $this->company->id, 'name' => 'FOH', 'is_active' => true]);

        $course = $this->course();

        TrainingAssignment::create([
            'company_id'         => $this->company->id,
            'training_course_id' => $course->id,
            'outlet_id'          => $this->outlet->id,
            'section_id'         => $boh->id,
        ]);

        $make = fn (?int $outletId, ?int $sectionId) => Employee::create([
            'company_id' => $this->company->id,
            'outlet_id'  => $outletId,
            'section_id' => $sectionId,
            'name'       => 'Staff ' . uniqid(),
            'is_active'  => true,
        ]);

        $bangsarCook    = $make($this->outlet->id, $boh->id);
        $bangsarWaiter  = $make($this->outlet->id, $foh->id);
        $damansaraCook  = $make($this->otherOutlet->id, $boh->id);

        $lands = fn (Employee $e) => TrainingAssignment::forEmployee($e)->count();

        $this->assertSame(1, $lands($bangsarCook));
        $this->assertSame(0, $lands($bangsarWaiter), 'right branch, wrong section');
        $this->assertSame(0, $lands($damansaraCook), 'right section, wrong branch');
    }

    /** An individual assignment reaches that person and nobody else. */
    public function test_an_individual_assignment_reaches_only_them(): void
    {
        $course = $this->course();

        TrainingAssignment::create([
            'company_id'         => $this->company->id,
            'training_course_id' => $course->id,
            'employee_id'        => $this->employee->id,
        ]);

        $colleague = Employee::create([
            'company_id' => $this->company->id,
            'outlet_id'  => $this->outlet->id,
            'name'       => 'Colleague',
            'is_active'  => true,
        ]);

        $this->assertSame(1, TrainingAssignment::forEmployee($this->employee)->count());
        $this->assertSame(0, TrainingAssignment::forEmployee($colleague)->count());
    }

    /**
     * A quiz assignment requires THAT paper, not every paper on its course.
     *
     * Expanding it back out would undo the only thing naming a quiz buys —
     * a course carries a kitchen set and a floor set, and being told to sit
     * both is not what "do the Malay one" means.
     */
    public function test_a_quiz_assignment_is_cleared_by_passing_that_quiz_alone(): void
    {
        $course = $this->course();
        $mine   = $this->quiz($course, ['title' => 'Mine']);
        $other  = $this->quiz($course, ['title' => 'Somebody else\'s']);

        TrainingAssignment::create([
            'company_id'       => $this->company->id,
            'training_quiz_id' => $mine->id,
            'outlet_id'        => $this->outlet->id,
        ]);

        $service = app(ReportCardService::class);
        $this->assertCount(1, $service->outstanding($this->employee));

        $play = app(SelfPacedQuizService::class);
        $attempt = $play->startOrResume($mine, $this->employee);
        foreach ($mine->questions as $question) {
            $play->answer($attempt, $question, [0], 3.0);
        }
        $play->finish($attempt);

        $this->assertCount(0, $service->outstanding($this->employee));
        $this->assertNotNull($other);
    }

    // ── Section targeting ─────────────────────────────────────────────────

    /**
     * A kitchen quiz does not reach the floor, and an untagged one reaches
     * everybody.
     *
     * The last part is the one worth a test: null means EVERYBODY, not "no
     * section", so a compliance quiz nobody remembered to tag still gets to the
     * whole team. Failing the other way would hide safety material and look
     * like nothing was wrong.
     */
    public function test_a_quiz_tagged_to_a_section_only_reaches_that_section(): void
    {
        $foh = \App\Models\Section::create(['company_id' => $this->company->id, 'name' => 'FOH', 'is_active' => true]);
        $boh = \App\Models\Section::create(['company_id' => $this->company->id, 'name' => 'BOH', 'is_active' => true]);

        $course = $this->course();

        $everyone = $this->quiz($course, ['title' => 'Allergens']);
        $kitchen  = $this->quiz($course, ['title' => 'Method', 'section_id' => $boh->id]);
        $floor    = $this->quiz($course, ['title' => 'Selling it', 'section_id' => $foh->id]);

        $offered = fn (?int $sectionId) => TrainingQuiz::published()
            ->where('training_course_id', $course->id)
            ->forSection($sectionId)
            ->orderBy('id')
            ->pluck('title')
            ->all();

        $this->assertSame(['Allergens', 'Selling it'], $offered($foh->id));
        $this->assertSame(['Allergens', 'Method'], $offered($boh->id));

        // Nobody on this company has a null section today, but the column
        // allows it — and they must still get the untagged material.
        $this->assertSame(['Allergens'], $offered(null));

        $this->assertNotNull($kitchen);
        $this->assertNotNull($everyone);
        $this->assertNotNull($floor);
    }

    public function test_an_untagged_quiz_reports_itself_as_everyones(): void
    {
        $section = \App\Models\Section::create([
            'company_id' => $this->company->id, 'name' => 'BOH', 'is_active' => true,
        ]);

        $this->assertSame('Everyone', $this->quiz()->sectionLabel());
        $this->assertSame('BOH', $this->quiz(null, ['section_id' => $section->id])->sectionLabel());
    }

    // ── Scoring wiring ────────────────────────────────────────────────────

    /** finalise() sums the ROWS, so a re-answer cannot inflate the total. */
    public function test_the_attempt_total_is_summed_from_the_answer_rows(): void
    {
        $quiz    = $this->quiz();
        $service = app(SelfPacedQuizService::class);
        $scoring = app(ScoringService::class);

        $attempt = $service->startOrResume($quiz, $this->employee);
        $service->answer($attempt, $quiz->questions[0], [1], 2.0);
        $service->answer($attempt, $quiz->questions[0], [0], 2.0);

        $scoring->finalise($attempt->fresh('answers'), $quiz);

        $this->assertSame(
            (int) $attempt->fresh()->answers()->sum('points_awarded'),
            $attempt->fresh()->score
        );
    }

    /** Opening a session with no questions is refused, not hosted empty. */
    public function test_a_quiz_with_no_questions_cannot_be_hosted(): void
    {
        $empty = TrainingQuiz::create([
            'company_id' => $this->company->id,
            'title'      => 'Nothing here',
            'status'     => 'published',
        ]);

        $this->expectException(\RuntimeException::class);

        app(LiveSessionService::class)->open($empty, $this->outlet->id, $this->manager->id);
    }

    public function test_two_players_cannot_share_a_nickname_in_one_room(): void
    {
        $quiz     = $this->quiz();
        $sessions = app(LiveSessionService::class);
        $session  = $sessions->open($quiz, $this->outlet->id, $this->manager->id);

        $first  = $sessions->join($session, 'Chef');
        $second = $sessions->join($session, 'Chef');

        // Two people called "Chef" is not an error worth stopping the room for
        // — and crucially the second one must NOT be handed the first one's
        // row, which is what matching anonymous players on their nickname did.
        $this->assertNotSame($first->id, $second->id);
        $this->assertSame('Chef', $first->nickname);
        $this->assertSame('Chef 2', $second->nickname);
    }

    /** A signed-in trainee rejoining keeps their score rather than resetting. */
    public function test_a_trainee_rejoining_gets_their_own_row_back(): void
    {
        $quiz     = $this->quiz();
        $sessions = app(LiveSessionService::class);
        $session  = $sessions->open($quiz, $this->outlet->id, $this->manager->id);

        $first  = $sessions->join($session, 'Aisyah', $this->employee);
        $second = $sessions->join($session, 'Aisyah', $this->employee);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, TrainingSession::find($session->id)->players()->count());
    }

    // ── Penalties and music ───────────────────────────────────────────────

    /**
     * A run that goes badly bottoms out at zero rather than going negative.
     *
     * The answer ROWS keep their true negative values — the report card has to
     * be able to say what a question cost — so this is specifically about the
     * total, which is the number that appears in front of colleagues.
     */
    public function test_a_disastrous_attempt_scores_zero_rather_than_a_negative(): void
    {
        $quiz    = $this->quiz(null, ['wrong_penalty_percent' => 100]);
        $service = app(SelfPacedQuizService::class);

        $attempt = $service->startOrResume($quiz, $this->employee);

        foreach ($quiz->questions as $question) {
            $service->answer($attempt, $question, [1], 5.0);
        }

        $attempt = $service->finish($attempt);

        $this->assertSame(0, (int) $attempt->score);
        $this->assertSame(0, $attempt->correct_count);
        $this->assertTrue($attempt->answers()->where('points_awarded', '<', 0)->exists());
    }

    /** Running out of time is not a guess, and is never charged for. */
    public function test_a_timeout_costs_nothing_even_on_a_penalised_quiz(): void
    {
        $quiz    = $this->quiz(null, ['wrong_penalty_percent' => 50]);
        $service = app(SelfPacedQuizService::class);

        $attempt = $service->startOrResume($quiz, $this->employee);

        $timedOut = $service->answer($attempt, $quiz->questions[0], [], 20.0);
        $guessed  = $service->answer($attempt, $quiz->questions[1], [1], 4.0);

        $this->assertSame(0, (int) $timedOut->points_awarded);
        $this->assertSame(-500, (int) $guessed->points_awarded);
    }

    /**
     * The music URL is rebuilt from an id rather than passed through — it ends
     * up in an iframe src, which is one of the few places a merchant-typed
     * string becomes executable.
     */
    public function test_only_a_youtube_id_survives_into_the_music_embed(): void
    {
        $quiz = $this->quiz();

        $quiz->music_url = 'https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=30s';
        $this->assertStringContainsString('youtube-nocookie.com/embed/dQw4w9WgXcQ', $quiz->musicEmbedUrl());

        $quiz->music_url = 'https://youtu.be/dQw4w9WgXcQ';
        $this->assertStringContainsString('/embed/dQw4w9WgXcQ', $quiz->musicEmbedUrl());

        // A single video only loops when it also names itself as the playlist.
        $this->assertStringContainsString('playlist=dQw4w9WgXcQ', $quiz->musicEmbedUrl());

        $quiz->music_url = 'javascript:alert(1)';
        $this->assertNull($quiz->musicEmbedUrl());

        $quiz->music_url = 'https://example.com/not-youtube';
        $this->assertNull($quiz->musicEmbedUrl());

        $quiz->music_url = '';
        $this->assertNull($quiz->musicEmbedUrl());
    }

    // ── Scheduled challenges ──────────────────────────────────────────────

    /**
     * A challenge is open between its dates and invisible outside them.
     *
     * The two null cases matter as much as the dates: a missing bound reads as
     * "no bound", not as "closed", or a half-filled form would produce a
     * challenge that silently reaches nobody.
     */
    public function test_a_challenge_is_only_open_inside_its_window(): void
    {
        $quiz     = $this->quiz();
        $sessions = app(LiveSessionService::class);

        $open = $sessions->schedule($quiz, null, $this->manager->id, now()->subDay(), now()->addDays(3));
        $soon = $sessions->schedule($quiz, null, $this->manager->id, now()->addDay(), now()->addDays(3));
        $gone = $sessions->schedule($quiz, null, $this->manager->id, now()->subDays(9), now()->subDay());
        $ever = $sessions->schedule($quiz, null, $this->manager->id, null, null);

        $ids = TrainingSession::openNow()->pluck('id')->all();

        $this->assertContains($open->id, $ids);
        $this->assertContains($ever->id, $ids);
        $this->assertNotContains($soon->id, $ids);
        $this->assertNotContains($gone->id, $ids);

        $this->assertTrue($open->isOpen());
        $this->assertTrue($gone->hasClosed());
    }

    /** A scheduled session is not a room: it must never surface as one. */
    public function test_a_challenge_does_not_appear_as_a_live_room(): void
    {
        $quiz     = $this->quiz();
        $sessions = app(LiveSessionService::class);

        $challenge = $sessions->schedule($quiz, null, $this->manager->id, null, now()->addDays(2));

        $this->assertEmpty(TrainingSession::live()->pluck('id')->all());
        $this->assertNull($challenge->pin);
        $this->assertNull($sessions->findByPin((string) $challenge->pin));
    }

    /**
     * One run each, however many attempts the quiz itself allows.
     *
     * A challenge is a competition on a shared board. Practising the same quiz
     * five times and posting the best score is not the same game as everybody
     * else's, so a finished challenge attempt closes the door — the practice
     * route is still there when they want to learn rather than compete.
     */
    public function test_a_challenge_can_only_be_taken_once(): void
    {
        $quiz      = $this->quiz(null, ['max_attempts' => 0]);
        $sessions  = app(LiveSessionService::class);
        $selfPaced = app(SelfPacedQuizService::class);

        $challenge = $sessions->schedule($quiz, null, $this->manager->id, null, now()->addDays(2));

        $attempt = $selfPaced->startOrResume($quiz, $this->employee, $challenge);
        $this->assertSame($challenge->id, $attempt->training_session_id);

        // Interrupted mid-run, they get the same attempt back rather than a new one.
        $selfPaced->answer($attempt, $quiz->questions[0], [0], 3.0);
        $this->assertSame($attempt->id, $selfPaced->startOrResume($quiz, $this->employee, $challenge)->id);

        foreach ($quiz->questions as $question) {
            $selfPaced->answer($attempt, $question, [0], 3.0);
        }
        $selfPaced->finish($attempt);

        $this->expectException(\RuntimeException::class);
        $selfPaced->startOrResume($quiz->fresh(), $this->employee, $challenge->fresh());
    }

    /** The board reads from attempts, and says who is still part-way through. */
    public function test_the_challenge_board_ranks_finished_runs_above_unfinished_ones(): void
    {
        $quiz      = $this->quiz();
        $sessions  = app(LiveSessionService::class);
        $selfPaced = app(SelfPacedQuizService::class);

        $challenge = $sessions->schedule($quiz, null, $this->manager->id, null, now()->addDays(2));

        $other = Employee::create([
            'company_id' => $this->company->id,
            'outlet_id'  => $this->outlet->id,
            'name'       => 'Hakim Yusof',
            'email'      => 'hakim' . uniqid() . '@example.test',
            'is_active'  => true,
        ]);

        // Aisyah finishes. Hakim starts and walks off.
        $mine = $selfPaced->startOrResume($quiz, $this->employee, $challenge);
        foreach ($quiz->questions as $question) {
            $selfPaced->answer($mine, $question, [0], 2.0);
        }
        $selfPaced->finish($mine);

        $selfPaced->startOrResume($quiz, $other, $challenge);

        $standings = $sessions->standings($challenge);

        $this->assertCount(2, $standings);
        $this->assertSame('Aisyah Rahman', $standings[0]['name']);
        $this->assertTrue($standings[0]['finished']);
        $this->assertFalse($standings[1]['finished']);
    }

    /** Options are index-addressed, so a question knows its own answer. */
    public function test_a_question_scores_against_its_own_options(): void
    {
        $question = new TrainingQuestion([
            'type' => 'mcq', 'prompt' => 'Which?',
            'options' => ['a', 'b', 'c'], 'correct' => [1],
        ]);

        $this->assertTrue($question->isCorrect([1]));
        $this->assertFalse($question->isCorrect([0]));
        $this->assertFalse($question->isCorrect([1, 2]));
    }
}
