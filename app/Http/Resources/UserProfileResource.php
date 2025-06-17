<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserProfileResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return[
            'name'=>$this->nickname ?: $this->name,
            'avatar'=>$this->avatar,
            'banner'=>$this->banner,
            'bio'=>$this->bio,
            'latest_playlists'=>PlaylistResource::collection($this->playlists()->withCount('songs')->latest()->get()),
            'latest_songs'=>SongResource::collection($this->songs()->latest()->take(20)->get()),
        ];
    }
}
