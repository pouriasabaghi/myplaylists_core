<?php
namespace App\Services;

use App\Models\Song;

class SongService
{

    /**
     * Uploads a song file to the storage and generates a unique filename if needed.
     *
     * @param \Illuminate\Http\UploadedFile $file The uploaded song file.
     * @return array [$path, $filename]
     * */
    public static function uploadSong($file)
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
}
