<?php

test('returns a successful response', function () {
    $response = $this->get(route('courses.index'));

    $response->assertOk();
});
