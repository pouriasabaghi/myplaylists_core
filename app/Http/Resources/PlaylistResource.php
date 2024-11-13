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
        $songs = $this->songs;
        return [
            'id' => $this->id,
            'name' => $this->name,
            'songs' => $songs,
            'total_songs' => $songs->count(),
            'cover' => $songs->last()?->cover ? env('APP_URL_WITH_PORT') . "/storage/{$songs->last()?->cover}" : null
        ];
    }
}