<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateUserTypeRequest;
use App\Models\Chapter;
use App\Models\Course;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use JsonException;
use League\CommonMark\CommonMarkConverter;

class AdminController extends Controller
{
    private const COURSES_PATH = 'courses';

    public function index(): Response
    {
        return Inertia::render('Admin/Index', [
            'users' => User::query()
                ->select(['id', 'name', 'email', 'user_type', 'created_at'])
                ->orderBy('name')
                ->get(),
            'courses' => Course::query()
                ->withCount('chapters')
                ->orderBy('title')
                ->get(['id', 'slug', 'title', 'description']),
            'chapters' => Chapter::query()
                ->with(['course:id,title'])
                ->withCount('topics')
                ->orderBy('course_id')
                ->orderBy('position')
                ->get(['id', 'course_id', 'slug', 'title', 'position']),
            'topics' => Topic::query()
                ->with(['chapter.course:id,title'])
                ->orderBy('chapter_id')
                ->orderBy('position')
                ->get(['id', 'chapter_id', 'slug', 'title', 'position']),
        ]);
    }

    public function syncCourses(): JsonResponse
    {
        $result = DB::transaction(function (): array {
            $courses = $this->listCourseSlugs();
            $created = 0;
            $updated = 0;
            $chaptersCreated = 0;
            $chaptersUpdated = 0;

            foreach ($courses as $slug) {
                $metadata = $this->readCourseMetadata($slug);
                $course = Course::query()->updateOrCreate(
                    ['slug' => $slug],
                    [
                        'title' => $metadata['title'] ?? $this->titleFromSlug($slug),
                        'description' => $metadata['description'] ?? null,
                    ]
                );

                if ($course->wasRecentlyCreated) {
                    $created++;
                } else {
                    $updated++;
                }

                $chapters = $metadata['chapters'] ?? [];
                $seenChapterSlugs = [];

                foreach ($chapters as $index => $chapter) {
                    $chapterSlug = $chapter['id'] ?? null;
                    if (! is_string($chapterSlug) || $chapterSlug === '') {
                        continue;
                    }

                    $seenChapterSlugs[] = $chapterSlug;

                    $entry = Chapter::query()->updateOrCreate(
                        [
                            'course_id' => $course->id,
                            'slug' => $chapterSlug,
                        ],
                        [
                            'title' => $chapter['title'] ?? $this->titleFromSlug($chapterSlug),
                            'position' => $index,
                        ]
                    );

                    if ($entry->wasRecentlyCreated) {
                        $chaptersCreated++;
                    } else {
                        $chaptersUpdated++;
                    }
                }

                if ($seenChapterSlugs !== []) {
                    Chapter::query()
                        ->where('course_id', $course->id)
                        ->whereNotIn('slug', $seenChapterSlugs)
                        ->delete();
                }
            }

            return [
                'courses_created' => $created,
                'courses_updated' => $updated,
                'chapters_created' => $chaptersCreated,
                'chapters_updated' => $chaptersUpdated,
            ];
        });

        return response()->json([
            'synced' => true,
            'result' => $result,
        ]);
    }

    public function syncTopics(): JsonResponse
    {
        $result = DB::transaction(function (): array {
            $created = 0;
            $updated = 0;

            $chapters = Chapter::query()
                ->with('course:id,slug')
                ->get();

            foreach ($chapters as $chapter) {
                $courseSlug = $chapter->course?->slug;
                if (! $courseSlug) {
                    continue;
                }

                $topicsIndex = $this->readTopicsIndex($courseSlug, $chapter->slug);
                if ($topicsIndex === null) {
                    continue;
                }

                $seenTopicSlugs = [];

                foreach ($topicsIndex as $index => $topicEntry) {
                    $topicSlug = $topicEntry['file'] ?? null;
                    if (! is_string($topicSlug) || $topicSlug === '') {
                        continue;
                    }

                    $seenTopicSlugs[] = $topicSlug;
                    $topicPayload = $this->readTopicPayload($courseSlug, $chapter->slug, $topicSlug);

                    $topic = Topic::query()->updateOrCreate(
                        [
                            'chapter_id' => $chapter->id,
                            'slug' => $topicSlug,
                        ],
                        [
                            'title' => $topicPayload['title'] ?? $topicEntry['title'] ?? $this->titleFromSlug($topicSlug),
                            'content' => $topicPayload['content'] ?? null,
                            'content_html' => $topicPayload['content_html'] ?? null,
                            'position' => $index,
                        ]
                    );

                    if ($topic->wasRecentlyCreated) {
                        $created++;
                    } else {
                        $updated++;
                    }
                }

                if ($seenTopicSlugs !== []) {
                    Topic::query()
                        ->where('chapter_id', $chapter->id)
                        ->whereNotIn('slug', $seenTopicSlugs)
                        ->delete();
                }
            }

            return [
                'topics_created' => $created,
                'topics_updated' => $updated,
            ];
        });

        return response()->json([
            'synced' => true,
            'result' => $result,
        ]);
    }

