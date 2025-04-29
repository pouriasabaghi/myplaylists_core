<?php

namespace App\Policies;

use App\Models\Playlist;
use App\Models\User;

class PlaylistPolicy
{
    public function before(User $user, $ability): ?bool{
        if ($user->role === 'admin') {
            return true;
        }

        return null;
    }

    public function modify(User $user, Playlist $playlist): bool
    {
        return $user->id === $playlist->user_id;
    }
}
