<?php
namespace App\Services;

use App\Models\Playlist;
use App\Models\Song;
use Illuminate\Support\Facades\Auth;

class PlaylistService
{
    public function createPlaylist(string $name)
    {
        return Playlist::create([
            'user_id' => Auth::id(),
            'name' => $name,
        ]);
    }

    public function updatePlayList(Playlist $playlist, string $name){
        $playlist->update([
            'name'=>$name
        ]);

        return $playlist;
    }

    public function addSongToPlaylist(Playlist $playlist, Song $song)
    {
        return $playlist->songs()->syncWithoutDetaching([$song->id]);
    }

    public function removeSongFromPlaylist(Playlist $playlist, Song $song)
    {
        return $playlist->songs()->detach($song->id);
    }
}
