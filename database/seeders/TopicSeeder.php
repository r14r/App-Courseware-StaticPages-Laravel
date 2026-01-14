<?php

namespace Database\Seeders;

use App\Models\Chapter;
use App\Models\Topic;
use Illuminate\Database\Seeder;

class TopicSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $chapters = Chapter::query()->get();

        if ($chapters->isEmpty()) {
            $chapters = Chapter::factory()->count(3)->create();
        }

        foreach ($chapters as $chapter) {
            Topic::factory()->count(5)->create([
                'chapter_id' => $chapter->id,
            ]);
        }
    }
}
