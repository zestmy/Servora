<?php

namespace App\Livewire\Profile;

use App\Services\ImageStorageService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Profile picture upload — shown on the Profile page; the image appears in
 * the sidebar user panel (both outlet and kitchen workspaces).
 */
class AvatarUpload extends Component
{
    use WithFileUploads;

    public $photo = null;

    public function updatedPhoto(): void
    {
        /*
         * REPORTED AS: changing the picture did not save the new image.
         *
         * It was rejecting the photo, at 2 MB. A picture taken on a phone is
         * routinely 3-8 MB, so the common case was the failing one — and the
         * only sign was a small red line beside the button while the avatar
         * sat there unchanged, which reads as nothing having happened.
         *
         * 5 MB matches the staff photo on the employee form, and is far under
         * what the server accepts (php-fpm 20 MB, nginx 25 MB), so the cap is
         * now the app's considered limit rather than an accident.
         *
         * HEIC is accepted because iPhones send it whenever "Keep Originals"
         * is on, and Imagick on this server reads it. The `image` rule does
         * not: it asks getimagesize(), which does not know the format, so
         * those uploads failed with "must be an image" and no way to comply
         * short of converting the file by hand.
         *
         * SVG is deliberately NOT in the list, though the `image` rule allowed
         * it. An SVG carries script, and this one is rendered back to every
         * page in the sidebar.
         */
        $this->validate([
            'photo' => ImageStorageService::uploadRule(5120),
        ], [
            'photo.mimes' => ImageStorageService::uploadMessage(),
            'photo.max'   => 'The image may not be larger than 5 MB.',
        ]);

        $user = Auth::user();

        /*
         * Compressed on the way in, the same as the staff photo. It is shown
         * at 64 pixels: storing 8 MB of phone camera to render a thumbnail
         * costs the disk, the backup and every page that loads it. This also
         * strips EXIF — an avatar should not carry the GPS coordinates of
         * wherever it was taken — and honours the orientation flag, so a photo
         * taken sideways is not stored sideways.
         */
        $path = ImageStorageService::storeCompressed($this->photo, 'avatars', 'public');

        if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
            Storage::disk('public')->delete($user->avatar);
        }

        $user->forceFill(['avatar' => $path])->save();
        $this->photo = null;

        $this->dispatch('avatar-updated');
        session()->flash('avatar_success', 'Profile picture updated.');
        $this->redirect(route('profile'));
    }

    public function removeAvatar(): void
    {
        $user = Auth::user();
        if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
            Storage::disk('public')->delete($user->avatar);
        }
        $user->forceFill(['avatar' => null])->save();

        session()->flash('avatar_success', 'Profile picture removed.');
        $this->redirect(route('profile'));
    }

    public function render()
    {
        return view('livewire.profile.avatar-upload');
    }
}
