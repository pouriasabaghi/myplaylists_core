<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Playlist extends Model
{
    protected $fillable = ['user_id', 'name'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function songs()
    {
        return $this->belongsToMany(Song::class);
    }

    public function followers()
    {
        return $this->belongsToMany(User::class, 'follows', 'playlist_id', 'user_id');
    }

    public function followersCount()
    {
        return $this->followers()->count();
    }
}
