<?php

namespace Tests\Feature;

use App\Livewire\Profile\AvatarUpload;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Changing your profile picture changes your profile picture.
 *
 * REPORTED AS: it did not save the new image. It was being rejected, at 2 MB —
 * and a picture taken on a phone is routinely 3-8 MB, so the common case was
 * the failing one. The only sign was a small red line beside the button while
 * the avatar sat there unchanged, which reads as nothing having happened at
 * all rather than as a refusal.
 *
 * The cap is 5 MB now, matching the staff photo on the employee form and well
 * under what the server takes (php-fpm 20 MB, nginx 25 MB), and anything over
 * the display size is compressed rather than refused.
 */
class ProfilePictureUploadTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $company = Company::create([
            'name' => 'Pic Co', 'slug' => Str::slug('Pic Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);

        $this->user = User::factory()->create(['company_id' => $company->id]);
        $this->user->companies()->syncWithoutDetaching([$company->id]);
    }

    private function upload(UploadedFile $file)
    {
        return Livewire::actingAs($this->user)->test(AvatarUpload::class)->set('photo', $file);
    }

    /** The reported case: a phone-sized photo. */
    public function test_a_phone_sized_photo_saves(): void
    {
        $this->user->forceFill(['avatar' => 'avatars/old.jpg'])->save();

        $this->upload(UploadedFile::fake()->image('holiday.jpg', 4000, 3000)->size(4096))
            ->assertHasNoErrors();

        $this->assertNotSame('avatars/old.jpg', $this->user->fresh()->avatar,
            'A 4 MB photo is an ordinary photo, not an oversized one.');
    }

    public function test_an_ordinary_small_image_still_saves(): void
    {
        $this->upload(UploadedFile::fake()->image('me.png', 400, 400))->assertHasNoErrors();

        $this->assertNotNull($this->user->fresh()->avatar);
        Storage::disk('public')->assertExists($this->user->fresh()->avatar);
    }

    /** iPhones send HEIC whenever "Keep Originals" is on. */
    public function test_a_heic_photo_is_accepted(): void
    {
        $heic = UploadedFile::fake()->create('IMG_0042.heic', 2048, 'image/heic');

        $this->upload($heic)->assertHasNoErrors('photo');
    }

    /** Replacing one removes the file it replaced rather than orphaning it. */
    public function test_the_previous_picture_is_cleaned_up(): void
    {
        Storage::disk('public')->put('avatars/old.jpg', 'x');
        $this->user->forceFill(['avatar' => 'avatars/old.jpg'])->save();

        $this->upload(UploadedFile::fake()->image('new.jpg', 400, 400));

        Storage::disk('public')->assertMissing('avatars/old.jpg');
    }

    // ── What must still be refused ────────────────────────────────────────

    /** There is a limit; it is just not below a normal photo. */
    public function test_something_genuinely_enormous_is_still_refused(): void
    {
        $this->user->forceFill(['avatar' => 'avatars/old.jpg'])->save();

        $this->upload(UploadedFile::fake()->image('huge.jpg', 9000, 9000)->size(8192))
            ->assertHasErrors('photo');

        $this->assertSame('avatars/old.jpg', $this->user->fresh()->avatar);
    }

    /**
     * SVG was allowed by the old `image` rule. It carries script and this file
     * is rendered back onto every page in the sidebar.
     */
    public function test_an_svg_is_refused(): void
    {
        $svg = UploadedFile::fake()->create('avatar.svg', 4, 'image/svg+xml');

        $this->upload($svg)->assertHasErrors('photo');

        $this->assertNull($this->user->fresh()->avatar);
    }

    public function test_a_document_pretending_to_be_a_picture_is_refused(): void
    {
        $this->upload(UploadedFile::fake()->create('payroll.pdf', 100, 'application/pdf'))
            ->assertHasErrors('photo');

        $this->assertNull($this->user->fresh()->avatar);
    }
}
