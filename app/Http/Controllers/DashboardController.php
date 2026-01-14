<?php

namespace App\Http\Controllers;

use App\Models\Course;
use Illuminate\Http\Request;
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
            ->map(static function (Course $course): array {
                return [
                    'id' => $course->id,
                    'slug' => $course->slug,
                    'title' => $course->title,
                    'description' => $course->description,
                    'score' => $course->pivot?->score,
                    'chapters' => $course->chapters_count,
                ];
            });

        return Inertia::render('Dashboard', [
            'courses' => $courses,
        ]);
    }
}
