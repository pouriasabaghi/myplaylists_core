<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Song;

class SongPolicy
{

    public function before(User $user, $ability): ?bool{
        if ($user->role === 'admin') {
            return true;
        }

        return null;
    }
    
    public function modify(User $user, Song $song): bool{
        return $user->id === $song->user_id;
    }
}
