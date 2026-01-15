<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\CourseProgressController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DataController;
use App\Http\Middleware\EnsureUserIsAdmin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Courses/Index');
})->name('courses.index');

Route::get('/courses/{slug}', function (string $slug) {
    return Inertia::render('Courses/Show', [
        'slug' => $slug,
    ]);
})->middleware(['auth'])->name('courses.show');

Route::get('/courses/{slug}/chapters/{chapter}/quiz', function (string $slug, string $chapter) {
    return Inertia::render('Quiz/Show', [
        'slug' => $slug,
        'chapter' => $chapter,
    ]);
})->middleware(['auth'])->name('quiz.show');

Route::get('/courses/{slug}/chapters/{chapter}/results', function (string $slug, string $chapter) {
    return Inertia::render('Quiz/Results', [
        'slug' => $slug,
        'chapter' => $chapter,
    ]);
})->middleware(['auth'])->name('quiz.results');

Route::get('/data', [DataController::class, 'index'])->name('data.index');
Route::get('/data/{path}', [DataController::class, 'show'])->where('path', '.*')->name('data.show');
Route::post('/data/{path}', [DataController::class, 'store'])->where('path', '.*')->name('data.store');
Route::put('/data/{path}', [DataController::class, 'update'])->where('path', '.*')->name('data.update');
Route::delete('/data/{path}', [DataController::class, 'destroy'])->where('path', '.*')->name('data.destroy');

Route::get('dashboard', DashboardController::class)
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::post('/progress/completion', [CourseProgressController::class, 'storeCompletion'])
        ->name('progress.completion');
    Route::post('/progress/results', [CourseProgressController::class, 'storeQuizResults'])
        ->name('progress.results');
});

Route::middleware(['auth', EnsureUserIsAdmin::class])->group(function () {
    Route::get('/admin', [AdminController::class, 'index'])->name('admin.index');
    Route::patch('/admin/users/{user}', [AdminController::class, 'updateUserType'])->name('admin.users.update');
    Route::post('/admin/sync/courses', [AdminController::class, 'syncCourses'])->name('admin.sync.courses');
    Route::post('/admin/sync/topics', [AdminController::class, 'syncTopics'])->name('admin.sync.topics');
});

Route::get('/docs', function () {
    return view('docs');
})->name('docs.index');

