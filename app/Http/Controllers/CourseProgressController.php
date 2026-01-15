<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCourseCompletionRequest;
use App\Http\Requests\StoreQuizResultRequest;
use App\Models\Course;
use App\Models\CourseTopicProgress;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class CourseProgressController extends Controller
{
    public function storeCompletion(StoreCourseCompletionRequest $request): JsonResponse
    {
        $user = $request->user();
        $slug = $request->validated('slug');
        $chapterId = $request->validated('chapter_id');
        $topics = $request->validated('topics', []);

        $course = $this->resolveCourse($slug);

        $user->courses()->syncWithoutDetaching([$course->id => []]);
        $this->updateTakenCourses($user, $course);

        $existing = $user->courses()->whereKey($course->id)->first();
        $completedChapters = $this->normalizeArrayValue($existing?->pivot?->completed_chapters);
        $completedTopics = $this->normalizeArrayValue($existing?->pivot?->completed_topics);

        $updatedChapters = collect($completedChapters)
            ->merge([$chapterId])
            ->unique()
            ->values()
            ->all();

        $updatedTopics = collect($completedTopics)
            ->merge($topics)
            ->unique()
            ->values()
            ->all();

        $this->storeTopicProgress($user, $course, $chapterId, $topics);

        $user->courses()->updateExistingPivot($course->id, [
            'completed_chapters' => $updatedChapters,
            'completed_topics' => $updatedTopics,
        ]);

        return response()->json([
            'updated' => true,
        ]);
    }

    public function storeQuizResults(StoreQuizResultRequest $request): JsonResponse
    {
        $user = $request->user();
        $slug = $request->validated('slug');
        $totalAnswers = $request->validated('total_answers');
        $correctAnswers = $request->validated('correct_answers');

        $course = $this->resolveCourse($slug);

        $user->courses()->syncWithoutDetaching([$course->id => []]);
        $this->updateTakenCourses($user, $course);
        $finalScore = $totalAnswers === 0
            ? 0
            : (int) round(($correctAnswers / $totalAnswers) * 100);

        $user->courses()->updateExistingPivot($course->id, [
            'score' => $correctAnswers,
            'total_answers' => $totalAnswers,
            'correct_answers' => $correctAnswers,
            'final_score' => $finalScore,
        ]);

        return response()->json([
            'updated' => true,
        ]);
    }

    private function resolveCourse(string $slug): Course
    {
        $course = Course::query()->where('slug', $slug)->first();
        if ($course) {
            return $course;
        }

        $title = $this->titleFromSlug($slug);
        $description = null;
        $path = "courses/{$slug}/chapters.json";

        if (Storage::disk('local')->exists($path)) {
            $contents = Storage::disk('local')->get($path);
            $payload = json_decode($contents, true);
            if (is_array($payload)) {
                $title = $payload['title'] ?? $title;
                $description = $payload['description'] ?? null;
            }
        }

        return Course::query()->create([
            'slug' => $slug,
            'title' => $title,
            'description' => $description,
        ]);
    }

    private function titleFromSlug(string $slug): string
    {
        $title = preg_replace('/^[0-9]+-/', '', $slug);
        $title = str_replace('-', ' ', $title ?? $slug);

        return ucwords($title);
    }

    /**
     * @return array<int, int|string>
     */
    private function normalizeArrayValue(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    private function updateTakenCourses(User $user, Course $course): void
    {
        $existing = $this->normalizeArrayValue($user->taken_courses);

        $updated = collect($existing)
            ->merge([$course->id])
            ->unique()
            ->values()
            ->all();

        $user->forceFill([
            'taken_courses' => $updated,
        ])->save();
    }

    /**
     * @param  array<int, string>  $topics
     */
    private function storeTopicProgress(User $user, Course $course, string $chapterId, array $topics): void
    {
        foreach ($topics as $topicKey) {
            [$topicChapter, $topicId] = $this->parseTopicKey($chapterId, $topicKey);

            CourseTopicProgress::query()->updateOrCreate(
                [
                    'user_id' => $user->id,
                    'course_id' => $course->id,
                    'chapter_id' => $topicChapter,
                    'topic_id' => $topicId,
                ],
                [
                    'completed_at' => now(),
                ]
            );
        }
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function parseTopicKey(string $chapterId, string $topicKey): array
    {
        $parts = explode('/', $topicKey, 2);
        if (count($parts) === 2) {
            return [$parts[0], $parts[1]];
        }

        return [$chapterId, $topicKey];
    }
}
