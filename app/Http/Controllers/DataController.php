<?php

namespace App\Http\Controllers;

use App\Http\Requests\DataWriteRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use JsonException;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

class DataController extends Controller
{
    private const BASE_PATH = 'courses';

    private const JSON_EXTENSION = '.json';

    private const YAML_EXTENSION = '.yaml';

    private const YAML_SHORT_EXTENSION = '.yml';

    private int $DEBUGLEVEL = 0;

    public function index(): JsonResponse
    {
        $files = collect(Storage::disk('local')->allFiles(self::BASE_PATH))
            ->filter(function (string $path): bool {
                return $this->isJsonPath($path) || $this->isYamlPath($path);
            })
            ->values()
            ->all();

        $this->logDebug('Data files loaded.', [
            'count' => count($files),
            'files' => $files,

        ]);

        return response()->json([
            'count' => count($files),            
            'files' => $files,            
        ]);
    }

    public function show(string $path): Response|JsonResponse
    {
        $storagePath = $this->resolveStoragePath($path);

        if ($this->isCourseIndex($storagePath)) {
            $payload = $this->listCourseSlugs();
            $this->logCoursewareDetails($storagePath, $payload);

            return response()->json($payload);
        }

        $targetPath = $this->resolveExistingPath($storagePath);

        if (! $targetPath) {
            abort(404);
        }

        $contents = Storage::disk('local')->get($targetPath);

        $this->logDebug('Data file loaded.', [
            'path' => $targetPath,
        ]);

        $payload = $this->decodePayload($targetPath, $contents);
        $this->logCoursewareDetails($targetPath, $payload);

        if ($this->isYamlPath($targetPath)) {
            return response()->json($payload);
        }

        return response($contents, 200, [
            'Content-Type' => 'application/json',
        ]);
    }

    public function store(DataWriteRequest $request, string $path): JsonResponse
    {
        $storagePath = $this->resolveStoragePath($path);
        $existingPath = $this->resolveExistingPath($storagePath);

        if ($existingPath) {
            return response()->json([
                'message' => 'File already exists.',
            ], 409);
        }

        $storedPath = $this->writeData($storagePath, $request->validated('data'));

        return response()->json([
            'path' => $storedPath,
        ], 201);
    }

    public function update(DataWriteRequest $request, string $path): JsonResponse
    {
        $storagePath = $this->resolveStoragePath($path);
        $existingPath = $this->resolveExistingPath($storagePath);

        if (! $existingPath) {
            abort(404);
        }

        $storedPath = $this->writeData($existingPath, $request->validated('data'), true);

        return response()->json([
            'path' => $storedPath,
        ]);
    }

    public function destroy(string $path): JsonResponse
    {
        $storagePath = $this->resolveStoragePath($path);

        $targetPath = $this->resolveExistingPath($storagePath);

        if (! $targetPath) {
            abort(404);
        }

        Storage::disk('local')->delete($targetPath);

        return response()->json([
            'deleted' => true,
        ]);
    }

