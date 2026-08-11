<?php

use App\Enums\PermissionName;
use App\Models\OrganizationUnit;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;

test('an authenticated user can read a stable profile resource', function () {
    $unit = OrganizationUnit::factory()->create();
    $user = User::factory()->create(['organization_unit_id' => $unit->id]);
    Sanctum::actingAs($user);

    $this->getJson('/api/v1/profile')
        ->assertOk()
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.organization_unit.id', $unit->id)
        ->assertJsonStructure(['data' => ['roles', 'permissions']])
        ->assertJsonMissingPath('data.password')
        ->assertJsonMissingPath('data.avatar_path');
});

test('an authenticated user can update their profile', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->patchJson('/api/v1/profile', [
        'name' => 'Tên mới',
        'email' => 'new-email@example.com',
    ])->assertOk()
        ->assertJsonPath('data.name', 'Tên mới')
        ->assertJsonPath('data.email', 'new-email@example.com');

    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'name' => 'Tên mới',
        'email' => 'new-email@example.com',
    ]);
});

test('a user can change password and revoke other device sessions', function () {
    $user = User::factory()->create(['password' => 'old-password']);
    $first = $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'old-password',
        'device_name' => 'Phone',
    ])->assertOk();
    $second = $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'old-password',
        'device_name' => 'Tablet',
    ])->assertOk();

    $this->withToken($first->json('data.access_token'))
        ->putJson('/api/v1/profile/password', [
            'current_password' => 'old-password',
            'password' => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
            'revoke_other_sessions' => true,
        ])->assertOk();

    expect(Hash::check('new-secure-password', $user->fresh()->password))->toBeTrue();
    $this->withToken($first->json('data.access_token'))->getJson('/api/v1/auth/me')->assertOk();
    Auth::forgetGuards();
    $this->withToken($second->json('data.access_token'))->getJson('/api/v1/auth/me')->assertUnauthorized();
});

test('meta returns enums and only visible option data', function () {
    $user = userWithPermissions([PermissionName::TaskAssign->value]);
    OrganizationUnit::factory()->create(['is_active' => true]);
    Project::factory()->create(['owner_id' => $user->id]);
    Sanctum::actingAs($user);

    $this->getJson('/api/v1/meta')
        ->assertOk()
        ->assertJsonStructure(['data' => [
            'task_statuses',
            'task_priorities',
            'project_statuses',
            'recurrence_frequencies',
            'project_member_roles',
            'project_task_visibilities',
            'roles',
            'limits',
        ]]);

    $this->getJson('/api/v1/meta/users?search='.$user->name.'&per_page=200')
        ->assertOk()
        ->assertJsonPath('meta.per_page', 100)
        ->assertJsonFragment(['id' => $user->id]);

    $this->getJson('/api/v1/meta/projects')->assertOk();
    $this->getJson('/api/v1/meta/organization-units')->assertOk();
});
