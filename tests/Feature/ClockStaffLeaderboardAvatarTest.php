<?php

namespace Tests\Feature;

use App\Livewire\Staff\Training\Leaderboard;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Outlet;
use App\Models\TrainingAttempt;
use App\Models\TrainingCourse;
use App\Models\TrainingQuiz;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The Clock app's training leaderboard wears faces: the reader's own banner
 * at the top and every board row use the shared staff-avatar, photo over
 * initials, served by the PIN-session photo route. On a board you scan
 * rather than read, the face is what lets somebody find a row before they
 * have read a single character — and no photo on file still means the
 * coloured initials disc, never a broken image.
 */
class ClockStaffLeaderboardAvatarTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Outlet $outlet;
    private Employee $me;
    private Employee $rival;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name'      => 'Board Faces Co',
            'slug'      => Str::slug('Board Faces Co') . '-' . uniqid(),
            'currency'  => 'MYR',
            'is_active' => true,
        ]);

        $this->outlet = Outlet::create([
            'company_id' => $this->company->id, 'name' => 'Bangsar', 'code' => 'BSR', 'is_active' => true,
        ]);

        $this->me = Employee::create([
            'company_id' => $this->company->id,
            'outlet_id'  => $this->outlet->id,
            'name'       => 'Aisyah Rahman',
            'email'      => 'aisyah' . uniqid() . '@example.test',
            'photo_path' => 'hr/photos/aisyah.jpg',
            'is_active'  => true,
        ]);

        $this->rival = Employee::create([
            'company_id' => $this->company->id,
            'outlet_id'  => $this->outlet->id,
            'name'       => 'Ben Ooi',
            'email'      => 'ben' . uniqid() . '@example.test',
            'photo_path' => 'hr/photos/ben.jpg',
            'is_active'  => true,
        ]);

        $course = TrainingCourse::create([
            'company_id' => $this->company->id,
            'title'      => 'Chiller discipline',
            'content'    => 'Chillers run between 0 and 4 degrees.',
            'status'     => 'published',
        ]);

        $quiz = TrainingQuiz::create([
            'company_id'         => $this->company->id,
            'training_course_id' => $course->id,
            'title'              => 'Chiller quiz',
            'status'             => 'published',
        ]);

        foreach ([$this->me, $this->rival] as $who) {
            TrainingAttempt::create([
                'company_id'       => $this->company->id,
                'training_quiz_id' => $quiz->id,
                'employee_id'      => $who->id,
                'outlet_id'        => $this->outlet->id,
                'mode'             => 'solo',
                'started_at'       => now()->subMinutes(10),
                'completed_at'     => now(),
                'score'            => 900,
                'max_score'        => 1000,
                'correct_count'    => 3,
                'question_count'   => 4,
                'percent'          => 75,
                'passed'           => true,
            ]);
        }

        // A PIN session, not a guard — there is no actingAs() for this.
        session(['subdomain_company_id' => $this->company->id]);
        app(\App\Services\Staff\StaffSession::class)->signIn($this->me, 'email');
    }

    public function test_the_banner_and_every_row_carry_the_photo(): void
    {
        $html = Livewire::test(Leaderboard::class)
            ->assertSee('Aisyah Rahman')
            ->assertSee('Ben Ooi')
            ->html();

        // My photo appears twice — once in the standing banner, once in my
        // board row. A single occurrence means one of the two lost its face.
        $this->assertSame(
            2,
            substr_count($html, route('clock.staff.photo', $this->me->id)),
            'The banner and the board row both wear the photo.'
        );

        $this->assertStringContainsString(
            route('clock.staff.photo', $this->rival->id),
            $html,
            'Everyone on the board gets their face, not just the signed-in reader.'
        );
    }

    public function test_no_photo_on_file_still_means_the_initials_disc(): void
    {
        $this->me->update(['photo_path' => null]);

        $html = Livewire::test(Leaderboard::class)
            ->assertSee('Aisyah Rahman')
            ->html();

        $this->assertStringNotContainsString(
            route('clock.staff.photo', $this->me->id),
            $html,
            'No photo on file must mean no img at all — the disc is the avatar.'
        );
        $this->assertStringContainsString('AR', $html, 'The coloured disc carries the initials.');
    }
}
