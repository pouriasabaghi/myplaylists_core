<?php

namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use App\Http\Resources\SongResource;
use App\Models\Song;
use Illuminate\Http\Request;

class FavoriteController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        return response()->json(SongResource::collection($user->favorites));
    }

    public function toggle($songId)
    {
        $user = auth()->user();
        $song = Song::findOrFail($songId);

        if ($user->favorites()->where('song_id', $song->id)->exists()) {
            $user->favorites()->detach($song);
            return response()->json(['message' => 'Removed from favorites']);
        } else {
            $user->favorites()->attach($song);
            return response()->json(['message' => 'Added to favorites']);
        }
    }
}
