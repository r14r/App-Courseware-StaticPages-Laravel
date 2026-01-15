<?php

use App\Enums\UserType;
use App\Models\Chapter;
use App\Models\Course;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

test('guests are redirected away from admin routes', function () {
    $this->get('/admin')->assertRedirect(route('login'));
});

test('non-admin users are forbidden from admin routes', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/admin')->assertForbidden();
    $this->actingAs($user)->post('/admin/sync/courses')->assertForbidden();
    $this->actingAs($user)->post('/admin/sync/topics')->assertForbidden();
});

test('admins can sync courses and topics from storage', function () {
    Storage::fake('local');

    Storage::disk('local')->put('courses/demo-course/chapters.json', json_encode([
        'id' => 'demo-course',
        'title' => 'Demo Course',
        'description' => 'Demo course description.',
        'chapters' => [
            ['id' => '001-intro', 'title' => 'Introduction'],
        ],
    ], JSON_THROW_ON_ERROR));

    Storage::disk('local')->put('courses/demo-course/001-intro/topics.json', json_encode([
        ['file' => '001-welcome.md', 'title' => 'Welcome Topic'],
    ], JSON_THROW_ON_ERROR));

    Storage::disk('local')->put('courses/demo-course/001-intro/001-welcome.md', "# Welcome\n\nHello world.");

    $admin = User::factory()->create(['user_type' => UserType::Admin]);

    $this->actingAs($admin)->post('/admin/sync/courses')->assertSuccessful();

    $course = Course::query()->where('slug', 'demo-course')->first();
    $chapter = Chapter::query()->where('slug', '001-intro')->first();

    expect($course)->not->toBeNull()
        ->and($course?->title)->toBe('Demo Course');
    expect($chapter)->not->toBeNull()
        ->and($chapter?->course_id)->toBe($course?->id);

    $this->actingAs($admin)->post('/admin/sync/topics')->assertSuccessful();

    $topic = Topic::query()->where('slug', '001-welcome.md')->first();

    expect($topic)->not->toBeNull()
        ->and($topic?->title)->toBe('Welcome');
});
