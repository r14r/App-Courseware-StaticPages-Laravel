<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DataController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Courses/Index');
})->name('courses.index');

Route::get('/courses/{slug}', function (string $slug) {
    return Inertia::render('Courses/Show', [
        'slug' => $slug,
    ]);
})->name('courses.show');

Route::get('/courses/{slug}/chapters/{chapter}/quiz', function (string $slug, string $chapter) {
    return Inertia::render('Quiz/Show', [
        'slug' => $slug,
        'chapter' => $chapter,
    ]);
})->name('quiz.show');

Route::get('/courses/{slug}/chapters/{chapter}/results', function (string $slug, string $chapter) {
    return Inertia::render('Quiz/Results', [
        'slug' => $slug,
        'chapter' => $chapter,
    ]);
})->name('quiz.results');

Route::get('/data',           [DataController::class, 'index']                       )->name('data.index');
Route::get('/data/{path}',    [DataController::class, 'show']   )->where('path', '.*')->name('data.show');
Route::post('/data/{path}',   [DataController::class, 'store']  )->where('path', '.*')->name('data.store');
Route::put('/data/{path}',    [DataController::class, 'update'] )->where('path', '.*')->name('data.update');
Route::delete('/data/{path}', [DataController::class, 'destroy'])->where('path', '.*')->name('data.destroy');

Route::get('dashboard', DashboardController::class)
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

require __DIR__.'/settings.php';
