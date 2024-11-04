<?php
namespace App\Services;

use App\Models\Song;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SongService
{

    /**
     * Uploads a song file to the storage and generates a unique filename if needed.
     *
     * @param \Illuminate\Http\UploadedFile $file The uploaded song file.
     * @return array [$path, $filename]
     * */
    public static function uploadSong($file): array
    {
        $filename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);

        // Create a unique file name
        $uploadFilename = $file->getClientOriginalName();

        // Check if file already exists in database and add a time stamp if it does
        $fileExists = Song::where('path', 'like', "songs/%/$uploadFilename%")->exists();
        if ($fileExists) {
            $uploadFilename = time() . '_' . $uploadFilename;
        }

        // Store the file in the storage
        $folder = date('Y/m');
        $path = $file->storeAs("songs/$folder", $uploadFilename, 'public');

        return [$path, $filename];
    }


    /**
     * Handles the streaming of a song by id.
     *
     * @param string $id The id of the song to stream.
     *
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     * */
    public static function streamHandler(string $id): StreamedResponse
    {
        $song = Song::findOrFail($id);
        $path = $song->path;

        if (!Storage::disk('public')->exists($path))
            return response()->json(['message' => 'File not found'], 404);

        return Storage::disk('public')->response($path, $song->name, [
            'Content-Type' => 'audio/mpeg',
            'Accept-Ranges' => 'bytes',
        ]);
    }
}
