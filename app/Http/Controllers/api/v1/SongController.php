<?php

namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use App\Models\Song;
use App\Services\SongService;
use Illuminate\Http\Request;
use Storage;

class SongController extends Controller
{

    public function index()
    {
        $songs = Song::all();

        return response()->json($songs);
    }


    /**
     * Upload a song to the server.
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        try {
            // Validate the file
            $data = $request->validate([
                'file' => 'required|mimetypes:audio/mpeg,audio/wav,audio/ogg',
            ]);

            $file = $data['file'];

            // Get filename and path for the song
            [$path, $filename] = SongService::uploadSong($file);

            // Create a new song
            $song = Song::create(['path' => $path, 'name' => $filename]);

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


    public function show(string $id)
    {
        $song = Song::findOrFail($id);

        return response()->json($song);
    }

    public function edit(string $id)
    {
        $song = Song::findOrFail($id);

        return response()->json($song);
    }

    public function update(Request $request, string $id)
    {
        try {
            $song = Song::findOrFail($id);

            // Update the song
            $song->update([
                'name' => $request->name,
                'artist' => $request->artist,
                'album' => $request->album,
                'time' => $request->time,
                'cover' => $request->cover,
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

    public function destroy(string $id)
    {
        try {
            $song = Song::findOrFail($id);

            $filepath = $song->path;

            $song->delete();

            Storage::disk('public')->delete($filepath);

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
}
