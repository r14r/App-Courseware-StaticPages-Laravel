<?php

use App\Models\Course;
use App\Models\User;

it('redirects guests to login', function () {
    $response = $this->get('/dashboard');

    $response->assertRedirect();
});

it('shows user courses and scores', function () {
    $user = User::factory()->create();
    $course = Course::factory()->create([
        'title' => 'Intro to Laravel',
    ]);

    $user->courses()->attach($course->id, ['score' => 88]);

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertSuccessful();
    $response->assertSee('Intro to Laravel');
    $response->assertSee('88');
});
