<?php

use App\Models\Task;

test('progress is computed from planned and actual quantity', function () {
    expect(Task::progressFromQuantity(500, 120))->toBe(24)
        ->and(Task::progressFromQuantity(500, 0))->toBe(0)
        ->and(Task::progressFromQuantity(500, 500))->toBe(100);
});

test('progress from quantity rounds to the nearest whole percent', function () {
    expect(Task::progressFromQuantity(3, 1))->toBe(33)
        ->and(Task::progressFromQuantity(3, 2))->toBe(67);
});

test('progress from quantity is capped at 100 when the target is exceeded', function () {
    expect(Task::progressFromQuantity(500, 520))->toBe(100)
        ->and(Task::progressFromQuantity(500, 100000))->toBe(100);
});

test('a task tracks quantity only when a planned quantity is set', function () {
    $plain = Task::factory()->create();
    $measured = Task::factory()->withQuantity(planned: 500, actual: 120, unit: 'hồ sơ')->create();

    expect($plain->tracksQuantity())->toBeFalse()
        ->and($plain->planned_quantity)->toBeNull()
        ->and($plain->actual_quantity)->toBeNull()
        ->and($plain->quantity_unit)->toBeNull()
        ->and($measured->tracksQuantity())->toBeTrue()
        ->and($measured->planned_quantity)->toBe(500)
        ->and($measured->actual_quantity)->toBe(120)
        ->and($measured->quantity_unit)->toBe('hồ sơ');
});
