<?php

namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PlaylistResource;
use App\Http\Resources\SongResource;
use App\Models\Playlist;
use App\Models\Song;
use App\Services\PlaylistService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

class PlaylistController extends Controller
{
    public function index()
    {
        $playlists = auth()->user()->playlists;
        return response()->json(PlaylistResource::collection($playlists));
    }

    public function create(Request $request, PlaylistService $playlistService): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $playlist = $playlistService->createPlaylist($request->name);

        return response()->json([
            'message' => 'Playlist created successfully',
            'playlist' => $playlist,
            'success' => true,
        ], 201);
    }

    public function edit(Request $request, Playlist $playlist): JsonResponse
    {
        return response()->json($playlist);
    }

    public function update(Request $request, Playlist $playlist, PlaylistService $playlistService): JsonResponse
    {
        if ($playlist->user_id !== auth()->user()->id) {
            return response()->json([
                'message' => 'Only owner can make these changes',
                'success' => false
            ], 403);
        }

        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $playlist = $playlistService->updatePlayList($playlist, $request->name);

        return response()->json([
            'message' => 'Playlist updated successfully',
            'playlist' => $playlist,
            'success' => true,
        ], 200);
    }

    public function destroy(Playlist $playlist): JsonResponse
    {
        try {
            if ($playlist->user_id !== auth()->user()->id) {
                return response()->json([
                    'message' => 'Only owner can make these changes',
                    'success' => false
                ], 403);
            }
            $playlist->songs()->detach();
            $playlist->delete();

            return response()->json([
                'message' => 'Playlist deleted successfully',
                'success' => true
            ]);

        } catch (\Exception $th) {
            return response()->json([
                'message' => $th->getMessage(),
                'success' => false
            ], $th->getCode() ?: 500);
        }
    }


    public function getSongs(Playlist $playlist): JsonResponse
    {
        return response()->json(SongResource::collection($playlist->songs));
    }

    public function addSong(Request $request, Playlist $playlist, PlaylistService $playlistService): JsonResponse
    {
        $request->validate([
            'song_id' => 'required|exists:songs,id',
        ]);

        $song = Song::findOrFail($request->song_id);

        $playlistService->addSongToPlaylist($playlist, $song);

        return response()->json([
            'message' => 'Song added to playlist successfully',
            'success' => true,
        ]);
    }

    public function addSongs(Request $request, Playlist $playlist, PlaylistService $playlistService): JsonResponse
    {
        $data = $request->validate([
            'songs_ids' => 'required|array',
        ]);

        $ids = $data['songs_ids'];

        $songs = Song::whereIn('id', $ids)->get();

        foreach ($songs as $song) {
            $playlistService->addSongToPlaylist($playlist, $song);
        }

        return response()->json([
            'message' => 'Song added to playlist successfully',
            'success' => true,
        ]);
    }

    public function removeSong(Playlist $playlist, Song $song, PlaylistService $playlistService): JsonResponse
    {
        if ($playlist->user_id !== auth()->user()->id) {
            return response()->json([
                'message' => 'Only owner can make these changes',
                'success' => false
            ], 403);
        }

        $playlistService->removeSongFromPlaylist($playlist, $song);

        return response()->json([
            'message' => 'Song removed from playlist.',
            'success' => true
        ]);
    }


    public function getTopPlaylists()
    {
        $mostFollowedPlaylists = cache()->remember('top_playlists', now()->addDay(), function () {
            return Playlist::withCount(['followers', 'songs'])
                ->having('songs_count', '>=', 5)
                ->orderByDesc('followers_count')
                ->take(50)
                ->get();
        });

        return response()->json(PlaylistResource::collection($mostFollowedPlaylists));
    }

    public function getLatestPlaylists()
    {
        $latestPlaylists = Playlist::orderByDesc('created_at')->take(10)->get();
        return response()->json(PlaylistResource::collection($latestPlaylists));
    }

}
