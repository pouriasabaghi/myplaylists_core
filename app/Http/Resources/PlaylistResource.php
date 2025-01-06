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
        $cover = $this->songs()->whereNotNull('cover')->orderByDesc('id')->first()?->cover;
        
        return [
            'id' => $this->id,
            'name' => $this->name,
            'songs' => $this->songs,
            'total_songs' => $this->songs->count(),
            'cover' => $cover,
            'isFollowed' => $this->user_id !== auth()->user()->id,
            'owner_name' =>mb_strimwidth(ucfirst($this->user->name), 0, 10,'...' )
        ];
    }
}
