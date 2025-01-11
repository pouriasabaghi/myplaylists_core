<?php

namespace App\Services;

use App\Models\Artist;
use Illuminate\Support\Facades\Storage;

class ArtistService
{

    public function updateCoverHandler($request, string $artistName){

        $data = $request->validate([
            'file' => 'required|mimetypes:image/jpeg,image/jpg,image/png,image/webp',
        ]);
        $cover = $data['file'];
        $coverPath = $this->uploadCover($cover);

        $artist = Artist::where('name', $artistName)->first();

        if ($artist) {
            Storage::disk('public')->delete($artist->cover);
            $artist->update([
                'cover' => $coverPath,
            ]);

            return response()->json($artist);
        }

        $artist = Artist::create([
            'name' => $artistName,
            'cover' => $coverPath,
        ]);
    }

    public function uploadCover($coverFile): string
    {
        $uploadFilename = $coverFile->getClientOriginalName();
        $fileExists = Artist::where('cover', 'like', "artists/%/$uploadFilename%")->exists();
        if ($fileExists) {
            $uploadFilename = time() . '_' . $uploadFilename;
        }

        // Store the uploaded file in storage
        $folder = date('Y/m');
        $path = $coverFile->storeAs("artists/$folder", $uploadFilename, 'public');

        return $path;
    }
}