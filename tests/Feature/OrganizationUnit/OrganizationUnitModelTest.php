<?php

use App\Models\OrganizationUnit;

test('an organization unit can have a parent and children', function () {
    $parent = OrganizationUnit::factory()->create();
    $child = OrganizationUnit::factory()->create(['parent_id' => $parent->id]);

    expect($child->parent->is($parent))->toBeTrue();
    expect($parent->children)->toHaveCount(1);
    expect($parent->children->first()->is($child))->toBeTrue();
});

test('an organization unit is soft deleted, not removed from the database', function () {
    $unit = OrganizationUnit::factory()->create();

    $unit->delete();

    $this->assertSoftDeleted($unit);
});
