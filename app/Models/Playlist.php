<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;

class Playlist extends Model
{
    protected $fillable = ['user_id', 'name'];

    protected function directLink(): Attribute
    {
        return Attribute::make(
            get: fn($value, $attributes) => config('app.frontend_url') . "/playlists/{$attributes['id']}",
        );
    }


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
