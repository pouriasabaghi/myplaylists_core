<?php

namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use App\Http\Resources\SongResource;
use App\Models\Song;
use App\Services\SongService;
use Illuminate\Http\Request;
use Storage;
use Symfony\Component\HttpFoundation\JsonResponse;
use Owenoj\LaravelGetId3\GetId3;

class SongController extends Controller
{
    public function index(): JsonResponse
    {
        $songs = Song::all();

        return response()->json(SongResource::collection($songs));
    }

    /**
     * Upload a song to the server.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        try {

            // Validate the file
            $data = $request->validate([
                'file' => 'required|mimetypes:audio/mpeg,audio/wav,audio/ogg',
            ]);


            $file = $data['file'];

            // Get metadata
            $track = GetId3::fromUploadedFile(request()->file('file'));
            $info = $track->extractInfo();

            //return response()->json([$track->getTitle()]);
            // Upload cover
            $comments = $info['comments'];
            $cover = SongService::uploadCover($comments);


            // Get filename and path for the song
            [$path, $filename] = SongService::uploadSong($file);

            // Create a new song
            $song = auth()->user()->songs()->create([
                'path' => $path,
                'name' => $filename,
                'cover' => $cover,
                'artist' => $track->getArtist(),
                'album' => $track->getAlbum(),
                'duration' => $track->getPlaytimeSeconds(),
                'size' => $file->getSize()
            ]);

            // Return the response
            return response()->json([
                'message' => 'Song uploaded successfully',
                'song' => $song,
                'success' => true,
                'file' => $filename,
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
        $song = Song::findOrFail($id);

        return response()->json($song);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $song = Song::findOrFail($id);

            // Update the song
            $song->update([
                'name' => $request->name,
                'artist' => $request->artist,
                'album' => $request->album,
                'time' => $request->time,
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

            $songPath = $song->path;
            $coverPath = $song->cover;

            $song->delete();

            Storage::disk('public')->delete($songPath);
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

    public function stream(string $id): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        return SongService::streamHandler($id);
    }
}
