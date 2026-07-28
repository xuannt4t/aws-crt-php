<?php

use App\Models\Task;
use App\Models\TaskAttachment;
use App\Models\User;

test('a task exposes its attachments newest first', function () {
    $task = Task::factory()->create();

    $older = TaskAttachment::factory()->for($task)->create();
    $newer = TaskAttachment::factory()->for($task)->create();

    expect($task->attachments()->pluck('id')->all())->toBe([$newer->id, $older->id]);
});

test('an attachment keeps its uploader even after the account is soft deleted', function () {
    $uploader = User::factory()->create();
    $attachment = TaskAttachment::factory()->for($uploader, 'uploader')->create();

    $uploader->delete();

    expect($attachment->fresh()->uploader->id)->toBe($uploader->id);
});

test('an attachment formats its size for humans', function (int $bytes, string $expected) {
    $attachment = TaskAttachment::factory()->create(['size_bytes' => $bytes]);

    expect($attachment->size_for_humans)->toBe($expected);
})->with([
    'bytes' => [512, '512.0 B'],
    'kilobytes' => [2048, '2.0 KB'],
    'megabytes' => [5 * 1024 * 1024, '5.0 MB'],
]);

test('soft deleting an attachment keeps the row in the database', function () {
    $attachment = TaskAttachment::factory()->create();

    $attachment->delete();

    expect(TaskAttachment::withTrashed()->count())->toBe(1)
        ->and(TaskAttachment::count())->toBe(0);
});
