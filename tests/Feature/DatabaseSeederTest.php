<?php

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Models\OrganizationUnit;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;

test('seeding creates a root organization unit and a system admin user', function () {
    Artisan::call('db:seed');

    $rootUnit = OrganizationUnit::where('code', 'ROOT')->first();
    $admin = User::where('email', config('dormida.admin_email'))->first();

    expect($rootUnit)->not->toBeNull();
    expect($admin)->not->toBeNull();
    expect($admin->is_system_admin)->toBeTrue();
    expect($admin->organization_unit_id)->toBe($rootUnit->id);
    expect($admin->hasRole(RoleName::SystemAdmin->value))->toBeTrue();
    expect($admin->can(PermissionName::SystemManageSettings->value))->toBeTrue();
});

test('the seeded admin can log in', function () {
    Artisan::call('db:seed');

    $response = $this->post('/login', [
        'email' => config('dormida.admin_email'),
        'password' => config('dormida.admin_password'),
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});
