<?php

namespace Tests\Feature;

use App\Livewire\Training\QuizBuilder;
use App\Models\Company;
use App\Models\TrainingCourse;
use App\Models\TrainingMusicTrack;
use App\Models\TrainingQuiz;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Backing tracks uploaded once and chosen from.
 *
 * Every quiz used to upload its own, so a company running eight papers with one
 * song carried eight copies of it, none of them named. What needs guarding is
 * not the picker but the three things around it:
 *
 *   no copying        choosing a track points a second quiz at the SAME path.
 *                     If this ever starts duplicating the file, the feature has
 *                     silently become the thing it replaced.
 *   no cross-tenant   the select is client-supplied like any other field, and
 *                     an id from another company must resolve to nothing rather
 *                     than to their audio.
 *   no deletion       taking music off one quiz must leave the track alone.
 *                     Other papers are playing it, and the failure is silent —
 *                     a published quiz that simply stops having music.
 */
class QuizMusicLibraryTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private User $user;
    private TrainingQuiz $quiz;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->company = Company::create([
            'name' => 'Music Co', 'slug' => Str::slug('Music Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);

        Permission::findOrCreate('training.manage', 'web');

        $this->user = User::factory()->create(['company_id' => $this->company->id]);
        $this->user->companies()->syncWithoutDetaching([$this->company->id]);

        setPermissionsTeamId($this->company->id);
        $this->user->givePermissionTo(['training.manage']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $course = TrainingCourse::create([
            'company_id' => $this->company->id, 'title' => 'Chiller discipline',
            'status' => 'published',
        ]);

        $this->quiz = TrainingQuiz::create([
            'company_id' => $this->company->id, 'training_course_id' => $course->id,
            'title' => 'Chiller quiz', 'status' => 'published',
            'shuffle_questions' => false, 'shuffle_options' => false,
            'issues_certificate' => false,
        ]);

        // saveSettings() refuses to publish a paper with no questions, and this
        // quiz is published so that the music assertions run against the same
        // path a real save takes rather than a draft-only shortcut.
        $this->quiz->questions()->create([
            'type'    => 'mcq',
            'prompt'  => 'What temperature does a chiller hold?',
            'options' => ['0-4°C', '8°C', '12°C', 'Room temperature'],
            'correct' => [0],
        ]);
    }

    private function builder()
    {
        return Livewire::actingAs($this->user)->test(QuizBuilder::class, ['id' => $this->quiz->id]);
    }

    /** An upload lands in the library, named, so the next quiz can find it. */
    public function test_an_uploaded_track_joins_the_library(): void
    {
        $this->builder()
            ->set('musicFile', UploadedFile::fake()->create('intro.mp3', 200, 'audio/mpeg'))
            ->set('musicTitle', 'Upbeat intro')
            ->call('saveSettings')
            ->assertHasNoErrors();

        $track = TrainingMusicTrack::withoutGlobalScopes()->first();

        $this->assertNotNull($track);
        $this->assertSame('Upbeat intro', $track->title);
        $this->assertSame('intro.mp3', $track->original_name);
        $this->assertSame($this->quiz->fresh()->music_path, $track->path);
    }

    /** The size is read before store() moves the temporary file. */
    public function test_the_library_records_the_size(): void
    {
        $this->builder()
            ->set('musicFile', UploadedFile::fake()->create('intro.mp3', 200, 'audio/mpeg'))
            ->call('saveSettings');

        $this->assertGreaterThan(0, (int) TrainingMusicTrack::withoutGlobalScopes()->first()->size_bytes);
    }

    /**
     * The point of the whole feature: a second quiz plays the same FILE.
     *
     * If this ever asserts two different paths, the library has quietly become
     * eight copies of one song again.
     */
    public function test_choosing_a_track_shares_the_file_rather_than_copying_it(): void
    {
        $track = TrainingMusicTrack::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'title'      => 'House track',
            'path'       => 'training/music/house.mp3',
        ]);

        $this->builder()
            ->set('musicTrackId', (string) $track->id)
            ->call('saveSettings')
            ->assertHasNoErrors();

        $this->assertSame('training/music/house.mp3', $this->quiz->fresh()->music_path);
        $this->assertSame(1, TrainingMusicTrack::withoutGlobalScopes()->count());
    }

    /** A fresh upload beats a library choice left over from before browsing. */
    public function test_an_upload_wins_over_a_selected_track(): void
    {
        $track = TrainingMusicTrack::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'title'      => 'Old track',
            'path'       => 'training/music/old.mp3',
        ]);

        $this->builder()
            ->set('musicTrackId', (string) $track->id)
            ->set('musicFile', UploadedFile::fake()->create('new.mp3', 120, 'audio/mpeg'))
            ->call('saveSettings')
            ->assertHasNoErrors();

        $this->assertNotSame('training/music/old.mp3', $this->quiz->fresh()->music_path);
    }

    /** Another tenant's id must resolve to nothing, not to their audio. */
    public function test_a_track_from_another_company_is_refused(): void
    {
        $other = Company::create([
            'name' => 'Other Co', 'slug' => Str::slug('Other Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);

        $theirs = TrainingMusicTrack::withoutGlobalScopes()->create([
            'company_id' => $other->id,
            'title'      => 'Their track',
            'path'       => 'training/music/theirs.mp3',
        ]);

        $this->builder()
            ->set('musicTrackId', (string) $theirs->id)
            ->call('saveSettings')
            ->assertHasErrors('musicTrackId');

        $this->assertNull($this->quiz->fresh()->music_path);
    }

    /**
     * Taking music off one quiz leaves the track for the others.
     *
     * The failure this guards is silent: a published paper that simply stops
     * having music because somebody edited a different one.
     */
    public function test_removing_music_from_a_quiz_keeps_the_track(): void
    {
        $track = TrainingMusicTrack::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'title'      => 'Shared track',
            'path'       => 'training/music/shared.mp3',
        ]);

        $this->builder()
            ->set('musicTrackId', (string) $track->id)
            ->call('saveSettings')
            ->call('removeMusicFile')
            ->call('saveSettings')
            ->assertHasNoErrors();

        $this->assertNull($this->quiz->fresh()->music_path);
        $this->assertNotNull(TrainingMusicTrack::withoutGlobalScopes()->find($track->id));
    }

    /** Renaming reaches the library, since the list is the only place it shows. */
    public function test_renaming_a_track_updates_the_library(): void
    {
        $track = TrainingMusicTrack::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'title'      => 'Untitled',
            'path'       => 'training/music/shared.mp3',
        ]);

        $this->builder()
            ->set('musicTrackId', (string) $track->id)
            ->set('musicTitle', 'Morning shift theme')
            ->call('saveSettings')
            ->assertHasNoErrors();

        $this->assertSame('Morning shift theme', $track->fresh()->title);
    }

    /** adopt() is idempotent, so re-saving one quiz does not grow the list. */
    public function test_saving_twice_does_not_duplicate_the_track(): void
    {
        $this->builder()
            ->set('musicFile', UploadedFile::fake()->create('intro.mp3', 100, 'audio/mpeg'))
            ->set('musicTitle', 'Intro')
            ->call('saveSettings')
            ->call('saveSettings')
            ->assertHasNoErrors();

        $this->assertSame(1, TrainingMusicTrack::withoutGlobalScopes()->count());
    }
}
