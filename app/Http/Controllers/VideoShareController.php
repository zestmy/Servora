<?php

namespace App\Http\Controllers;

use App\Models\VideoShareToken;

class VideoShareController extends Controller
{
    /**
     * Show the video player page (no video URL in source).
     */
    public function show(string $token)
    {
        $share = VideoShareToken::with(['recipe', 'company'])->where('token', $token)->first();

        if (! $share || ! $share->recipe || ! $share->recipe->video_url) {
            abort(404, 'Video not available.');
        }

        $recipe  = $share->recipe;
        $company = $share->company;

        return view('video-share.show', compact('recipe', 'company', 'token'));
    }

    /**
     * Return embed URL via AJAX — keeps video ID out of page source.
     */
    public function data(string $token)
    {
        $share = VideoShareToken::with('recipe')->where('token', $token)->first();

        if (! $share || ! $share->recipe || ! $share->recipe->video_url) {
            return response()->json(['embed' => null]);
        }

        return response()->json([
            'embed' => $this->parseVideoEmbed($share->recipe->video_url),
        ]);
    }

    private function parseVideoEmbed(?string $url): ?string
    {
        if (! $url) return null;

        // YouTube — maximum privacy embed
        if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/)([a-zA-Z0-9_-]{11})/', $url, $m)) {
            return 'https://www.youtube-nocookie.com/embed/' . $m[1] . '?' . http_build_query([
                'rel'             => 0,     // no related videos
                'modestbranding'  => 1,     // minimal YouTube branding
                'autoplay'        => 1,     // auto-play on load
                'controls'        => 1,     // show basic controls
                'iv_load_policy'  => 3,     // disable annotations
                'disablekb'       => 1,     // disable keyboard shortcuts
                'playsinline'     => 1,     // inline on mobile
                'cc_load_policy'  => 0,     // don't auto-show captions
                'origin'          => config('app.url'),
            ]);
        }

        // Vimeo — privacy-enhanced embed
        if (preg_match('/vimeo\.com\/(\d+)/', $url, $m)) {
            return 'https://player.vimeo.com/video/' . $m[1] . '?' . http_build_query([
                'dnt'       => 1,   // do not track
                'autoplay'  => 1,
                'title'     => 0,   // hide video title
                'byline'    => 0,   // hide uploader name
                'portrait'  => 0,   // hide uploader avatar
                'controls'  => 1,
            ]);
        }

        return null;
    }
}
