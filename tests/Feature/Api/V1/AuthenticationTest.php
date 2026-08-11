<?php

use App\Models\User;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow('2026-08-11 10:00:00');
});

afterEach(function () {
    Carbon::setTestNow();
});

test('an active user can log in and receive a five hour access token and thirty day refresh token', function () {
    $user = User::factory()->create([
        'email' => 'mobile@example.com',
        'password' => 'password',
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'mobile@example.com',
        'password' => 'password',
        'device_name' => 'iPhone 15',
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.token_type', 'Bearer')
        ->assertJsonPath('data.access_token_expires_in', 18000)
        ->assertJsonPath('data.refresh_token_expires_in', 2592000)
        ->assertJsonPath('data.user.id', $user->id)
        ->assertJsonStructure(['data' => ['access_token', 'refresh_token']]);

    $refreshToken = $response->json('data.refresh_token');

    $this->assertDatabaseHas('personal_access_tokens', [
        'tokenable_id' => $user->id,
        'name' => 'iPhone 15',
        'expires_at' => now()->addHours(5)->format('Y-m-d H:i:s'),
    ]);
    $this->assertDatabaseHas('api_refresh_sessions', [
        'user_id' => $user->id,
        'name' => 'iPhone 15',
        'token_hash' => hash('sha256', $refreshToken),
        'expires_at' => now()->addDays(30)->format('Y-m-d H:i:s'),
    ]);
});

test('invalid credentials and inactive accounts cannot log in', function () {
    User::factory()->create([
        'email' => 'disabled@example.com',
        'password' => 'password',
        'is_active' => false,
    ]);

    $this->postJson('/api/v1/auth/login', [
        'email' => 'missing@example.com',
        'password' => 'wrong-password',
        'device_name' => 'Android',
    ])->assertUnprocessable()->assertJsonValidationErrors('email');

    $this->postJson('/api/v1/auth/login', [
        'email' => 'disabled@example.com',
        'password' => 'password',
        'device_name' => 'Android',
    ])->assertForbidden()->assertExactJson([
        'success' => false,
        'message' => 'Tài khoản đã bị vô hiệu hóa.',
    ]);
});

test('refresh rotates both credentials and rejects reuse of the previous refresh token', function () {
    $user = User::factory()->create(['password' => 'password']);
    $login = $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'password',
        'device_name' => 'Pixel 10',
    ])->assertOk();

    $oldAccessToken = $login->json('data.access_token');
    $oldRefreshToken = $login->json('data.refresh_token');

    $rotated = $this->postJson('/api/v1/auth/refresh', [
        'refresh_token' => $oldRefreshToken,
    ])->assertOk();

    expect($rotated->json('data.access_token'))->not->toBe($oldAccessToken)
        ->and($rotated->json('data.refresh_token'))->not->toBe($oldRefreshToken);

    $this->withToken($oldAccessToken)->getJson('/api/v1/auth/me')->assertUnauthorized();

    $this->postJson('/api/v1/auth/refresh', [
        'refresh_token' => $oldRefreshToken,
    ])->assertUnauthorized();

    $this->postJson('/api/v1/auth/refresh', [
        'refresh_token' => $rotated->json('data.refresh_token'),
    ])->assertUnauthorized();
});

test('an expired refresh token is rejected', function () {
    $user = User::factory()->create(['password' => 'password']);
    $login = $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'password',
        'device_name' => 'Tablet',
    ])->assertOk();

    $this->travel(31)->days();

    $this->postJson('/api/v1/auth/refresh', [
        'refresh_token' => $login->json('data.refresh_token'),
    ])->assertUnauthorized();
});

test('logout revokes only the current device session', function () {
    $user = User::factory()->create(['password' => 'password']);
    $first = $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'password',
        'device_name' => 'Phone',
    ])->assertOk();
    $second = $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'password',
        'device_name' => 'Tablet',
    ])->assertOk();

    $this->withToken($first->json('data.access_token'))
        ->postJson('/api/v1/auth/logout')
        ->assertOk();

    $this->withToken($first->json('data.access_token'))->getJson('/api/v1/auth/me')->assertUnauthorized();
    $this->withToken($second->json('data.access_token'))->getJson('/api/v1/auth/me')->assertOk();
});

test('logout all revokes every device session', function () {
    $user = User::factory()->create(['password' => 'password']);
    $first = $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'password',
        'device_name' => 'Phone',
    ])->assertOk();
    $second = $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'password',
        'device_name' => 'Tablet',
    ])->assertOk();

    $this->withToken($first->json('data.access_token'))
        ->postJson('/api/v1/auth/logout-all')
        ->assertOk();

    $this->withToken($first->json('data.access_token'))->getJson('/api/v1/auth/me')->assertUnauthorized();
    $this->withToken($second->json('data.access_token'))->getJson('/api/v1/auth/me')->assertUnauthorized();
});
