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

    public function removeSong(Playlist $playlist, Song $song, PlaylistService $playlistService): JsonResponse
    {
        $playlistService->removeSongFromPlaylist($playlist, $song);

        return response()->json([
            'message' => 'Song removed from playlist.',
            'success' => true
        ]);
    }
}
