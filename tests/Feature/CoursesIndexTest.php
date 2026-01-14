<?php

use Inertia\Testing\AssertableInertia as Assert;

it('renders the course index page', function () {
    $response = $this->get('/');

    $response->assertSuccessful();
    $response->assertInertia(fn (Assert $page) => $page->component('Courses/Index'));
});
