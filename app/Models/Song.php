<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Song extends Model
{
    protected $fillable = ['duration', 'size', 'cover', 'path', 'name', 'artist', 'album', 'user_id'];


    public function playlists()
    {
        return $this->belongsToMany(Playlist::class);
    }
}
