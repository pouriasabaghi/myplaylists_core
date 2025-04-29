<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Song>
 */
class SongFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->title(),
            'duration' => $this->faker->numberBetween(150, 200),
            'cover' => 'covers/2025/03/67e5b434ac9b6.jpg',
            'path' => 'songs/2025/04/1743855898_1742561690_file_91.mp3',
            'user_id'=>2
        ];
    }
}
