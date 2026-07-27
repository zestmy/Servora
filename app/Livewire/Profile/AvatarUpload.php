<?php

namespace App\Livewire\Profile;

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
        $this->validate([
            'photo' => 'image|max:2048', // 2 MB
        ], [
            'photo.image' => 'The file must be an image (JPG, PNG, GIF or WebP).',
            'photo.max'   => 'The image may not be larger than 2 MB.',
        ]);

        $user = Auth::user();

        $path = $this->photo->store('avatars', 'public');

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
