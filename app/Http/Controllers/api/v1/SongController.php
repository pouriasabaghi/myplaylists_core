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
        $songs = auth()->user()->songs()->latest()->get();
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
                'file' => 'required|mimetypes:audio/mpeg,audio/wav,audio/ogg,audio/x-m4a',
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

            \Gate::authorize('modify', $song);

            // Update the song
            $song->update([
                'name' => $request->name,
                'artist' => $request->artist,
                'album' => $request->album,
                'lyrics' => $request->lyrics,
            ]);

            return response()->json([
                'message' => 'Song updated successfully',
                'song' => $song,
                'success' => true,
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'message' =>$th?->getCode() === 500 ? 'An error occurred' : $th->getMessage(),
                'error' => $th->getMessage(),
                'success' => false,
            ], 500);
        }
    }

    public function destroy(string|array $id, SongService $songService): JsonResponse
    {
        try {
            $song = Song::findOrFail($id);

            \Gate::authorize('modify', $song);

            $songService->deleteSong($song);

            return response()->json([
                'message' => 'Song deleted successfully',
                'success' => true,
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => $th->getCode() === 500 ? 'An error accorded' : $th->getMessage(),
                'error' => $th->getMessage(),
                'success' => false,
            ], 500);
        }
    }

    public function bulkDestroy(Request $request, SongService $songService): JsonResponse
    {
        try {
            $data = $request->validate([
                'ids' => 'required|array',
            ]);

            $ids = $data['ids'];

            $songs = Song::whereIn('id', $ids)->get();

            foreach ($songs as $song) {
                \Gate::authorize('modify', $song);
                $songService->deleteSong($song);
            }

            return response()->json([
                'message' => 'Song deleted successfully',
                'success' => true,
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => $th->getCode() === 500 ? 'An error accorded' : $th->getMessage(),
                'error' => $th->getMessage(),
                'success' => false,
            ], 500);
        }
    }

    public function stream(string $id, SongService $songService): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        return $songService->streamHandler($id);
    }

    public function download(string $id, SongService $songService)
    {
        return $songService->downloadHandler($id);
    }

    public function getTopSongs()
    {
        $mostFavoritesSongs = cache()->remember('top_songs', now()->addDay(), function () {
            return Song::withCount('favorites')
                ->orderByDesc('favorites_count')
                ->take(30)
                ->get();
        });

        return response()->json(SongResource::collection($mostFavoritesSongs));
    }

    public function getLatestSongs()
    {
        $latestSong = Song::orderByDesc('created_at')->take(30)->get();
        return response()->json($latestSong);
    }
}
