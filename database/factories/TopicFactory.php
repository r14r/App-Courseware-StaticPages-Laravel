<?php

namespace Database\Factories;

use App\Models\Chapter;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Topic>
 */
class TopicFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'chapter_id' => Chapter::factory(),
            'title' => $this->faker->sentence(4),
            'content' => [
                $this->faker->paragraph(),
                $this->faker->paragraph(),
            ],
            'content_html' => null,
            'position' => $this->faker->numberBetween(1, 50),
        ];
    }
}
