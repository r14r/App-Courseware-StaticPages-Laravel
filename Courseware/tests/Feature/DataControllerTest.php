<?php

use Illuminate\Support\Facades\Storage;

it('lists json files under the data directory', function () {
    Storage::fake('local');

    Storage::disk('local')->put('data/courses/index.json', json_encode(['course-1']));
    Storage::disk('local')->put('data/courses/notes.txt', 'not json');

    $response = $this->get('/data');

    $response->assertSuccessful();
    $response->assertJson([
        'files' => ['data/courses/index.json'],
    ]);
});

it('shows a json data file', function () {
    Storage::fake('local');

    Storage::disk('local')->put('data/courses/index.json', json_encode(['course-1']));

    $response = $this->get('/data/courses/index.json');

    $response->assertSuccessful();
    $response->assertJson(['course-1']);
});

it('stores and updates data files', function () {
    Storage::fake('local');

    $create = $this->post('/data/courses/demo.json', [
        'data' => ['title' => 'Demo'],
    ]);

    $create->assertCreated();
    expect(Storage::disk('local')->exists('data/courses/demo.json'))->toBeTrue();

    $update = $this->put('/data/courses/demo.json', [
        'data' => ['title' => 'Updated'],
    ]);

    $update->assertSuccessful();
    $payload = json_decode(Storage::disk('local')->get('data/courses/demo.json'), true);

    expect($payload)->toBe(['title' => 'Updated']);
});

it('rejects unsafe data paths', function () {
    Storage::fake('local');

    $response = $this->get('/data/../secrets.json');

    $response->assertNotFound();
});
