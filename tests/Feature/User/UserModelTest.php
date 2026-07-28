<?php

use App\Models\OrganizationUnit;
use App\Models\User;

test('a user belongs to an organization unit', function () {
    $unit = OrganizationUnit::factory()->create();
    $user = User::factory()->create(['organization_unit_id' => $unit->id]);

    expect($user->organizationUnit->is($unit))->toBeTrue();
});

test('a user is soft deleted, not removed from the database', function () {
    $user = User::factory()->create();

    $user->delete();

    $this->assertSoftDeleted($user);
});
