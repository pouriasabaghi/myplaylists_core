<?php
namespace App\Services;

use App\Models\Song;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Facades\Http;
use Owenoj\LaravelGetId3\GetId3;
use Exception;

class SongService
{

    public function createSong($file)
    {
        $filesize = $file->getSize();

        //  Check for upload limitation
        if (!auth()->user()->canUpload($filesize)) {
            return response()->json([
                'message' => '🔥 You have reached your upload limit 3GB',
                'success' => false,
            ], 403);
        }


        // Get filename and path for the song
        [$path, $filename] = $this->uploadSong($file);

        // Get metadata
        $track = GetId3::fromDiskAndPath('public', $path);
        $info = $track->extractInfo();

        // Upload cover
        $comments = $info['comments'];
        $cover = $this->uploadCover($comments);

        // Create a new song
        $song = auth()->user()->songs()->create([
            'path' => $path,
            'name' => $filename,
            'cover' => $cover,
            'artist' => $track->getArtist(),
            'album' => $track->getAlbum(),
            'duration' => $track->getPlaytimeSeconds(),
            'size' => $filesize
        ]);

        return $song;
    }

    public function createSongFromTelegramBot($fileUrl, $audio, $user)
    {
        // upload song to server
        [$path] = $this->uploadSong($fileUrl);

        // get metadata
        $track = GetId3::fromDiskAndPath('public', $path);
        $info = $track->extractInfo();

        // upload cover
        $comments = $info['comments'];
        $cover = $this->uploadCover($comments);

        // create song
        Song::create([
            'user_id' => $user->id,
            'path' => $path,
            'name' => $audio->getTitle() ?? 'Unknown',
            'artist' => $audio->getPerformer(),
            'size' => $audio->getFileSize(),
            'duration' => $audio->getDuration(),
            'cover' => $cover,
        ]);
    }

    /**
     * Uploads a song file from a URL or uploaded file.
     *
     * @param \Illuminate\Http\UploadedFile|string $fileOrUrl Either an UploadedFile instance or a URL string.
     * @return array [$path, $filename]
     * @throws Exception if the file download fails.
     */
    public function uploadSong($fileOrUrl): array
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


    public function uploadCover($comments): string|null
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
    public function streamHandler(string $id): StreamedResponse
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

    public function deleteSong($song)
    {
        if ($song->user_id !== auth()->user()->id)
            throw new Exception('Only owner can make these changes', 403);



        $songPath = $song->path;
        $coverPath = $song->cover;

        $song->delete();

        Storage::disk('public')->delete($songPath);

        if ($coverPath)
            Storage::disk('public')->delete($coverPath);


    }
}
