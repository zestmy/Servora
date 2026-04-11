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
     * Return video type + ID via AJAX — keeps video URL out of page source.
     */
    public function data(string $token)
    {
        $share = VideoShareToken::with('recipe')->where('token', $token)->first();

        if (! $share || ! $share->recipe || ! $share->recipe->video_url) {
            return response()->json(['type' => null, 'id' => null]);
        }

        $url = $share->recipe->video_url;

        if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/)([a-zA-Z0-9_-]{11})/', $url, $m)) {
            return response()->json(['type' => 'youtube', 'id' => $m[1]]);
        }

        if (preg_match('/vimeo\.com\/(\d+)/', $url, $m)) {
            return response()->json(['type' => 'vimeo', 'id' => $m[1]]);
        }

        return response()->json(['type' => null, 'id' => null]);
    }
}
