<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

it('logs in with valid credentials via Fortify', function () {
    $user = User::factory()->create([
        'email' => 'api@example.com',
        'password' => Hash::make('secret-password'),
    ]);

    $response = $this->post('/login', [
        'email' => 'api@example.com',
        'password' => 'secret-password',
    ]);

    $response->assertRedirect();

    $this->assertAuthenticatedAs($user);
});

it('rejects invalid credentials', function () {
    User::factory()->create([
        'email' => 'api@example.com',
        'password' => Hash::make('secret-password'),
    ]);

    $response = $this->post('/login', [
        'email' => 'api@example.com',
        'password' => 'wrong-password',
    ]);

    $response->assertSessionHasErrors('email');
    $this->assertGuest();
});

it('logs out authenticated users via Fortify', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/logout');

    $response->assertRedirect();
    $this->assertGuest();
});

it('reuses the session for authenticated api requests', function () {
    Storage::fake('local');
    $user = User::factory()->create([
        'email' => 'api@example.com',
        'password' => Hash::make('secret-password'),
    ]);

    $login = $this->post('/login', [
        'email' => 'api@example.com',
        'password' => 'secret-password',
    ]);

    $login->assertRedirect();

    $response = $this->getJson('/api/course');

    $response->assertSuccessful();
    $response->assertJson([
        'data' => [],
    ]);
});
