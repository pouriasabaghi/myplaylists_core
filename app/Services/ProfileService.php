<?php

namespace App\Services;

use App\Models\User;
use Intervention\Image\Laravel\Facades\Image;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Encoders\JpegEncoder;

class ProfileService
{
    public function upload($image, $destination, $width, $height)
    {
        $folder = date('Y/m/d');
        $imageName = uniqid() . '.jpg';
        $relativePath = "$destination/$folder/$imageName";

        // Resize with Intervention Image
        $img = Image::read($image)
            ->cover($width, $height, 'center')
            ->encode(new JpegEncoder(quality:90)); 

        Storage::disk('public')->put($relativePath, (string) $img);

        return $relativePath; 
    }
}