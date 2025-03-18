<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class Song extends Model
{
    protected $fillable = ['duration', 'size', 'cover', 'path', 'name', 'artist', 'album', 'user_id', 'lyrics'];

    protected function cover(): Attribute
    {
        return Attribute::make(
            get: fn($value) => $value ? env('APP_URL_WITH_PORT') . "/storage/{$value}" : null
        );
    }

    protected function path()
    {
        return Attribute::make(
            get: fn($value) => $value ? env('APP_URL_WITH_PORT') . "/storage/{$value}" : null
        );
    }

    protected function directLink(): Attribute
    {
        return Attribute::make(
            get: fn ($value, $attributes) => config('app.frontend_url') . "/songs/{$attributes['id']}",      
        );
    }

    protected function shareLink(): Attribute
    {
        return Attribute::make(
            get: fn ($value, $attributes) => config('app.frontend_url') . "/songs/share/{$attributes['id']}",      
        );
    }

    public function playlists()
    {
        return $this->belongsToMany(Playlist::class);
    }

    public function favorites()
    {
        return $this->belongsToMany(User::class, 'favorites');
    }


    public function user(){
        return $this->belongsTo(User::class);
    }
}
