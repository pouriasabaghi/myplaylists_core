<?php
namespace App\Services;

use App\Models\Playlist;
use App\Models\Song;
use Illuminate\Support\Facades\Auth;
use \App\Jobs\SendNotificationsJob;
use \App\Notifications\PlaylistUpdated as PlaylistUpdatedNotification;

class PlaylistService
{
    public function createPlaylist(string $name)
    {
        return Playlist::create([
            'user_id' => Auth::id(),
            'name' => $name,
        ]);
    }

    public function updatePlayList(Playlist $playlist, string $name)
    {
        $playlist->update([
            'name' => $name
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

    public function notifyFollowers(Playlist $playlist)
    {
        // Prevent multiple notification for the same playlist in period of time
        $lock = cache()->lock("playlist_updated_$playlist->id", now()->endOfDay()->second);
        if ($lock->get()) {
            $notification = new PlaylistUpdatedNotification($playlist);

            $followers = $playlist->followers()->with('telegram')->get();
            foreach ($followers as $follower)
                $follower->notify($notification);
        }
    }
}
