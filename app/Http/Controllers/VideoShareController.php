<?php

namespace App\Http\Controllers;

use App\Models\VideoShareToken;

class VideoShareController extends Controller
{
    public function show(string $token)
    {
        $share = VideoShareToken::with(['recipe', 'company'])->where('token', $token)->first();

        if (! $share || ! $share->recipe || ! $share->recipe->video_url) {
            abort(404, 'Video not available.');
        }

        $recipe   = $share->recipe;
        $company  = $share->company;
        $embedUrl = $this->parseVideoEmbed($recipe->video_url);

        return view('video-share.show', compact('recipe', 'company', 'embedUrl'));
    }

    private function parseVideoEmbed(?string $url): ?string
    {
        if (! $url) return null;

        if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/)([a-zA-Z0-9_-]{11})/', $url, $m)) {
            return 'https://www.youtube-nocookie.com/embed/' . $m[1] . '?rel=0&modestbranding=1&autoplay=1';
        }

        if (preg_match('/vimeo\.com\/(\d+)/', $url, $m)) {
            return 'https://player.vimeo.com/video/' . $m[1] . '?dnt=1&autoplay=1';
        }

        return null;
    }
}
