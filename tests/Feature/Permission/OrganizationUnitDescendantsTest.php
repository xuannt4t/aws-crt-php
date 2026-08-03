<?php

use App\Models\OrganizationUnit;
use Illuminate\Support\Facades\DB;

test('descendant ids of returns the unit itself for a leaf unit', function () {
    $leaf = OrganizationUnit::factory()->create();

    expect(OrganizationUnit::descendantIdsOf($leaf->id))->toBe([$leaf->id]);
});

test('descendant ids of walks a three level tree', function () {
    $root = OrganizationUnit::factory()->create();
    $child = OrganizationUnit::factory()->create(['parent_id' => $root->id]);
    $grandchild = OrganizationUnit::factory()->create(['parent_id' => $child->id]);
    $unrelated = OrganizationUnit::factory()->create();

    $result = OrganizationUnit::descendantIdsOf($root->id);

    expect($result)->toEqualCanonicalizing([$root->id, $child->id, $grandchild->id])
        ->and($result)->not->toContain($unrelated->id);
});

test('descendant ids of returns empty array for a non existent unit', function () {
    expect(OrganizationUnit::descendantIdsOf(999999))->toBe([]);
});

test('descendant ids of does not hang on cyclic parent child data', function () {
    $a = OrganizationUnit::factory()->create();
    $b = OrganizationUnit::factory()->create(['parent_id' => $a->id]);

    // Force a cycle directly via DB, bypassing model rules, to simulate corrupt data.
    DB::table('organization_units')->where('id', $a->id)->update(['parent_id' => $b->id]);

    $result = OrganizationUnit::descendantIdsOf($a->id);

    expect($result)->toEqualCanonicalizing([$a->id, $b->id]);
});