Route::get('/docs/openapi.json', function (Request $request) {
    $serverUrl = $request->getSchemeAndHttpHost();

    return response()->json([
        'openapi' => '3.0.3',
        'info' => [
            'title' => 'Courseware API',
            'version' => '1.0.0',
            'description' => 'File-based course and quiz APIs for the Courseware app.',
        ],
        'servers' => [
            ['url' => $serverUrl],
        ],
        'paths' => [
            '/api/course' => [
                'get' => [
                    'summary' => 'List courses',
                    'responses' => [
                        '200' => [
                            'description' => 'List of courses',
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        '$ref' => '#/components/schemas/CourseListResponse',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'security' => [['cookieAuth' => []]],
                ],
                'post' => [
                    'summary' => 'Create course',
                    'requestBody' => [
                        'required' => true,
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    '$ref' => '#/components/schemas/CourseCreateRequest',
                                ],
                            ],
                        ],
                    ],
                    'responses' => [
                        '201' => [
                            'description' => 'Created course',
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        '$ref' => '#/components/schemas/CourseResponse',
                                    ],
                                ],
                            ],
                        ],
                        '409' => ['description' => 'Course already exists'],
                    ],
                    'security' => [['cookieAuth' => []]],
                ],
            ],
            '/api/course/{course}' => [
                'get' => [
                    'summary' => 'Get course',
                    'parameters' => [
                        [
                            'name' => 'course',
                            'in' => 'path',
                            'required' => true,
                            'schema' => ['type' => 'string'],
                        ],
                    ],
                    'responses' => [
                        '200' => [
                            'description' => 'Course details',
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        '$ref' => '#/components/schemas/CourseResponse',
                                    ],
                                ],
                            ],
                        ],
                        '404' => ['description' => 'Course not found'],
                    ],
                    'security' => [['cookieAuth' => []]],
                ],
                'put' => [
                    'summary' => 'Update course',
                    'parameters' => [
                        [
                            'name' => 'course',
                            'in' => 'path',
                            'required' => true,
                            'schema' => ['type' => 'string'],
                        ],
                    ],
                    'requestBody' => [
                        'required' => true,
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    '$ref' => '#/components/schemas/CourseUpdateRequest',
                                ],
                            ],
                        ],
                    ],
                    'responses' => [
                        '200' => [
                            'description' => 'Updated course',
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        '$ref' => '#/components/schemas/CourseResponse',
                                    ],
                                ],
                            ],
                        ],
                        '404' => ['description' => 'Course not found'],
                    ],
                    'security' => [['cookieAuth' => []]],
                ],
                'delete' => [
                    'summary' => 'Delete course',
                    'parameters' => [
                        [
                            'name' => 'course',
                            'in' => 'path',
                            'required' => true,
                            'schema' => ['type' => 'string'],
                        ],
                    ],
                    'responses' => [
                        '200' => [
                            'description' => 'Deleted',
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'deleted' => ['type' => 'boolean'],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        '404' => ['description' => 'Course not found'],
                    ],
                    'security' => [['cookieAuth' => []]],
                ],
            ],
            '/api/course/{course}/chapters/{chapter}/quiz' => [
                'get' => [
                    'summary' => 'Get quiz',
                    'parameters' => [
                        [
                            'name' => 'course',
                            'in' => 'path',
                            'required' => true,
                            'schema' => ['type' => 'string'],
                        ],
                        [
                            'name' => 'chapter',
                            'in' => 'path',
                            'required' => true,
                            'schema' => ['type' => 'string'],
                        ],
                    ],
                    'responses' => [
                        '200' => [
                            'description' => 'Quiz payload',
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        '$ref' => '#/components/schemas/QuizResponse',
                                    ],
                                ],
                            ],
                        ],
                        '404' => ['description' => 'Quiz not found'],
                    ],
                    'security' => [['cookieAuth' => []]],
                ],
                'put' => [
                    'summary' => 'Upsert quiz',
                    'parameters' => [
                        [
                            'name' => 'course',
                            'in' => 'path',
                            'required' => true,
                            'schema' => ['type' => 'string'],
                        ],
                        [
                            'name' => 'chapter',
                            'in' => 'path',
                            'required' => true,
                            'schema' => ['type' => 'string'],
                        ],
                    ],
                    'requestBody' => [
                        'required' => true,
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    '$ref' => '#/components/schemas/QuizRequest',
                                ],
                            ],
                        ],
                    ],
                    'responses' => [
                        '200' => [
                            'description' => 'Quiz payload',
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        '$ref' => '#/components/schemas/QuizResponse',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'security' => [['cookieAuth' => []]],
                ],
                'delete' => [
                    'summary' => 'Delete quiz',
                    'parameters' => [
                        [
                            'name' => 'course',
                            'in' => 'path',
                            'required' => true,
                            'schema' => ['type' => 'string'],
                        ],
                        [
                            'name' => 'chapter',
                            'in' => 'path',
                            'required' => true,
                            'schema' => ['type' => 'string'],
                        ],
                    ],
                    'responses' => [
                        '200' => [
                            'description' => 'Deleted',
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'deleted' => ['type' => 'boolean'],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        '404' => ['description' => 'Quiz not found'],
                    ],
                    'security' => [['cookieAuth' => []]],
                ],
            ],
        ],
        'components' => [
            'securitySchemes' => [
                'cookieAuth' => [
                    'type' => 'apiKey',
                    'in' => 'cookie',
                    'name' => 'laravel_session',
                ],
            ],
            'schemas' => [
                'CourseListResponse' => [
                    'type' => 'object',
                    'properties' => [
                        'data' => [
                            'type' => 'array',
                            'items' => [
                                '$ref' => '#/components/schemas/CourseSummary',
                            ],
                        ],
                    ],
                ],
                'CourseSummary' => [
                    'type' => 'object',
                    'properties' => [
                        'slug' => ['type' => 'string'],
                        'title' => ['type' => 'string', 'nullable' => true],
                        'description' => ['type' => 'string', 'nullable' => true],
                    ],
                ],
                'CourseResponse' => [
                    'type' => 'object',
                    'properties' => [
                        'data' => [
                            '$ref' => '#/components/schemas/Course',
                        ],
                    ],
                ],
                'Course' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'string'],
                        'slug' => ['type' => 'string'],
                        'title' => ['type' => 'string', 'nullable' => true],
                        'description' => ['type' => 'string', 'nullable' => true],
                        'chapters' => [
                            'type' => 'array',
                            'items' => [
                                '$ref' => '#/components/schemas/Chapter',
                            ],
                        ],
                    ],
                ],
                'Chapter' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'string'],
                        'title' => ['type' => 'string'],
                    ],
                ],
                'CourseCreateRequest' => [
                    'type' => 'object',
                    'required' => ['slug', 'title'],
                    'properties' => [
                        'slug' => ['type' => 'string'],
                        'title' => ['type' => 'string'],
                        'description' => ['type' => 'string', 'nullable' => true],
                        'chapters' => [
                            'type' => 'array',
                            'items' => [
                                '$ref' => '#/components/schemas/Chapter',
                            ],
                        ],
                    ],
                ],
                'CourseUpdateRequest' => [
                    'type' => 'object',
                    'properties' => [
                        'title' => ['type' => 'string'],
                        'description' => ['type' => 'string', 'nullable' => true],
                        'chapters' => [
                            'type' => 'array',
                            'items' => [
                                '$ref' => '#/components/schemas/Chapter',
                            ],
                        ],
                    ],
                ],
                'QuizRequest' => [
                    'type' => 'object',
                    'required' => ['title', 'questions'],
                    'properties' => [
                        'title' => ['type' => 'string'],
                        'questions' => [
                            'type' => 'array',
                            'items' => [
                                '$ref' => '#/components/schemas/QuizQuestion',
                            ],
                        ],
                    ],
                ],
                'QuizResponse' => [
                    'type' => 'object',
                    'properties' => [
                        'data' => [
                            '$ref' => '#/components/schemas/QuizPayload',
                        ],
                    ],
                ],
                'QuizPayload' => [
                    'type' => 'object',
                    'properties' => [
                        'title' => ['type' => 'string'],
                        'questions' => [
                            'type' => 'array',
                            'items' => [
                                '$ref' => '#/components/schemas/QuizQuestion',
                            ],
                        ],
                    ],
                ],
                'QuizQuestion' => [
                    'type' => 'object',
                    'required' => ['question', 'options', 'correctIndex'],
                    'properties' => [
                        'id' => ['type' => 'string', 'nullable' => true],
                        'type' => ['type' => 'string', 'nullable' => true],
                        'question' => ['type' => 'string'],
                        'options' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                        ],
                        'correctIndex' => ['type' => 'integer'],
                        'explanation' => ['type' => 'string', 'nullable' => true],
                    ],
                ],
            ],
        ],
    ]);
})->name('docs.openapi');

require __DIR__.'/settings.php';
