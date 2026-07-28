<?php

use App\Models\OrganizationUnit;
use App\Models\User;

test('a system admin can view the organization units index', function () {
    $admin = User::factory()->create(['is_system_admin' => true]);

    $response = $this->actingAs($admin)->get(route('organization-units.index'));

    $response->assertOk();
});

test('a guest is redirected to login when viewing organization units', function () {
    $response = $this->get(route('organization-units.index'));

    $response->assertRedirect(route('login'));
});

test('a system admin can create an organization unit', function () {
    $admin = User::factory()->create(['is_system_admin' => true]);

    $response = $this->actingAs($admin)->post(route('organization-units.store'), [
        'parent_id' => null,
        'name' => 'Phòng Kỹ thuật',
        'code' => 'ENG',
        'is_active' => true,
    ]);

    $response->assertRedirect(route('organization-units.index'));
    $this->assertDatabaseHas('organization_units', ['code' => 'ENG', 'name' => 'Phòng Kỹ thuật']);
});

test('a regular user cannot create an organization unit', function () {
    $user = User::factory()->create(['is_system_admin' => false]);

    $response = $this->actingAs($user)->post(route('organization-units.store'), [
        'parent_id' => null,
        'name' => 'Phòng Kỹ thuật',
        'code' => 'ENG',
        'is_active' => true,
    ]);

    $response->assertForbidden();
    $this->assertDatabaseMissing('organization_units', ['code' => 'ENG']);
});

test('creating an organization unit requires a unique code', function () {
    $admin = User::factory()->create(['is_system_admin' => true]);
    OrganizationUnit::factory()->create(['code' => 'ENG']);

    $response = $this->actingAs($admin)->post(route('organization-units.store'), [
        'parent_id' => null,
        'name' => 'Another Unit',
        'code' => 'ENG',
        'is_active' => true,
    ]);

    $response->assertSessionHasErrors('code');
});

test('a system admin can update an organization unit', function () {
    $admin = User::factory()->create(['is_system_admin' => true]);
    $unit = OrganizationUnit::factory()->create();

    $response = $this->actingAs($admin)->put(route('organization-units.update', $unit), [
        'parent_id' => null,
        'name' => 'Renamed Unit',
        'code' => $unit->code,
        'is_active' => true,
    ]);

    $response->assertRedirect(route('organization-units.index'));
    expect($unit->fresh()->name)->toBe('Renamed Unit');
});

test('updating an organization unit rejects a circular parent', function () {
    $admin = User::factory()->create(['is_system_admin' => true]);
    $unit = OrganizationUnit::factory()->create();

    $response = $this->actingAs($admin)->put(route('organization-units.update', $unit), [
        'parent_id' => $unit->id,
        'name' => $unit->name,
        'code' => $unit->code,
        'is_active' => true,
    ]);

    $response->assertSessionHasErrors('parent_id');
});

test('a system admin can delete an organization unit with no dependents', function () {
    $admin = User::factory()->create(['is_system_admin' => true]);
    $unit = OrganizationUnit::factory()->create();

    $response = $this->actingAs($admin)->delete(route('organization-units.destroy', $unit));

    $response->assertRedirect(route('organization-units.index'));
    $this->assertSoftDeleted($unit);
});

test('deleting an organization unit with children fails', function () {
    $admin = User::factory()->create(['is_system_admin' => true]);
    $parent = OrganizationUnit::factory()->create();
    OrganizationUnit::factory()->create(['parent_id' => $parent->id]);

    $response = $this->actingAs($admin)->delete(route('organization-units.destroy', $parent));

    $response->assertSessionHasErrors('organization_unit');
    $this->assertNotSoftDeleted($parent);
});

test('a regular user cannot update an organization unit', function () {
    $user = User::factory()->create(['is_system_admin' => false]);
    $unit = OrganizationUnit::factory()->create();

    $response = $this->actingAs($user)->put(route('organization-units.update', $unit), [
        'parent_id' => null,
        'name' => 'Renamed Unit',
        'code' => $unit->code,
        'is_active' => true,
    ]);

    $response->assertForbidden();
});

test('a regular user cannot delete an organization unit', function () {
    $user = User::factory()->create(['is_system_admin' => false]);
    $unit = OrganizationUnit::factory()->create();

    $response = $this->actingAs($user)->delete(route('organization-units.destroy', $unit));

    $response->assertForbidden();
    $this->assertNotSoftDeleted($unit);
});
