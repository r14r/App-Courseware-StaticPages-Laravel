<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $this->call([
            CourseSeeder::class,
            ChapterSeeder::class,
            TopicSeeder::class,
        ]);

        $courses = Course::query()->take(3)->get();
        $enrollments = $courses->mapWithKeys(static function (Course $course): array {
            return [
                $course->id => [
                    'score' => fake()->numberBetween(60, 100),
                ],
            ];
        })->all();

        $user->courses()->syncWithoutDetaching($enrollments);
    }
}
