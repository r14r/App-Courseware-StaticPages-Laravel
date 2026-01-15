<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CourseTopicProgress>
 */
class CourseTopicProgressFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'course_id' => Course::factory(),
            'chapter_id' => $this->faker->slug(2),
            'topic_id' => $this->faker->slug(3).'.md',
            'completed_at' => $this->faker->optional()->dateTime(),
        ];
    }
}
