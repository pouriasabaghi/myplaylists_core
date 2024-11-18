<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class Song extends Model
{
    protected $fillable = ['duration', 'size', 'cover', 'path', 'name', 'artist', 'album', 'user_id'];

    protected function cover(): Attribute{
        return Attribute::make(
            get: fn ($value) => $value ? env('APP_URL_WITH_PORT') . "/storage/{$value}" : null
        );
    }

    protected function path(){
        return Attribute::make(
            get: fn ($value) => $value ? env('APP_URL_WITH_PORT') . "/storage/{$value}" : null
        );
    }

    public function playlists()
    {
        return $this->belongsToMany(Playlist::class);
    }
}
