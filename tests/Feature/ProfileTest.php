<?php

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('profile page is displayed', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get('/profile');

    $response->assertOk();
});

test('profile information can be updated', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch('/profile', [
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/profile');

    $user->refresh();

    $this->assertSame('Test User', $user->name);
    $this->assertSame('test@example.com', $user->email);
    $this->assertNull($user->email_verified_at);
});

test('email verification status is unchanged when the email address is unchanged', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch('/profile', [
            'name' => 'Test User',
            'email' => $user->email,
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/profile');

    $this->assertNotNull($user->refresh()->email_verified_at);
});

test('profile avatar can be uploaded', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $avatar = UploadedFile::fake()->image('avatar.jpg', 500, 500)->size(500);

    $this->actingAs($user)
        ->patch('/profile', [
            'name' => $user->name,
            'email' => $user->email,
            'avatar' => $avatar,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect('/profile');

    $avatarPath = $user->refresh()->avatar_path;

    expect($avatarPath)->not->toBeNull()
        ->and($user->avatar_url)->toBe(Storage::disk('public')->url($avatarPath));

    Storage::disk('public')->assertExists($avatarPath);
});

test('uploading a new profile avatar deletes the previous file', function () {
    Storage::fake('public');
    Storage::disk('public')->put('avatars/old-avatar.jpg', 'old image');

    $user = User::factory()->create([
        'avatar_path' => 'avatars/old-avatar.jpg',
    ]);

    $this->actingAs($user)
        ->patch('/profile', [
            'name' => $user->name,
            'email' => $user->email,
            'avatar' => UploadedFile::fake()->image('new-avatar.webp', 500, 500)->size(500),
        ])
        ->assertSessionHasNoErrors();

    $newAvatarPath = $user->refresh()->avatar_path;

    Storage::disk('public')->assertMissing('avatars/old-avatar.jpg');
    Storage::disk('public')->assertExists($newAvatarPath);
});

test('profile avatar must be a supported image no larger than two megabytes', function () {
    Storage::fake('public');

    $user = User::factory()->create();

    $this->actingAs($user)
        ->from('/profile')
        ->patch('/profile', [
            'name' => $user->name,
            'email' => $user->email,
            'avatar' => UploadedFile::fake()->create('avatar.svg', 100, 'image/svg+xml'),
        ])
        ->assertSessionHasErrors('avatar')
        ->assertRedirect('/profile');

    $this->actingAs($user)
        ->from('/profile')
        ->patch('/profile', [
            'name' => $user->name,
            'email' => $user->email,
            'avatar' => UploadedFile::fake()->image('large.jpg')->size(2049),
        ])
        ->assertSessionHasErrors('avatar')
        ->assertRedirect('/profile');

    expect($user->fresh()->avatar_path)->toBeNull();
});

test('user can delete their account', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->delete('/profile', [
            'password' => 'password',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/');

    $this->assertGuest();
    $this->assertSoftDeleted($user);
    expect(AuditLog::where('action', AuditAction::UserDeleted->value)->exists())->toBeTrue();
});

test('correct password must be provided to delete account', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from('/profile')
        ->delete('/profile', [
            'password' => 'wrong-password',
        ]);

    $response
        ->assertSessionHasErrors('password')
        ->assertRedirect('/profile');

    $this->assertNotNull($user->fresh());
});