    public function updateUserType(UpdateUserTypeRequest $request, User $user): JsonResponse
    {
        $user->update($request->validated());

        return response()->json([
            'updated' => true,
            'user' => [
                'id' => $user->id,
                'user_type' => $user->user_type?->value ?? $user->user_type,
            ],
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function listCourseSlugs(): array
    {
        $directories = Storage::disk('local')->directories(self::COURSES_PATH);

        return array_map('basename', $directories);
    }

    /**
     * @return array<string, mixed>
     */
    private function readCourseMetadata(string $slug): array
    {
        $path = self::COURSES_PATH.'/'.$slug.'/chapters.json';
        if (! Storage::disk('local')->exists($path)) {
            return [];
        }

        try {
            return json_decode(Storage::disk('local')->get($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return [];
        }
    }

    /**
     * @return array<int, array{file?: string, title?: string}>|null
     */
    private function readTopicsIndex(string $courseSlug, string $chapterSlug): ?array
    {
        $path = self::COURSES_PATH.'/'.$courseSlug.'/'.$chapterSlug.'/topics.json';
        if (! Storage::disk('local')->exists($path)) {
            return null;
        }

        try {
            $payload = json_decode(Storage::disk('local')->get($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        if (! is_array($payload)) {
            return null;
        }

        return array_map(function ($entry): array {
            if (is_string($entry)) {
                return ['file' => $entry];
            }

            return is_array($entry) ? $entry : [];
        }, $payload);
    }

    /**
     * @return array{title?: string, content?: array<int, string>|null, content_html?: string|null}
     */
    private function readTopicPayload(string $courseSlug, string $chapterSlug, string $topicSlug): array
    {
        $path = self::COURSES_PATH.'/'.$courseSlug.'/'.$chapterSlug.'/'.$topicSlug;
        if (! Storage::disk('local')->exists($path)) {
            return [];
        }

        if (str_ends_with($topicSlug, '.md')) {
            return $this->readMarkdownTopic($path);
        }

        if (str_ends_with($topicSlug, '.json')) {
            return $this->readJsonTopic($path);
        }

        return [];
    }

    /**
     * @return array{title?: string, content?: array<int, string>|null, content_html?: string|null}
     */
    private function readJsonTopic(string $path): array
    {
        try {
            $payload = json_decode(Storage::disk('local')->get($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return [];
        }

        if (! is_array($payload)) {
            return [];
        }

        return [
            'title' => $payload['title'] ?? null,
            'content' => $payload['content'] ?? null,
            'content_html' => $payload['contentHtml'] ?? null,
        ];
    }

    /**
     * @return array{title?: string, content_html?: string|null}
     */
    private function readMarkdownTopic(string $path): array
    {
        $contents = Storage::disk('local')->get($path);
        [$title, $body] = $this->splitMarkdown($contents);
        $converter = new CommonMarkConverter;
        $html = trim($converter->convert($body)->getContent());

        return [
            'title' => $title,
            'content_html' => $html !== '' ? $html : null,
        ];
    }

    /**
     * @return array{0: string|null, 1: string}
     */
    private function splitMarkdown(string $contents): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $contents) ?: [];
        $title = null;
        $bodyLines = [];
        $seenContent = false;

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if (! $seenContent && $trimmed === '') {
                continue;
            }

            if (! $seenContent && str_starts_with($trimmed, '# ')) {
                $title = trim(substr($trimmed, 2));
                $seenContent = true;

                continue;
            }

            $seenContent = true;
            $bodyLines[] = $line;
        }

        return [$title, implode("\n", $bodyLines)];
    }

    private function titleFromSlug(string $slug): string
    {
        $title = preg_replace('/^[0-9]+-/', '', $slug);
        $title = str_replace('-', ' ', $title ?? $slug);

        return ucwords($title);
    }
}
