<?php

use Illuminate\Support\Facades\Storage;
use Symfony\Component\Yaml\Yaml;

it('lists yaml files under the courses directory', function () {
    Storage::fake('local');

    Storage::disk('local')->put('courses/index.yaml', Yaml::dump(['course-1'], 4, 2));
    Storage::disk('local')->put('courses/notes.txt', 'not yaml');

    $response = $this->get('/data');

    $response->assertSuccessful();
    $response->assertJson([
        'files' => ['courses/index.yaml'],
    ]);
});

it('builds the courses index from folders', function () {
    Storage::fake('local');

    Storage::disk('local')->makeDirectory('courses/course-1');
    Storage::disk('local')->makeDirectory('courses/course-2');
    Storage::disk('local')->put('courses/course-1/chapters.json', json_encode(['title' => 'Course 1'], JSON_THROW_ON_ERROR));
    Storage::disk('local')->put('courses/course-2/chapters.json', json_encode(['title' => 'Course 2'], JSON_THROW_ON_ERROR));

    $response = $this->get('/data/courses/index.json');

    $response->assertSuccessful();
    $response->assertJson([
        ['slug' => 'course-1'],
        ['slug' => 'course-2'],
    ]);
});

it('ignores folders without course metadata', function () {
    Storage::fake('local');

    Storage::disk('local')->makeDirectory('courses/course-1');
    Storage::disk('local')->makeDirectory('courses/course-ignored');
    Storage::disk('local')->put('courses/course-1/chapters.json', json_encode(['title' => 'Course 1'], JSON_THROW_ON_ERROR));

    $response = $this->get('/data/courses/index.json');

    $response->assertSuccessful();
    $response->assertJsonFragment(['slug' => 'course-1']);
    $response->assertJsonMissing(['slug' => 'course-ignored']);
});

it('loads courses with encoded folder names', function () {
    Storage::fake('local');

    Storage::disk('local')->makeDirectory('courses/intro course');
    Storage::disk('local')->put('courses/intro course/chapters.json', json_encode([
        'title' => 'Intro Course',
    ], JSON_THROW_ON_ERROR));

    $index = $this->get('/data/courses/index.json');

    $index->assertSuccessful();
    $index->assertJsonFragment(['slug' => 'intro course']);

    $course = $this->get('/data/courses/intro%20course/chapters.json');

    $course->assertSuccessful();
    $course->assertJson([
        'title' => 'Intro Course',
    ]);
});

it('stores and updates data files', function () {
    Storage::fake('local');

    $create = $this->post('/data/courses/demo.json', [
        'data' => ['title' => 'Demo'],
    ]);

    $create->assertCreated();
    expect(Storage::disk('local')->exists('courses/demo.json'))->toBeTrue();

    $update = $this->put('/data/courses/demo.json', [
        'data' => ['title' => 'Updated'],
    ]);

    $update->assertSuccessful();
    $payload = json_decode(Storage::disk('local')->get('courses/demo.json'), true, 512, JSON_THROW_ON_ERROR);

    expect($payload)->toBe(['title' => 'Updated']);
});

it('prefers json files over yaml files', function () {
    Storage::fake('local');

    Storage::disk('local')->put('courses/demo/chapters.json', json_encode(['title' => 'Json']));
    Storage::disk('local')->put('courses/demo/chapters.yaml', Yaml::dump(['title' => 'Yaml'], 4, 2));

    $response = $this->get('/data/courses/demo/chapters.json');

    $response->assertSuccessful();
    $response->assertJson([
        'title' => 'Json',
    ]);
});

it('rejects unsafe data paths', function () {
    Storage::fake('local');

    $response = $this->get('/data/../secrets.json');

    $response->assertNotFound();
});
