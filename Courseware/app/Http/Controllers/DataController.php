<?php

namespace App\Http\Controllers;

use App\Http\Requests\DataWriteRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use JsonException;

class DataController extends Controller
{
    private const BASE_PATH = 'data';

    public function index(): JsonResponse
    {
        $files = collect(Storage::disk('local')->allFiles(self::BASE_PATH))
            ->filter(static fn (string $path): bool => str_ends_with($path, '.json'))
            ->values()
            ->all();

        $this->logDebug('Data files loaded.', [
            'count' => count($files),
            'files' => $files,
        ]);

        return response()->json([
            'files' => $files,
        ]);
    }

    public function show(string $path): Response
    {
        $storagePath = $this->resolveStoragePath($path);

        if (! Storage::disk('local')->exists($storagePath)) {
            abort(404);
        }

        $contents = Storage::disk('local')->get($storagePath);

        $this->logDebug('Data file loaded.', [
            'path' => $storagePath,
        ]);

        $this->logCoursewareDetails($storagePath, $contents);

        return response($contents, 200, [
            'Content-Type' => 'application/json',
        ]);
    }

    public function store(DataWriteRequest $request, string $path): JsonResponse
    {
        $storagePath = $this->resolveStoragePath($path);

        if (Storage::disk('local')->exists($storagePath)) {
            return response()->json([
                'message' => 'File already exists.',
            ], 409);
        }

        $this->writeJson($storagePath, $request->validated('data'));

        return response()->json([
            'path' => $storagePath,
        ], 201);
    }

    public function update(DataWriteRequest $request, string $path): JsonResponse
    {
        $storagePath = $this->resolveStoragePath($path);

        if (! Storage::disk('local')->exists($storagePath)) {
            abort(404);
        }

        $this->writeJson($storagePath, $request->validated('data'));

        return response()->json([
            'path' => $storagePath,
        ]);
    }

    public function destroy(string $path): JsonResponse
    {
        $storagePath = $this->resolveStoragePath($path);

        if (! Storage::disk('local')->exists($storagePath)) {
            abort(404);
        }

        Storage::disk('local')->delete($storagePath);

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

        if (! str_ends_with($normalized, '.json')) {
            abort(404);
        }

        return self::BASE_PATH.'/'.$normalized;
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws JsonException
     */
    private function writeJson(string $path, array $data): void
    {
        Storage::disk('local')->put(
            $path,
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
        );
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function logDebug(string $message, array $context = []): void
    {
        if (! app()->isLocal()) {
            return;
        }

        Log::info($message, $context);
        error_log($message.' '.json_encode($context));
    }

    private function logCoursewareDetails(string $storagePath, string $contents): void
    {
        if (! app()->isLocal()) {
            return;
        }

        try {
            $payload = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return;
        }

        if (str_ends_with($storagePath, 'courses/index.json') && is_array($payload)) {
            $this->logDebug('Courses found.', [
                'count' => count($payload),
                'courses' => $payload,
            ]);
        }

        if (str_ends_with($storagePath, 'course.json') && is_array($payload)) {
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
}
