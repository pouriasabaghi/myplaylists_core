<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlaylistResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $songsQuery = $this->songs();
        $cover = $songsQuery->whereNotNull('cover')->orderBy('songs.created_at', 'desc')?->value('cover');

        return [
            'id' => $this->id,
            'name' => $this->name,
            'total_songs' => $this->songs_count,
            'cover' => $cover,
            'isFollowed' => $this->followers->isNotEmpty(),
            'isOwner' => $this->user_id !== auth()->user()->id,
            'owner_name' => mb_strimwidth(ucfirst($this->user->name), 0, 10, '...')
        ];
    }
}
