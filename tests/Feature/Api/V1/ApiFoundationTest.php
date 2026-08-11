<?php

use App\Enums\PermissionName;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

test('api routes return the standard unauthenticated json response', function () {
    $this->getJson('/api/v1/auth/me')
        ->assertUnauthorized()
        ->assertExactJson([
            'success' => false,
            'message' => 'Unauthenticated.',
        ]);
});

test('authenticated api responses use the standard success envelope', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Thành công')
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonMissingPath('data.password');
});

test('inactive users are rejected by protected api routes', function () {
    $user = User::factory()->create(['is_active' => false]);
    Sanctum::actingAs($user);

    $this->getJson('/api/v1/auth/me')
        ->assertForbidden()
        ->assertExactJson([
            'success' => false,
            'message' => 'Tài khoản đã bị vô hiệu hóa.',
        ]);
});

test('api authorization and missing resource errors use the standard envelope', function () {
    Sanctum::actingAs(User::factory()->create());
    $this->getJson('/api/v1/organization-units')
        ->assertForbidden()
        ->assertExactJson(['success' => false, 'message' => 'Bạn không có quyền thực hiện thao tác này.']);

    Sanctum::actingAs(userWithPermissions([PermissionName::UserView->value]));
    $this->getJson('/api/v1/users/999999')
        ->assertNotFound()
        ->assertExactJson(['success' => false, 'message' => 'Không tìm thấy tài nguyên.']);
});
