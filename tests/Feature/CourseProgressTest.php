<?php

use App\Models\Course;
use App\Models\CourseTopicProgress;
use App\Models\User;

it('stores chapter completion on the course pivot', function () {
    $user = User::factory()->create();
    $course = Course::factory()->create([
        'slug' => 'demo-course',
    ]);

    $this->actingAs($user)
        ->postJson(route('progress.completion'), [
            'slug' => 'demo-course',
            'chapter_id' => '001-intro',
            'topics' => ['001-intro/001-topic.json', '001-intro/002-topic.json'],
        ])
        ->assertSuccessful();

    $user->refresh();
    $pivot = $user->courses()->whereKey($course->id)->first()?->pivot;
    $completedChapters = $pivot?->completed_chapters;
    if (is_string($completedChapters)) {
        $completedChapters = json_decode($completedChapters, true) ?? [];
    }
    $completedTopics = $pivot?->completed_topics;
    if (is_string($completedTopics)) {
        $completedTopics = json_decode($completedTopics, true) ?? [];
    }

    expect($pivot)->not->toBeNull()
        ->and($completedChapters)->toContain('001-intro')
        ->and($completedTopics)->toContain('001-intro/001-topic.json', '001-intro/002-topic.json');
    expect($user->taken_courses)->toContain($course->id);

    $topicProgress = CourseTopicProgress::query()
        ->where('user_id', $user->id)
        ->where('course_id', $course->id)
        ->get();

    expect($topicProgress)->toHaveCount(2);
    expect($topicProgress->pluck('topic_id')->all())->toContain('001-topic.json', '002-topic.json');
});

it('stores quiz results on the course pivot', function () {
    $user = User::factory()->create();
    $course = Course::factory()->create([
        'slug' => 'demo-course',
    ]);

    $this->actingAs($user)
        ->postJson(route('progress.results'), [
            'slug' => 'demo-course',
            'total_answers' => 5,
            'correct_answers' => 4,
        ])
        ->assertSuccessful();

    $user->refresh();
    $pivot = $user->courses()->whereKey($course->id)->first()?->pivot;

    expect($pivot)->not->toBeNull()
        ->and($pivot->total_answers)->toBe(5)
        ->and($pivot->correct_answers)->toBe(4)
        ->and($pivot->score)->toBe(4)
        ->and($pivot->final_score)->toBe(80);
    expect($user->taken_courses)->toContain($course->id);
});
