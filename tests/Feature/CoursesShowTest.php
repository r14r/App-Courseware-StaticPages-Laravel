<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('redirects guests to login', function () {
    $response = $this->get(route('courses.show', [
        'slug' => 'demo-course',
    ]));

    $response->assertRedirect(route('login', absolute: false));
});

it('renders the course show page', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('courses.show', [
        'slug' => 'demo-course',
    ]));

    $response->assertSuccessful();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Courses/Show')
        ->where('slug', 'demo-course'));
});
