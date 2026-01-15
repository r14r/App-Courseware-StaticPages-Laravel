<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('redirects guests to login', function () {
    $response = $this->get(route('quiz.results', [
        'slug' => 'demo-course',
        'chapter' => 'demo-chapter',
    ]));

    $response->assertRedirect(route('login', absolute: false));
});

it('renders the quiz results page', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('quiz.results', [
        'slug' => 'demo-course',
        'chapter' => 'demo-chapter',
    ]));

    $response->assertSuccessful();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Quiz/Results')
        ->where('slug', 'demo-course')
        ->where('chapter', 'demo-chapter'));
});
