<?php

use App\Actions\OrganizationUnit\DeleteOrganizationUnitAction;
use App\Actions\OrganizationUnit\UpdateOrganizationUnitAction;
use App\Models\OrganizationUnit;
use App\Models\User;
use Illuminate\Validation\ValidationException;

test('an organization unit cannot be updated to be its own parent', function () {
    $unit = OrganizationUnit::factory()->create();

    expect(fn () => (new UpdateOrganizationUnitAction)->execute($unit, ['parent_id' => $unit->id]))
        ->toThrow(ValidationException::class);
});

test('an organization unit cannot be updated to have one of its descendants as its parent', function () {
    $grandparent = OrganizationUnit::factory()->create();
    $parent = OrganizationUnit::factory()->create(['parent_id' => $grandparent->id]);
    $child = OrganizationUnit::factory()->create(['parent_id' => $parent->id]);

    expect(fn () => (new UpdateOrganizationUnitAction)->execute($grandparent, ['parent_id' => $child->id]))
        ->toThrow(ValidationException::class);
});

test('an organization unit can be updated to a valid new parent', function () {
    $oldParent = OrganizationUnit::factory()->create();
    $newParent = OrganizationUnit::factory()->create();
    $unit = OrganizationUnit::factory()->create(['parent_id' => $oldParent->id]);

    (new UpdateOrganizationUnitAction)->execute($unit, ['parent_id' => $newParent->id, 'name' => $unit->name, 'code' => $unit->code, 'is_active' => true]);

    expect($unit->fresh()->parent_id)->toBe($newParent->id);
});

test('an organization unit cannot be deleted while it has children', function () {
    $actor = User::factory()->create();
    $parent = OrganizationUnit::factory()->create();
    OrganizationUnit::factory()->create(['parent_id' => $parent->id]);

    expect(fn () => app(DeleteOrganizationUnitAction::class)->execute($actor, $parent))
        ->toThrow(ValidationException::class);

    $this->assertNotSoftDeleted($parent);
});

test('an organization unit cannot be deleted while it has users assigned', function () {
    $actor = User::factory()->create();
    $unit = OrganizationUnit::factory()->create();
    User::factory()->create(['organization_unit_id' => $unit->id]);

    expect(fn () => app(DeleteOrganizationUnitAction::class)->execute($actor, $unit))
        ->toThrow(ValidationException::class);

    $this->assertNotSoftDeleted($unit);
});

test('an organization unit with no children or users can be deleted', function () {
    $actor = User::factory()->create();
    $unit = OrganizationUnit::factory()->create();

    app(DeleteOrganizationUnitAction::class)->execute($actor, $unit);

    $this->assertSoftDeleted($unit);
});
