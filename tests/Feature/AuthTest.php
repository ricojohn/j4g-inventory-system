<?php

use App\Models\User;
use Database\Seeders\UserSeeder;

beforeEach(function () {
    seedBaseData();
    (new UserSeeder)->run();
});

test('login succeeds with valid credentials and regenerates session', function () {
    $response = $this->post('/login', [
        'email' => 'admin@j4g.test',
        'password' => 'password',
    ]);

    $response->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs(userWithRole('Admin'));
});

test('login fails with invalid credentials', function () {
    $response = $this->post('/login', [
        'email' => 'admin@j4g.test',
        'password' => 'wrong-password',
    ]);

    $response->assertSessionHasErrors('email');
    $this->assertGuest();
});

test('inactive users cannot login', function () {
    User::query()->where('email', 'admin@j4g.test')->update(['status' => 'inactive']);

    $response = $this->post('/login', [
        'email' => 'admin@j4g.test',
        'password' => 'password',
    ]);

    $response->assertSessionHasErrors('email');
    $this->assertGuest();
});

test('logout invalidates session', function () {
    $this->actingAs(userWithRole('Admin'));

    $response = $this->post('/logout');

    $response->assertRedirect(route('login'));
    $this->assertGuest();
});
