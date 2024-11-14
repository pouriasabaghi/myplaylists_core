<?php
namespace App\Services;

use App\Models\Song;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Facades\Http;
use Exception;

class SongService
{

   /**
     * Uploads a song file from a URL or uploaded file.
     *
     * @param \Illuminate\Http\UploadedFile|string $fileOrUrl Either an UploadedFile instance or a URL string.
     * @return array [$path, $filename]
     * @throws Exception if the file download fails.
     */
    public static function uploadSong($fileOrUrl): array
    {
        if (is_string($fileOrUrl)) {
            // Handle the URL case
            try {
                $response = Http::get($fileOrUrl);
                if (!$response->successful()) {
                    throw new Exception('Failed to download file.');
                }
                
                // Extract the filename from the URL
                $urlPath = parse_url($fileOrUrl, PHP_URL_PATH);
                $filename = pathinfo($urlPath, PATHINFO_FILENAME);
                $extension = pathinfo($urlPath, PATHINFO_EXTENSION);
                
                // Create a unique filename if needed
                $uploadFilename = "$filename.$extension";
                $fileExists = Song::where('path', 'like', "songs/%/$uploadFilename%")->exists();
                if ($fileExists) {
                    $uploadFilename = time() . '_' . $uploadFilename;
                }
                
                // Define folder based on date
                $folder = date('Y/m');
                $path = "songs/$folder/$uploadFilename";
                
                // Store the downloaded file
                Storage::disk('public')->put($path, $response->body());
            } catch (Exception $e) {
                throw new Exception('File download failed: ' . $e->getMessage());
            }
        } else {
            // Handle the uploaded file case
            $filename = pathinfo($fileOrUrl->getClientOriginalName(), PATHINFO_FILENAME);
            $uploadFilename = $fileOrUrl->getClientOriginalName();
            $fileExists = Song::where('path', 'like', "songs/%/$uploadFilename%")->exists();
            if ($fileExists) {
                $uploadFilename = time() . '_' . $uploadFilename;
            }

            // Store the uploaded file in storage
            $folder = date('Y/m');
            $path = $fileOrUrl->storeAs("songs/$folder", $uploadFilename, 'public');
        }

        return [$path, $filename];
    }


    public static function uploadCover($comments): string|null
    {
        if (isset($comments['picture'][0])) {
            $pictureData = $comments['picture'][0];

            if (isset($pictureData['data'])) {
                // create uniq name
                $fileName = uniqid() . '.jpg';

                $folder = date('Y/m');
                Storage::disk('public')->put("covers/$folder/$fileName", $pictureData['data']);

                return "covers/$folder/$fileName";
            }
        }

        return null;
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
