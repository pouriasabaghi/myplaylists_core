<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PlaylistSongSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $playlist = \App\Models\Playlist::orderBy('id', 'desc')->first();

        if (!$playlist) {
            $this->command->error('Playlist not found');
        }

        $songs = \App\Models\Song::factory(50)->create();

        $playlist->songs()->syncWithoutDetaching($songs->pluck('id')->toArray());

        $this->command->info('300 songs attached successfully.');
    }
}
