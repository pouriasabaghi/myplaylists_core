<?php

namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use App\Http\Resources\SongResource;
use App\Models\Song;
use App\Services\SongService;
use Illuminate\Http\Request;
use Storage;
use Symfony\Component\HttpFoundation\JsonResponse;


class SongController extends Controller
{
    public function index(): JsonResponse
    {
        $songs = auth()->user()->songs()->get();
        return response()->json(SongResource::collection($songs));
    }

    /**
     * Upload a song to the server.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request, SongService $songService): JsonResponse
    {
        try {

            // Validate the file
            $data = $request->validate([
                'file' => 'required|mimetypes:audio/mpeg,audio/wav,audio/ogg',
            ]);
            $file = $data['file'];

            $song = $songService->createSong($file);

            // Return the response
            return response()->json([
                'message' => 'Song uploaded successfully',
                'song' => $song,
                'success' => true,
            ], 201);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'An error occurred',
                'error' => $th->getMessage() . ' in line:' . $th->getLine(),
                'success' => false,
            ], 500);
        }
    }

    public function show(string $id): JsonResponse
    {
        $song = Song::findOrFail($id);

        return response()->json(new SongResource($song));
    }

    public function edit(string $id): JsonResponse
    {
        $song = auth()->user()->songs()->findOrFail($id);

        return response()->json($song);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $song = Song::findOrFail($id);

            if($song->user_id !== auth()->user()->id) {
                return response()->json([
                    'message' => 'Only owner can make these changes',
                    'success' => false
                ], 403);
            }

            // Update the song
            $song->update([
                'name' => $request->name,
                'artist' => $request->artist,
                'album' => $request->album,
            ]);

            return response()->json([
                'message' => 'Song updated successfully',
                'song' => $song,
                'success' => true,
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'An error occurred',
                'error' => $th->getMessage(),
                'success' => false,
            ], 500);
        }
    }

    public function destroy(string $id): JsonResponse
    {
        try {
            $song = Song::findOrFail($id);

            if($song->user_id !== auth()->user()->id) {
                return response()->json([
                    'message' => 'Only owner can make these changes',
                    'success' => false
                ], 403);
            }

            $songPath = $song->path;
            $coverPath = $song->cover;

            $song->delete();

            Storage::disk('public')->delete($songPath);

            if ($coverPath)
                Storage::disk('public')->delete($coverPath);

            return response()->json([
                'message' => 'Song deleted successfully',
                'success' => true,
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'An error occurred',
                'error' => $th->getMessage(),
                'success' => false,
            ], 500);
        }
    }

    public function stream(string $id, SongService $songService): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        return $songService->streamHandler($id);
    }

    public function getTopSongs()
    {
        $mostFavoritesSongs = Song::withCount('favorites')
            ->orderByDesc('favorites_count')
            ->take(10)
            ->get();

        return response()->json(SongResource::collection($mostFavoritesSongs));
    }
}
