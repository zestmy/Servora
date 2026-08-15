<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use App\Models\Outlet;
use App\Models\TrainingAssignment;
use App\Models\TrainingCourse;
use App\Models\TrainingPath;
use App\Models\TrainingQuiz;
use App\Models\User;
use App\Services\Training\LiveSessionService;
use App\Services\Training\SelfPacedQuizService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Every Training screen renders, with data in it.
 *
 * A shallow test on purpose, and worth its runtime for exactly that reason: a
 * Blade template is only compiled when something asks for it, so a mistyped
 * variable or a component prop that does not exist is invisible until somebody
 * opens the page — usually the person who was told the feature was finished.
 * Behaviour lives in TrainingModuleTest; this asserts the pages exist and draw.
 *
 * Each screen is rendered with at least one row in it, because an empty state
 * exercises none of the markup that actually loops.
 */
class TrainingScreensRenderTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $outlet;
    private User $manager;
    private Employee $employee;
    private TrainingCourse $course;
    private TrainingQuiz $quiz;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'Render Co', 'slug' => Str::slug('Render Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);

        $this->outlet = Outlet::create([
            'company_id' => $this->company->id, 'name' => 'Main', 'code' => 'MAIN', 'is_active' => true,
        ]);

        foreach ([
            'training.view', 'training.manage', 'training.host',
            'training.assign', 'training.reports', 'training.portal',
        ] as $name) {
            Permission::findOrCreate($name, 'web');
        }

        $this->manager = User::factory()->create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
            'can_view_all_outlets' => true,
        ]);
        $this->manager->companies()->syncWithoutDetaching([$this->company->id]);
        $this->manager->outlets()->sync([$this->outlet->id]);

        setPermissionsTeamId($this->company->id);
        $this->manager->givePermissionTo([
            'training.view', 'training.manage', 'training.host',
            'training.assign', 'training.reports', 'training.portal',
        ]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->employee = Employee::create([
            'company_id' => $this->company->id,
            'outlet_id'  => $this->outlet->id,
            'name'       => 'Aisyah Rahman',
            'email'      => 'render' . uniqid() . '@example.test',
            'is_active'  => true,
        ]);

        $this->course = TrainingCourse::create([
            'company_id'    => $this->company->id,
            'title'         => 'Chiller discipline',
            'summary'       => 'Where the cold chain breaks.',
            'category'      => 'Food safety',
            'content'       => 'Chillers run between 0 and 4 degrees.',
            'status'        => 'published',
            'is_compliance' => true,
        ]);

        $this->quiz = TrainingQuiz::create([
            'company_id'         => $this->company->id,
            'training_course_id' => $this->course->id,
            'title'              => 'Chiller quiz',
            'status'             => 'published',
            'shuffle_questions'  => false,
            'shuffle_options'    => false,
            'issues_certificate' => true,
        ]);

        $this->quiz->questions()->create([
            'type'    => 'mcq',
            'prompt'  => 'What temperature does a chiller hold?',
            'options' => ['0-4°C', '8°C', '12°C', 'Room temperature'],
            'correct' => [0],
            'topic'   => 'Food safety',
            'explanation' => 'Above 4°C the cold chain is broken.',
        ]);

        $path = TrainingPath::create([
            'company_id' => $this->company->id,
            'name'       => 'Front of house induction',
            'status'     => 'published',
        ]);

        $path->items()->create([
            'training_course_id' => $this->course->id,
            'sort_order'         => 0,
        ]);

        // Assigned, or the trainee's path block renders empty — a path only
        // appears to somebody it has been given to. Assigning to the OUTLET
        // rather than to this trainee by name is also the shape the product
        // recommends, so the test exercises the one that matters.
        TrainingAssignment::create([
            'company_id'       => $this->company->id,
            'training_path_id' => $path->id,
            'outlet_id'        => $this->outlet->id,
            'due_on'           => now()->addWeek()->toDateString(),
        ]);

        $this->quiz = $this->quiz->fresh('questions');
    }

    // ── Admin ─────────────────────────────────────────────────────────────

    public function test_the_course_library_renders(): void
    {
        Livewire::actingAs($this->manager)
            ->test(\App\Livewire\Training\Courses::class)
            ->assertOk()
            ->assertSee('Chiller discipline')
            ->assertSee('Compliance');
    }

    public function test_the_course_form_renders_for_a_new_course_and_an_existing_one(): void
    {
        Livewire::actingAs($this->manager)
            ->test(\App\Livewire\Training\CourseForm::class)
            ->assertOk()
            ->assertSee('Where the material comes from');

        Livewire::actingAs($this->manager)
            ->test(\App\Livewire\Training\CourseForm::class, ['id' => $this->course->id])
            ->assertOk()
            ->assertSet('title', 'Chiller discipline')
            ->assertSet('isCompliance', true);
    }

    public function test_the_quiz_list_renders(): void
    {
        Livewire::actingAs($this->manager)
            ->test(\App\Livewire\Training\Quizzes::class)
            ->assertOk()
            ->assertSee('Chiller quiz');
    }

    public function test_the_quiz_builder_renders_with_its_question(): void
    {
        Livewire::actingAs($this->manager)
            ->test(\App\Livewire\Training\QuizBuilder::class, ['id' => $this->quiz->id])
            ->assertOk()
            ->assertSee('What temperature does a chiller hold?')
            ->assertSee('Above 4°C the cold chain is broken.');
    }

    /** The editor's own state changes, which is where the option list reshapes. */
    public function test_the_question_editor_reshapes_for_true_false(): void
    {
        Livewire::actingAs($this->manager)
            ->test(\App\Livewire\Training\QuizBuilder::class, ['id' => $this->quiz->id])
            ->call('newQuestion')
            ->assertSet('editingId', 0)
            ->set('qType', 'true_false')
            ->assertSet('qOptions', ['True', 'False'])
            ->assertOk();
    }

    /**
     * A second language is the SAME quiz, in other words.
     *
     * Reported first as "when I generated the 2nd language the first one
     * disappears" — it was overwriting the questions — and the fix is not a
     * second quiz either. Two quizzes would be two rows everywhere it counts:
     * two sets of attempts, two leaderboard lines, two assignments to clear.
     * The questions and the answer key are shared; only the words differ.
     */
    public function test_a_quiz_can_be_translated_and_still_counts_once(): void
    {
        \App\Models\AppSetting::set('openrouter_api_key', 'test-key');

        $question = $this->quiz->questions->first();

        \Illuminate\Support\Facades\Http::fake([
            'openrouter.ai/*' => \Illuminate\Support\Facades\Http::response([
                'choices' => [['message' => ['content' => json_encode(['questions' => [[
                    'id'          => $question->id,
                    'prompt'      => 'Berapa suhu peti sejuk?',
                    'options'     => ['0-4°C', '8°C', '12°C', 'Suhu bilik'],
                    'explanation' => 'Melebihi 4°C rantaian sejuk terputus.',
                ]]])]]],
            ]),
        ]);

        Livewire::actingAs($this->manager)
            ->test(\App\Livewire\Training\QuizBuilder::class, ['id' => $this->quiz->id])
            ->set('translateTo', 'ms')
            ->call('translate')
            ->assertOk();

        // Still one quiz. The English wording is untouched.
        $this->assertSame(1, TrainingQuiz::count());
        $this->assertSame('What temperature does a chiller hold?', $question->fresh()->prompt);

        $this->assertSame(
            ['en' => 'English', 'ms' => 'Bahasa Malaysia'],
            $this->quiz->fresh()->availableLanguages(),
        );

        // Same answer key, different words — which is the whole point.
        $this->assertSame('Berapa suhu peti sejuk?', $question->fresh()->promptIn('ms'));
        $this->assertSame([0], array_map('intval', (array) $question->fresh()->correct));
    }

    /**
     * A half-translated quiz is not offered in that language.
     *
     * Somebody who picked Malay because they read Malay would be dropped into
     * English at question five, at speed, for points, having already committed
     * to the attempt. A partial translation is the author's problem to finish,
     * not the trainee's to discover.
     */
    public function test_a_partly_translated_quiz_offers_only_its_own_language(): void
    {
        $this->quiz->questions()->create([
            'type'    => 'mcq',
            'prompt'  => 'Which shelf holds raw chicken?',
            'options' => ['Bottom', 'Top', 'Middle', 'Any'],
            'correct' => [0],
        ]);

        \App\Models\TrainingQuestionTranslation::create([
            'training_question_id' => $this->quiz->questions()->first()->id,
            'language'             => 'ms',
            'prompt'               => 'Berapa suhu peti sejuk?',
            'options'              => ['0-4°C', '8°C', '12°C', 'Suhu bilik'],
        ]);

        $quiz = $this->quiz->fresh();

        $this->assertSame(['en' => 'English'], $quiz->availableLanguages());
        $this->assertFalse($quiz->isMultilingual());
        $this->assertSame(['ms' => 1], $quiz->translationCoverage());
    }

    /** The chosen language is recorded on the attempt, for the reporting. */
    public function test_the_language_read_is_written_onto_the_attempt(): void
    {
        \App\Models\TrainingQuestionTranslation::create([
            'training_question_id' => $this->quiz->questions()->first()->id,
            'language'             => 'ms',
            'prompt'               => 'Berapa suhu peti sejuk?',
            'options'              => ['0-4°C', '8°C', '12°C', 'Suhu bilik'],
        ]);

        $this->asStaff();

        Livewire::test(\App\Livewire\Staff\Training\QuizPlay::class, ['id' => $this->quiz->id])
            ->assertOk()
            ->assertSee('Bahasa Malaysia')
            ->set('language', 'ms')
            ->call('begin')
            ->assertOk()
            // The Malay wording, and the same options behind it.
            ->assertSee('Berapa suhu peti sejuk?');

        $this->assertSame('ms', \App\Models\TrainingAttempt::latest('id')->first()->language);
    }

    /** A language nobody translated cannot be forced from the browser. */
    public function test_an_unoffered_language_falls_back_to_the_original(): void
    {
        $this->asStaff();

        Livewire::test(\App\Livewire\Staff\Training\QuizPlay::class, ['id' => $this->quiz->id])
            ->set('language', 'id')
            ->call('begin')
            ->assertSet('language', 'en')
            ->assertSee('What temperature does a chiller hold?');
    }

    public function test_the_sessions_screen_renders(): void
    {
        Livewire::actingAs($this->manager)
            ->test(\App\Livewire\Training\Sessions::class)
            ->assertOk()
            ->assertSee('Start a session');
    }

    /** Scheduling from the same form, and the board it produces. */
    public function test_a_challenge_can_be_scheduled_and_watched(): void
    {
        Livewire::actingAs($this->manager)
            ->test(\App\Livewire\Training\Sessions::class)
            ->set('kind', 'challenge')
            ->set('quizId', $this->quiz->id)
            ->set('outletId', $this->outlet->id)
            ->set('sessionName', 'Allergen week')
            ->set('closesAt', now()->addDays(4)->toDateString())
            ->call('host')
            ->assertOk()
            ->assertSee('Allergen week');

        $challenge = \App\Models\TrainingSession::where('mode', 'scheduled')->firstOrFail();

        // End of the chosen day, not the start of it: "closes Friday" has to
        // include Friday.
        $this->assertTrue($challenge->closes_at->isSameDay(now()->addDays(4)));
        $this->assertSame(23, $challenge->closes_at->hour);

        Livewire::actingAs($this->manager)
            ->test(\App\Livewire\Training\ChallengeBoard::class, ['id' => $challenge->id])
            ->assertOk()
            ->assertSee('Allergen week')
            ->assertSee('Nobody has taken it yet');
    }

    /** Every state of the host console, because they are four different views. */
    public function test_the_host_console_renders_in_every_state(): void
    {
        $sessions = app(LiveSessionService::class);
        $session  = $sessions->open($this->quiz, $this->outlet->id, $this->manager->id, 'Tuesday briefing');
        $sessions->join($session, 'Aisyah', $this->employee);

        // Lobby — the PIN is the whole screen.
        Livewire::actingAs($this->manager)
            ->test(\App\Livewire\Training\LiveHost::class, ['id' => $session->id])
            ->assertOk()
            ->assertSee($session->pin);

        $sessions->start($session->fresh());

        Livewire::actingAs($this->manager)
            ->test(\App\Livewire\Training\LiveHost::class, ['id' => $session->id])
            ->assertOk()
            ->assertSee('What temperature does a chiller hold?');

        $sessions->reveal($session->fresh());

        // Reveal draws the per-option tally, which the live view does not.
        Livewire::actingAs($this->manager)
            ->test(\App\Livewire\Training\LiveHost::class, ['id' => $session->id])
            ->assertOk()
            ->assertSee('Above 4°C the cold chain is broken.');

        $sessions->end($session->fresh());

        Livewire::actingAs($this->manager)
            ->test(\App\Livewire\Training\LiveHost::class, ['id' => $session->id])
            ->assertOk()
            ->assertSee('Final scores')
            ->assertSee('Aisyah');
    }

    public function test_the_assignments_screen_renders_with_its_modal_open(): void
    {
        Livewire::actingAs($this->manager)
            ->test(\App\Livewire\Training\Assignments::class)
            ->assertOk()
            ->call('openCreate')
            ->assertSet('showModal', true)
            ->assertSee('New assignment')
            ->assertSee('Chiller discipline')
            ->assertOk();
    }

    public function test_the_leaderboard_renders(): void
    {
        Livewire::actingAs($this->manager)
            ->test(\App\Livewire\Training\Leaderboard::class)
            ->assertOk()
            ->assertSee('Leaderboard');
    }

    public function test_the_report_cards_screen_renders_a_selected_employee(): void
    {
        // An attempt, so the card has something to draw rather than an empty state.
        $service = app(SelfPacedQuizService::class);
        $attempt = $service->startOrResume($this->quiz, $this->employee);
        $service->answer($attempt, $this->quiz->questions->first(), [0], 3.0);
        $service->finish($attempt);

        Livewire::actingAs($this->manager)
            ->test(\App\Livewire\Training\ReportCards::class)
            ->assertOk()
            ->assertSee('Aisyah Rahman')
            ->call('select', $this->employee->id)
            ->assertOk()
            ->assertSee('What to work on')
            ->assertSee('Recent attempts');
    }

    public function test_the_paths_screen_renders_with_its_contents(): void
    {
        Livewire::actingAs($this->manager)
            ->test(\App\Livewire\Training\Paths::class)
            ->assertOk()
            ->assertSee('Front of house induction')
            ->assertSee('Chiller discipline');
    }

    public function test_the_certificates_screen_renders_in_every_filter(): void
    {
        $component = Livewire::actingAs($this->manager)
            ->test(\App\Livewire\Training\Certificates::class)
            ->assertOk();

        foreach (['all', 'valid', 'expiring', 'expired', 'revoked'] as $filter) {
            $component->set('filter', $filter)->assertOk();
        }
    }

    // ── Staff app ─────────────────────────────────────────────────────────

    private function asStaff(): static
    {
        // The staff apps are a PIN session, not a guard — see StaffSession.
        // Via email, not PIN: most staff have no PIN, and the session validates
        // against whichever credential opened it. See TrainingModuleTest.
        app(\App\Services\Staff\StaffSession::class)->signIn($this->employee, 'email');

        return $this;
    }

    public function test_the_staff_course_list_renders(): void
    {
        $this->asStaff();

        Livewire::test(\App\Livewire\Staff\Training\Index::class)
            ->assertOk()
            ->assertSee('Chiller discipline')
            ->assertSee('Front of house induction');
    }

    /**
     * The public front door: what someone sees a second after scanning the QR,
     * before they have proved who they are.
     */
    public function test_the_public_quiz_link_introduces_the_quiz_without_a_pin(): void
    {
        // No asStaff() — this is the whole point of the screen. The company
        // comes from the subdomain, which is what the middleware puts in the
        // session on a real request.
        session(['subdomain_company_id' => $this->company->id]);

        Livewire::test(\App\Livewire\Staff\Training\QuizGate::class, ['token' => $this->quiz->shareToken()])
            ->assertOk()
            ->assertSee('Chiller quiz')
            ->assertSee('Start — enter your PIN', escape: false)
            // Nothing about the questions themselves leaks out here.
            ->assertDontSee('What temperature does a chiller hold?');
    }

    /** A draft's link is dead, so a poster can be printed before the paper is. */
    public function test_an_unpublished_quiz_has_no_working_link(): void
    {
        session(['subdomain_company_id' => $this->company->id]);

        $token = $this->quiz->shareToken();
        $this->quiz->update(['status' => 'draft']);

        Livewire::test(\App\Livewire\Staff\Training\QuizGate::class, ['token' => $token])
            ->assertOk()
            ->assertSee('This link has expired')
            ->assertDontSee('Chiller quiz');
    }

    /** A made-up token is the same dead end, not an error. */
    public function test_an_unknown_token_lands_on_the_expired_page(): void
    {
        session(['subdomain_company_id' => $this->company->id]);

        Livewire::test(\App\Livewire\Staff\Training\QuizGate::class, ['token' => 'notarealtoken123'])
            ->assertOk()
            ->assertSee('This link has expired');
    }

    /**
     * A quiz on a draft course is still offered to staff.
     *
     * It was invisible: a quiz was reachable only through its course, and the
     * course screen refuses a draft — so a published quiz could reach nobody
     * at all. Reported as "I need the quiz to be available in Staff Portal".
     */
    public function test_a_quiz_whose_course_is_a_draft_is_still_listed(): void
    {
        $this->asStaff();

        // While the course is published, the quiz is reached through it and is
        // not listed twice.
        Livewire::test(\App\Livewire\Staff\Training\Index::class)
            ->assertOk()
            ->assertSee('Chiller discipline')
            ->assertViewHas('quizzes', fn ($quizzes) => $quizzes->isEmpty());

        $this->course->update(['status' => 'draft']);

        Livewire::test(\App\Livewire\Staff\Training\Index::class)
            ->assertOk()
            ->assertSee('Chiller quiz')
            ->assertDontSee('Nothing published yet')
            ->assertViewHas('quizzes', fn ($quizzes) => $quizzes->count() === 1);
    }

    public function test_the_staff_course_page_renders(): void
    {
        $this->asStaff();

        Livewire::test(\App\Livewire\Staff\Training\CourseView::class, ['id' => $this->course->id])
            ->assertOk()
            ->assertSee('Chillers run between 0 and 4 degrees.')
            ->assertSee('Chiller quiz');
    }

    /**
     * The page offers the quizzes for the reader's SECTION, not simply the
     * first one on the course.
     *
     * This is the case that broke the old `quizzes->first()`: a course now
     * carries a kitchen questionnaire beside a floor one, and taking the first
     * would hand a waiter the chef's quiz because it had the lower id.
     */
    public function test_the_staff_course_page_offers_only_this_person_s_section(): void
    {
        $foh = \App\Models\Section::create(['company_id' => $this->company->id, 'name' => 'FOH', 'is_active' => true]);
        $boh = \App\Models\Section::create(['company_id' => $this->company->id, 'name' => 'BOH', 'is_active' => true]);

        // The existing quiz is untagged, so it belongs to everybody.
        $this->quiz->update(['title' => 'Everyone quiz']);

        foreach ([['Kitchen quiz', $boh->id], ['Floor quiz', $foh->id]] as [$title, $sectionId]) {
            $paper = TrainingQuiz::create([
                'company_id'         => $this->company->id,
                'training_course_id' => $this->course->id,
                'title'              => $title,
                'status'             => 'published',
            ]);

            $paper->sections()->sync([$sectionId]);

            $paper->questions()->create([
                'type' => 'mcq', 'prompt' => $title . '?',
                'options' => ['a', 'b'], 'correct' => [0],
            ]);
        }

        $this->employee->update(['section_id' => $foh->id]);
        $this->asStaff();

        Livewire::test(\App\Livewire\Staff\Training\CourseView::class, ['id' => $this->course->id])
            ->assertOk()
            ->assertSee('Everyone quiz')
            ->assertSee('Floor quiz')
            ->assertDontSee('Kitchen quiz');
    }

    public function test_playing_a_quiz_renders_the_question_then_the_result(): void
    {
        $this->asStaff();

        $component = Livewire::test(\App\Livewire\Staff\Training\QuizPlay::class, ['id' => $this->quiz->id])
            ->assertOk()
            // The start screen first: nothing exists until Start is pressed, so
            // opening a quiz and backing out costs nobody an attempt.
            ->assertSee('Chiller quiz')
            ->assertDontSee('What temperature does a chiller hold?')
            ->call('begin')
            ->assertSet('started', true)
            ->assertSee('What temperature does a chiller hold?');

        $component->call('choose', 0, false)
            ->call('submit')
            ->assertSet('lastCorrect', true)
            ->assertSee('Above 4°C the cold chain is broken.')
            ->call('nextQuestion')
            ->assertSet('finished', true)
            ->assertSee('Passed')
            ->assertOk();
    }

    /**
     * The result page must not offer a course the reader cannot open.
     *
     * A quiz can be published while its course is still a draft — the menu
     * knowledge paper was in exactly that state — and the staff course screen
     * correctly refuses a draft. The "Back to the course" button was therefore
     * a guaranteed 404, handed to somebody who had just finished a quiz.
     */
    public function test_the_result_hides_the_course_link_when_the_course_is_a_draft(): void
    {
        $this->asStaff();

        // Played through rather than scored behind the screen's back: the
        // result is a state of THIS component, and a fresh mount would simply
        // start a new attempt at a one-question quiz.
        $play = fn () => Livewire::test(\App\Livewire\Staff\Training\QuizPlay::class, ['id' => $this->quiz->id])
            ->call('begin')
            ->call('choose', 0, false)
            ->call('submit')
            ->call('nextQuestion');

        // Published course: the link is offered.
        $play()->assertOk()->assertSee('Back to the course');

        $this->course->update(['status' => 'draft']);

        $play()->assertOk()
            ->assertDontSee('Back to the course')
            // The rest of the result is untouched — only the dead link goes.
            ->assertSee('All training');
    }

    /**
     * The form SHOWS the cover it already has.
     *
     * Reported as "I added a cover image and it did not indicate anything was
     * uploaded" — and it was right: the upload worked and the staff app drew
     * the picture, but the edit form was a bare file input that mentioned
     * neither the new file nor the one already on the record.
     */
    public function test_the_course_form_shows_and_can_clear_an_existing_cover(): void
    {
        $this->course->update(['cover_path' => 'training/covers/example.jpg']);

        $form = Livewire::actingAs($this->manager)
            ->test(\App\Livewire\Training\CourseForm::class, ['id' => $this->course->id])
            ->assertOk()
            ->assertSet('coverPath', 'training/covers/example.jpg')
            ->assertSee('Current cover')
            ->assertSee('training/covers/example.jpg');

        // Removing does not touch the stored file until the form is saved: a
        // course edited and abandoned must come back with its picture.
        $form->call('removeCover')
            ->assertSet('coverPath', null)
            ->assertDontSee('Current cover');

        $this->assertSame('training/covers/example.jpg', $this->course->fresh()->cover_path);

        $form->call('save');

        $this->assertNull($this->course->fresh()->cover_path);
    }

    /**
     * The board moves between questions, on the screen and not just in a
     * service.
     *
     * standingOnQuiz() is unit-tested elsewhere; what this pins is the wiring
     * that makes it visible — the previous rank being carried across a request
     * so the arrow can be drawn, and the block being drawn at all.
     *
     * THE BLOCK IS HIDDEN WHEN NOBODY ELSE HAS TAKEN THE QUIZ, which is a real
     * behaviour rather than a bug and the likeliest reason somebody looks for
     * this and does not find it: "1st of 1" is an empty room dressed as a
     * leaderboard. Both halves are asserted.
     */
    public function test_the_standing_moves_between_questions(): void
    {
        // A second question, so there is a "between" at all.
        $this->quiz->questions()->create([
            'type' => 'mcq', 'prompt' => 'Which shelf holds raw chicken?',
            'options' => ['Bottom', 'Top'], 'correct' => [0], 'sort_order' => 2,
        ]);

        $this->asStaff();

        // Nobody else has taken it: no board, because there is no board.
        Livewire::test(\App\Livewire\Staff\Training\QuizPlay::class, ['id' => $this->quiz->id])
            ->call('begin')
            ->call('choose', 0, false)
            ->call('submit')
            ->assertOk()
            ->assertDontSee('of 1');

        // A rival who scored on the first question only.
        $rival = Employee::create([
            'company_id' => $this->company->id,
            'outlet_id'  => $this->outlet->id,
            'name'       => 'Hakim Yusof',
            'email'      => 'hakim' . uniqid() . '@example.test',
            'is_active'  => true,
        ]);

        $service = app(SelfPacedQuizService::class);
        $theirs  = $service->startOrResume($this->quiz->fresh('questions'), $rival);

        foreach ($this->quiz->fresh('questions')->questions as $i => $question) {
            $service->answer($theirs, $question, $i === 0 ? [0] : [1], 4.0);
        }
        $service->finish($theirs);

        // Fresh run for our employee, now with somebody to chase.
        \App\Models\TrainingAttempt::where('employee_id', $this->employee->id)->delete();

        $play = Livewire::test(\App\Livewire\Staff\Training\QuizPlay::class, ['id' => $this->quiz->id])
            ->call('begin');

        // Question one, answered wrongly: behind the rival, and no arrow yet
        // because there is no previous position to compare with.
        $play->call('choose', 1, false)->call('submit')
            ->assertSet('standing.rank', 2)
            ->assertSet('standing.of', 2)
            ->assertSet('standingBefore', null)
            ->assertSee('of 2');

        // Question two, answered correctly and fast enough to go past them.
        $play->call('nextQuestion')
            ->call('choose', 0, false)
            ->call('submit')
            ->assertSet('standing.rank', 1)
            ->assertSet('standingBefore', 2)
            ->assertSee('up 1');
    }

    /**
     * The three things the backing music depends on, asserted in the markup.
     *
     * Audio cannot be tested from PHP — but each of the two reported music
     * bugs was a STRUCTURAL property of the rendered page, and those can be:
     *
     * 1. The Start button dispatches `start-music`. Playback has to begin
     *    inside the tap, because a browser refuses it outside a user gesture.
     * 2. The effects component is the FIRST CHILD of both the start screen and
     *    the question screen. That is what lets Livewire's morph carry it from
     *    one to the other instead of tearing it down — a torn-down component
     *    takes the player with it, which is "the music stops when I move to the
     *    next question".
     * 3. There is NO player element in the markup at all. It is created in
     *    JavaScript and appended to <body>, outside anything Livewire morphs.
     *    A `quiz-music-player` div appearing in a Blade template again would
     *    reintroduce bug 2 exactly.
     */
    public function test_the_quiz_screens_keep_the_music_wiring_intact(): void
    {
        $this->quiz->update(['music_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ']);

        $this->asStaff();

        $start = Livewire::test(\App\Livewire\Staff\Training\QuizPlay::class, ['id' => $this->quiz->id])
            ->assertOk()
            ->assertSee('start-music', escape: false)
            ->assertSee('quizFx(', escape: false)
            // The id appears in the script that CREATES the element, which is
            // the whole point; what must never come back is a Blade-rendered
            // element carrying it.
            ->assertDontSee('id="quiz-music-player"', escape: false);

        $question = $start->call('begin')->assertOk()
            ->assertSee('quizFx(', escape: false)
            ->assertDontSee('id="quiz-music-player"', escape: false);

        // First child on both, which is the property the morph relies on.
        foreach ([$start->html(), $question->html()] as $html) {
            $body = preg_replace('/<!--.*?-->/s', '', $html);

            $this->assertMatchesRegularExpression(
                '~<div[^>]*>\s*<div x-data="quizFx\(~',
                $body,
                'quiz-fx must be the first child, or the morph tears the player down between screens.',
            );
        }
    }

    /**
     * A translation can be corrected by hand.
     *
     * It is machine-written, and until now the only remedy for a bad Malay
     * rendering of a safety question was to translate the whole paper again
     * and hope. The words are editable; the ANSWER KEY is not, because it is a
     * list of positions into the option array and a translation that could
     * change it would be a second answer key.
     */
    public function test_a_translation_can_be_edited_without_touching_the_answer_key(): void
    {
        $question = $this->quiz->questions->first();

        \App\Models\TrainingQuestionTranslation::create([
            'training_question_id' => $question->id,
            'language'             => 'ms',
            'prompt'               => 'Suhu peti sejuk?',
            'options'              => ['0-4°C', '8°C', '12°C', 'Suhu bilik'],
        ]);

        $builder = Livewire::actingAs($this->manager)
            ->test(\App\Livewire\Training\QuizBuilder::class, ['id' => $this->quiz->id])
            ->call('editQuestion', $question->id)
            ->assertSet('qPrompt', 'What temperature does a chiller hold?')
            // Switching loads the translated wording.
            ->set('editingLanguage', 'ms')
            ->assertSet('qPrompt', 'Suhu peti sejuk?');

        $builder->set('qPrompt', 'Berapakah suhu peti sejuk?')
            ->call('saveQuestion')
            ->assertOk();

        $translation = $question->translations()->where('language', 'ms')->first();

        $this->assertSame('Berapakah suhu peti sejuk?', $translation->prompt);
        // Reviewed by a person now, whatever wrote it first.
        $this->assertFalse($translation->machine_translated);

        // The original and its key are untouched.
        $this->assertSame('What temperature does a chiller hold?', $question->fresh()->prompt);
        $this->assertSame([0], array_map('intval', (array) $question->fresh()->correct));
    }

    /** A translation may not change how many options there are. */
    public function test_a_translation_cannot_change_the_option_count(): void
    {
        $question = $this->quiz->questions->first();

        \App\Models\TrainingQuestionTranslation::create([
            'training_question_id' => $question->id,
            'language'             => 'ms',
            'prompt'               => 'Suhu peti sejuk?',
            'options'              => ['0-4°C', '8°C', '12°C', 'Suhu bilik'],
        ]);

        Livewire::actingAs($this->manager)
            ->test(\App\Livewire\Training\QuizBuilder::class, ['id' => $this->quiz->id])
            ->call('editQuestion', $question->id)
            ->set('editingLanguage', 'ms')
            // One option emptied — the answer key would land on the wrong word.
            ->set('qOptions', ['0-4°C', '8°C', '', ''])
            ->call('saveQuestion')
            ->assertHasErrors('qOptions');

        $this->assertCount(4, $question->translations()->where('language', 'ms')->first()->optionList());
    }

    /** The board can be narrowed to one quiz. */
    public function test_the_staff_board_filters_by_quiz(): void
    {
        $other = TrainingQuiz::create([
            'company_id'         => $this->company->id,
            'training_course_id' => $this->course->id,
            'title'              => 'Allergen quiz',
            'status'             => 'published',
        ]);

        $other->questions()->create([
            'type' => 'mcq', 'prompt' => 'Which is a nut?',
            'options' => ['Almond', 'Rice'], 'correct' => [0],
        ]);

        $this->asStaff();

        $service = app(SelfPacedQuizService::class);
        $attempt = $service->startOrResume($this->quiz, $this->employee);
        foreach ($this->quiz->questions as $question) {
            $service->answer($attempt, $question, [0], 3.0);
        }
        $service->finish($attempt);

        // Everything: they are on the board.
        Livewire::test(\App\Livewire\Staff\Training\Leaderboard::class)
            ->assertOk()
            ->assertSee('Allergen quiz')
            ->assertSee('Every quiz')
            ->assertViewHas('board', fn ($board) => $board->count() === 1);

        // Filtered to the quiz they have NOT taken: nobody.
        Livewire::test(\App\Livewire\Staff\Training\Leaderboard::class)
            ->set('quizId', (string) $other->id)
            ->assertOk()
            ->assertViewHas('board', fn ($board) => $board->isEmpty())
            // And the "not started" list is hidden, because it would be a
            // different claim under the same heading.
            ->assertViewHas('notStarted', fn ($rows) => $rows->isEmpty());
    }

    /**
     * The quiz filter appears even when there is only one quiz.
     *
     * It was hidden below two, on the reasoning that a one-option filter is
     * redundant — true, and beside the point: the company this was built for
     * has one live quiz, so the control never appeared and the feature read as
     * missing. A redundant dropdown costs a line; an invisible one costs a
     * conversation.
     */
    public function test_the_quiz_filter_shows_with_a_single_quiz(): void
    {
        $this->asStaff();

        Livewire::test(\App\Livewire\Staff\Training\Leaderboard::class)
            ->assertOk()
            ->assertViewHas('quizzes', fn ($quizzes) => $quizzes->count() === 1)
            ->assertSee('Every quiz')
            ->assertSee('Chiller quiz');
    }

    /** The wall of certificates, and its empty state. */
    public function test_the_staff_certificates_screen_renders(): void
    {
        $this->asStaff();

        Livewire::test(\App\Livewire\Staff\Training\MyCertificates::class)
            ->assertOk()
            ->assertSee('No certificates yet');

        \App\Models\TrainingCertificate::create([
            'company_id'         => $this->company->id,
            'employee_id'        => $this->employee->id,
            'training_course_id' => $this->course->id,
            'title'              => 'Chiller discipline',
            'recipient_name'     => $this->employee->name,
            'serial'             => 'CERT-2026-TESTONLY',
            'issued_at'          => now(),
        ]);

        Livewire::test(\App\Livewire\Staff\Training\MyCertificates::class)
            ->assertOk()
            ->assertSee('Chiller discipline')
            ->assertSee('CERT-2026-TESTONLY')
            ->assertDontSee('No certificates yet');
    }

    public function test_the_progress_screen_renders(): void
    {
        $this->asStaff();

        Livewire::test(\App\Livewire\Staff\Training\MyProgress::class)
            ->assertOk()
            ->assertSee('My progress')
            // Not the board: that is its own tab now, and the same numbers in
            // two places is how the two start disagreeing.
            ->assertSee('Aisyah Rahman');
    }

    /**
     * The Staff Portal home screen — literally the app's front door, so a
     * failure here is every staff member seeing a 500 on open.
     *
     * Rendered for somebody with NOTHING: no attempts, no assignments, no
     * clock event, no rank. That is the state of every employee on the day
     * this ships, and a dashboard that only works once there is data to show
     * is a dashboard nobody ever sees working.
     */
    public function test_the_staff_home_screen_renders_for_somebody_with_no_history(): void
    {
        $this->asStaff();

        Livewire::test(\App\Livewire\Staff\Home::class)
            ->assertOk()
            ->assertSee('Not clocked in')
            ->assertSee('Clock in')
            ->assertSee('This month')
            // The empty-state line, not a fabricated rank.
            ->assertSee('Nothing yet this month');
    }

    /** ...and again once they have a score and something outstanding. */
    public function test_the_staff_home_screen_renders_with_data(): void
    {
        TrainingAssignment::create([
            'company_id'         => $this->company->id,
            'training_course_id' => $this->course->id,
            'outlet_id'          => $this->outlet->id,
            'due_on'             => now()->subDay()->toDateString(),
        ]);

        // Answered WRONG on purpose. Passing would clear the assignment — that
        // is what "outstanding" means and it is tested elsewhere — and this
        // screen needs the case where somebody has a score AND still owes
        // something, which is the state most staff are actually in.
        $service = app(SelfPacedQuizService::class);
        $attempt = $service->startOrResume($this->quiz, $this->employee);
        $service->answer($attempt, $this->quiz->questions->first(), [1], 3.0);
        $service->finish($attempt);

        $this->asStaff();

        Livewire::test(\App\Livewire\Staff\Home::class)
            ->assertOk()
            ->assertSee('To do')
            ->assertSee('Overdue')
            ->assertSee('Top of')
            ->assertSee('Aisyah Rahman');
    }

    /**
     * The board — which the app also links to prominently, so a failure here
     * is not one screen being wrong.
     */
    public function test_the_leaderboard_renders_with_and_without_a_score(): void
    {
        $this->asStaff();

        // Nothing taken yet. It has to say so rather than invent a rank.
        Livewire::test(\App\Livewire\Staff\Training\Leaderboard::class)
            ->assertOk()
            ->assertSee('Leaderboard')
            ->assertSee('No score yet');

        $service = app(SelfPacedQuizService::class);
        $attempt = $service->startOrResume($this->quiz, $this->employee);
        $service->answer($attempt, $this->quiz->questions->first(), [0], 3.0);
        $service->finish($attempt);

        Livewire::test(\App\Livewire\Staff\Training\Leaderboard::class)
            ->assertOk()
            ->assertSee('Aisyah Rahman')
            ->assertSee('accuracy')
            // Both scopes draw; the branch board is the default.
            ->set('scope', 'company')
            ->assertOk()
            ->assertSee('Aisyah Rahman');
    }

    public function test_the_live_join_screen_renders_and_a_bad_pin_is_reported(): void
    {
        $this->asStaff();

        Livewire::test(\App\Livewire\Staff\Training\LivePlay::class)
            ->assertOk()
            ->assertSee('Join a live session')
            ->set('pin', '000000')
            ->call('join')
            ->assertSee('No live session with that PIN')
            ->assertOk();
    }

    public function test_a_player_screen_renders_through_a_round(): void
    {
        $sessions = app(LiveSessionService::class);
        $session  = $sessions->open($this->quiz, $this->outlet->id, $this->manager->id);

        $this->asStaff();

        $component = Livewire::test(\App\Livewire\Staff\Training\LivePlay::class)
            ->set('pin', $session->pin)
            ->set('nickname', 'Aisyah')
            ->call('join')
            ->assertOk()
            // Not "You're in": assertSee() escapes what it is given, and literal
            // template text is not escaped on the way out, so the apostrophe
            // never matches. Asserting on the sentence below it says the same
            // thing without depending on that.
            ->assertSee('Waiting for the host to start');

        $sessions->start($session->fresh());

        $component->call('heartbeat')
            ->assertOk()
            ->assertSee('What temperature does a chiller hold?')
            ->call('pick', 0, false)
            ->assertOk()
            ->assertSee('Answer in');

        $sessions->end($session->fresh());

        $component->call('heartbeat')->assertOk()->assertSee('Top of the room');
    }

    // ── Routes ────────────────────────────────────────────────────────────

    /** The gates on the routes themselves, not just the components behind them. */
    public function test_every_training_route_opens_for_a_manager_who_holds_the_abilities(): void
    {
        $this->actingAs($this->manager);

        foreach ([
            'training.courses', 'training.quizzes', 'training.live',
            'training.paths', 'training.assignments', 'training.leaderboard',
            'training.report-cards', 'training.certificates',
        ] as $name) {
            $this->get(route($name))->assertOk();
        }
    }

    public function test_a_user_without_the_abilities_is_refused(): void
    {
        $outsider = User::factory()->create([
            'company_id' => $this->company->id, 'outlet_id' => $this->outlet->id,
        ]);
        $outsider->companies()->syncWithoutDetaching([$this->company->id]);
        $outsider->outlets()->sync([$this->outlet->id]);

        $this->actingAs($outsider)->get(route('training.courses'))->assertForbidden();
        $this->actingAs($outsider)->get(route('training.report-cards'))->assertForbidden();
    }
}
