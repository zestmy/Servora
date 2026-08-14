<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\LmsUser;
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
    private LmsUser $trainee;
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

        $this->trainee = LmsUser::create([
            'company_id' => $this->company->id,
            'outlet_id'  => $this->outlet->id,
            'name'       => 'Aisyah Rahman',
            'email'      => 'render' . uniqid() . '@example.test',
            'password'   => 'secret-not-used',
            'status'     => 'approved',
        ]);
        $this->trainee->outlets()->sync([$this->outlet->id]);

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

    public function test_the_sessions_screen_renders(): void
    {
        Livewire::actingAs($this->manager)
            ->test(\App\Livewire\Training\Sessions::class)
            ->assertOk()
            ->assertSee('Start a session');
    }

    /** Every state of the host console, because they are four different views. */
    public function test_the_host_console_renders_in_every_state(): void
    {
        $sessions = app(LiveSessionService::class);
        $session  = $sessions->open($this->quiz, $this->outlet->id, $this->manager->id, 'Tuesday briefing');
        $sessions->join($session, 'Aisyah', $this->trainee);

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

    public function test_the_report_cards_screen_renders_a_selected_trainee(): void
    {
        // An attempt, so the card has something to draw rather than an empty state.
        $service = app(SelfPacedQuizService::class);
        $attempt = $service->startOrResume($this->quiz, $this->trainee);
        $service->answer($attempt, $this->quiz->questions->first(), [0], 3.0);
        $service->finish($attempt);

        Livewire::actingAs($this->manager)
            ->test(\App\Livewire\Training\ReportCards::class)
            ->assertOk()
            ->assertSee('Aisyah Rahman')
            ->call('select', $this->trainee->id)
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

    // ── Trainee ───────────────────────────────────────────────────────────

    private function asTrainee(): static
    {
        // See TrainingModuleTest for why this is not actingAs(..., 'lms').
        $this->app['auth']->guard('lms')->setUser($this->trainee);

        return $this;
    }

    public function test_the_trainee_course_list_renders(): void
    {
        $this->asTrainee();

        Livewire::test(\App\Livewire\Lms\Courses::class)
            ->assertOk()
            ->assertSee('Chiller discipline')
            ->assertSee('Front of house induction');
    }

    public function test_the_trainee_course_page_renders(): void
    {
        $this->asTrainee();

        Livewire::test(\App\Livewire\Lms\CourseView::class, ['id' => $this->course->id])
            ->assertOk()
            ->assertSee('Chillers run between 0 and 4 degrees.')
            ->assertSee('Chiller quiz');
    }

    public function test_playing_a_quiz_renders_the_question_then_the_result(): void
    {
        $this->asTrainee();

        $component = Livewire::test(\App\Livewire\Lms\QuizPlay::class, ['id' => $this->quiz->id])
            ->assertOk()
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

    public function test_the_progress_screen_renders(): void
    {
        $this->asTrainee();

        Livewire::test(\App\Livewire\Lms\MyProgress::class)
            ->assertOk()
            ->assertSee('My progress')
            ->assertSee('Leaderboard');
    }

    public function test_the_live_join_screen_renders_and_a_bad_pin_is_reported(): void
    {
        $this->asTrainee();

        Livewire::test(\App\Livewire\Lms\LivePlay::class)
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

        $this->asTrainee();

        $component = Livewire::test(\App\Livewire\Lms\LivePlay::class)
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
