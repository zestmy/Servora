<?php

namespace App\Livewire\Components;

use App\Models\Announcement;
use Livewire\Component;

class AnnouncementBanner extends Component
{
    public function render()
    {
        $announcements = Announcement::active()->latest()->limit(3)->get();

        return view('livewire.components.announcement-banner', compact('announcements'));
    }
}
