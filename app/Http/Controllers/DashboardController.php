<?php

namespace App\Http\Controllers;

use App\Models\Course;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $courses = $request->user()
            ->courses()
            ->withCount('chapters')
            ->orderBy('title')
            ->get()
            ->map(function (Course $course): array {
                $chaptersCount = $course->chapters_count;
                $chapters = [];
                $path = "courses/{$course->slug}/chapters.json";
                if (Storage::disk('local')->exists($path)) {
                    $payload = json_decode(Storage::disk('local')->get($path), true);
                    if (is_array($payload) && isset($payload['chapters']) && is_array($payload['chapters'])) {
                        $chaptersCount = count($payload['chapters']);
                        $chapters = $payload['chapters'];
                    }
                }

                return [
                    'id' => $course->id,
                    'slug' => $course->slug,
                    'title' => $course->title,
                    'description' => $course->description,
                    'score' => $course->pivot?->score,
                    'total_answers' => $course->pivot?->total_answers,
                    'correct_answers' => $course->pivot?->correct_answers,
                    'final_score' => $course->pivot?->final_score,
                    'chapters' => $chaptersCount,
                    'completed_chapters' => $this->countProgressValue($course->pivot?->completed_chapters),
                    'completed_topics' => $this->countProgressValue($course->pivot?->completed_topics),
                    'total_topics' => $this->countTotalTopics($course->slug, $chapters),
                ];
            });

        return Inertia::render('Dashboard', [
            'courses' => $courses,
        ]);
    }

    private function countProgressValue(mixed $value): int
    {
        if (is_array($value)) {
            return count($value);
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);

            return is_array($decoded) ? count($decoded) : 0;
        }

        return 0;
    }

    /**
     * @param  array<int, array<string, mixed>>  $chapters
     */
    private function countTotalTopics(string $slug, array $chapters): int
    {
        $total = 0;

        foreach ($chapters as $chapter) {
            if (! is_array($chapter)) {
                continue;
            }

            $chapterId = $chapter['id'] ?? null;
            if (! is_string($chapterId) || $chapterId === '') {
                continue;
            }

            $topicsPath = "courses/{$slug}/{$chapterId}/topics.json";
            if (! Storage::disk('local')->exists($topicsPath)) {
                continue;
            }

            $payload = json_decode(Storage::disk('local')->get($topicsPath), true);
            if (! is_array($payload)) {
                continue;
            }

            $total += count($payload);
        }

        return $total;
    }
}