    private function resolveStoragePath(string $path): string
    {
        $normalized = str_replace('\\', '/', $path);
        $normalized = ltrim($normalized, '/');

        if ($normalized === '' || str_contains($normalized, '..')) {
            abort(404);
        }

        if (str_starts_with($normalized, 'courses/')) {
            $normalized = substr($normalized, strlen('courses/'));
        }

        if ($normalized === '') {
            abort(404);
        }

        if (! $this->isJsonPath($normalized) && ! $this->isYamlPath($normalized)) {
            abort(404);
        }

        return self::BASE_PATH.'/'.$normalized;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function writeData(string $path, array $data, bool $preservePath = false): string
    {
        $targetPath = $preservePath ? $path : $this->resolveWritePath($path);

        if ($this->isJsonPath($targetPath)) {
            Storage::disk('local')->put(
                $targetPath,
                json_encode($data, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)
            );

            return $targetPath;
        }

        Storage::disk('local')->put($targetPath, Yaml::dump($data, 8, 2));

        return $targetPath;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function logDebug(string $message, array $context = []): void
    {
        if (! app()->isLocal()) {
            return;
        }

        if ($this->DEBUGLEVEL > 1) {
            Log::info($message, $context);
            error_log($message.' '.json_encode($context));
        }

    }

    private function logCoursewareDetails(string $storagePath, mixed $payload): void
    {
        if (! app()->isLocal()) {
            return;
        }

        if (! is_array($payload)) {
            return;
        }

        if ($this->isCourseIndex($storagePath)) {
            $this->logDebug('Courses found.', [
                'count' => count($payload),
                'courses' => $payload,
            ]);
        }

        if (
            str_ends_with($storagePath, 'chapters.json')
            || str_ends_with($storagePath, 'chapters.yaml')
            || str_ends_with($storagePath, 'chapters.json')
            || str_ends_with($storagePath, 'chapters.yaml')
        ) {
            $this->logDebug('Course metadata loaded.', [
                'title' => $payload['title'] ?? null,
                'chapters' => isset($payload['chapters']) && is_array($payload['chapters'])
                    ? count($payload['chapters'])
                    : null,
            ]);
        }

        if (str_ends_with($storagePath, 'topics.json') && is_array($payload)) {
            $this->logDebug('Topics found.', [
                'count' => count($payload),
                'topics' => $payload,
            ]);
        }
    }

    private function resolveExistingPath(string $storagePath): ?string
    {
        $jsonCandidates = array_unique(array_filter([
            $this->resolveJsonPath($storagePath),
            $this->isJsonPath($storagePath) ? $storagePath : null,
        ]));

        foreach ($jsonCandidates as $candidate) {
            if (Storage::disk('local')->exists($candidate)) {
                return $candidate;
            }
        }

        $yamlCandidates = array_unique(array_filter([
            $this->resolveYamlPath($storagePath),
            $this->isYamlPath($storagePath) ? $storagePath : null,
        ]));

        foreach ($yamlCandidates as $candidate) {
            if (Storage::disk('local')->exists($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function resolveJsonPath(string $storagePath): string
    {
        if (str_ends_with($storagePath, 'chapters.json')) {
            return substr($storagePath, 0, -strlen('chapters.json')).'chapters.json';
        }

        if (str_ends_with($storagePath, 'course.yaml')) {
            return substr($storagePath, 0, -strlen('course.yaml')).'chapters.json';
        }

        if (str_ends_with($storagePath, 'course.yml')) {
            return substr($storagePath, 0, -strlen('course.yml')).'chapters.json';
        }

        if ($this->isYamlPath($storagePath)) {
            return str_ends_with($storagePath, self::YAML_SHORT_EXTENSION)
                ? substr($storagePath, 0, -strlen(self::YAML_SHORT_EXTENSION)).self::JSON_EXTENSION
                : substr($storagePath, 0, -strlen(self::YAML_EXTENSION)).self::JSON_EXTENSION;
        }

        return $storagePath;
    }

    private function resolveYamlPath(string $storagePath): string
    {
        if (str_ends_with($storagePath, 'chapters.json')) {
            return substr($storagePath, 0, -strlen('chapters.json')).'chapters.yaml';
        }

        if (str_ends_with($storagePath, 'course.yaml')) {
            return substr($storagePath, 0, -strlen('course.yaml')).'chapters.yaml';
        }

        if (str_ends_with($storagePath, 'course.yml')) {
            return substr($storagePath, 0, -strlen('course.yml')).'chapters.yaml';
        }

        if ($this->isYamlPath($storagePath)) {
            return str_ends_with($storagePath, self::YAML_SHORT_EXTENSION)
                ? substr($storagePath, 0, -strlen(self::YAML_SHORT_EXTENSION)).self::YAML_EXTENSION
                : $storagePath;
        }

        if ($this->isJsonPath($storagePath)) {
            return substr($storagePath, 0, -strlen(self::JSON_EXTENSION)).self::YAML_EXTENSION;
        }

        return $storagePath;
    }

    private function resolveWritePath(string $storagePath): string
    {
        if ($this->isYamlPath($storagePath)) {
            return $this->resolveYamlPath($storagePath);
        }

        return $this->resolveJsonPath($storagePath);
    }

    private function decodePayload(string $storagePath, string $contents): mixed
    {
        if ($this->isYamlPath($storagePath)) {
            try {
                return Yaml::parse($contents);
            } catch (ParseException) {
                return null;
            }
        }

        try {
            return json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }
    }

    private function isCourseIndex(string $storagePath): bool
    {
        return str_ends_with($storagePath, 'courses/index.json')
            || str_ends_with($storagePath, 'courses/index.yaml')
            || str_ends_with($storagePath, 'courses/index.yml');
    }

    /**
     * @return array<int, array{slug: string}>
     */
    private function listCourseSlugs(): array
    {
        $directories = Storage::disk('local')->directories(self::BASE_PATH);

        $slugs = [];

        foreach ($directories as $path) {
            $slug = basename($path);
            $metadataPath = $this->resolveCourseMetadataPath($slug);

            if ($metadataPath === null) {
                $this->logCourseIndexDecision($slug, false, 'missing chapters.json or course.yaml');

                continue;
            }

            $slugs[] = $slug;
            $this->logCourseIndexDecision($slug, true, $metadataPath);
        }

        sort($slugs);

        $result = array_map(static fn (string $slug): array => ['slug' => $slug], $slugs);

        $this->logDebug("listCourseSlugs ", [
            "directories" => $directories,
            "slugs" => $slugs,
            "result" => $result
        ]);

        return $result;
    }

    private function resolveCourseMetadataPath(string $slug): ?string
    {
        $jsonPath = self::BASE_PATH.'/'.$slug.'/chapters.json';
        if (Storage::disk('local')->exists($jsonPath)) {
            return $jsonPath;
        }

        $yamlPath = self::BASE_PATH.'/'.$slug.'/course.yaml';
        if (Storage::disk('local')->exists($yamlPath)) {
            return $yamlPath;
        }

        $ymlPath = self::BASE_PATH.'/'.$slug.'/course.yml';
        if (Storage::disk('local')->exists($ymlPath)) {
            return $ymlPath;
        }

        return null;
    }

    private function logCourseIndexDecision(string $slug, bool $included, string $detail): void
    {
        if (! app()->isLocal()) {
            return;
        }

        Log::info('Course index entry evaluated.', [
            'slug' => $slug,
            'included' => $included,
            'detail' => $detail,
        ]);
    }

    private function isJsonPath(string $path): bool
    {
        return str_ends_with($path, self::JSON_EXTENSION);
    }

    private function isYamlPath(string $path): bool
    {
        return str_ends_with($path, self::YAML_EXTENSION)
            || str_ends_with($path, self::YAML_SHORT_EXTENSION);
    }
}
