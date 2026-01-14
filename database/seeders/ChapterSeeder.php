<?php

namespace Database\Seeders;

use App\Models\Chapter;
use App\Models\Course;
use Illuminate\Database\Seeder;

class ChapterSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $courses = Course::query()->get();

        if ($courses->isEmpty()) {
            $courses = Course::factory()->count(2)->create();
        }

        foreach ($courses as $course) {
            Chapter::factory()->count(3)->create([
                'course_id' => $course->id,
            ]);
        }
    }
}
