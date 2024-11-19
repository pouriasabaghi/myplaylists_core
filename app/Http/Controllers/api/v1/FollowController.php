<?php

namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PlaylistResource;
use App\Models\Playlist;
use Illuminate\Http\Request;

class FollowController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        return response()->json( PlaylistResource::collection($user->followedPlaylists));
    }
    public function toggle(Playlist $playlist)
    {
        $user = auth()->user();

        if ($playlist->user_id === $user->id) {
            return response()->json(['message' => 'You cannot follow your own playlist'], 400);
        }

        if ($user->followedPlaylists()->where('playlist_id', $playlist->id)->exists()) {
            $user->followedPlaylists()->detach($playlist->id);
            return response()->json(['message' => 'Removed from follows']);
        } else {
            $user->followedPlaylists()->attach($playlist->id);
            return response()->json(['message' => 'Added to follows']);
        }
    }

    /**
     * Check if a user is following a playlist.
     */
    public function isFollowing(Request $request, Playlist $playlist)
    {
        $user = auth()->user();
        $isFollowing = $user->followedPlaylists()->where('playlist_id', $playlist->id)->exists();

        return response()->json($isFollowing, 200);
    }
}
