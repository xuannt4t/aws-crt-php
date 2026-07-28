<?php

use App\Enums\AuditAction;
use App\Enums\PermissionName;
use App\Models\Task;
use App\Models\TaskAttachment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function uploaderUser(): App\Models\User
{
    return userWithPermissions([
        PermissionName::TaskView->value,
        PermissionName::TaskComment->value,
    ]);
}

test('a user who can comment uploads files to a task', function () {
    Storage::fake('local');

    $uploader = uploaderUser();
    $task = Task::factory()->create();

    $this->actingAs($uploader)
        ->post(route('tasks.attachments.store', $task), [
            'files' => [
                UploadedFile::fake()->create('bao-cao.pdf', 120, 'application/pdf'),
                UploadedFile::fake()->create('so-lieu.xlsx', 80, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
            ],
        ])
        ->assertRedirect(route('tasks.show', $task))
        ->assertSessionHas('success');

    $attachments = TaskAttachment::query()->get();

    expect($attachments)->toHaveCount(2)
        ->and($attachments->pluck('original_name')->all())->toContain('bao-cao.pdf', 'so-lieu.xlsx')
        ->and($attachments->pluck('task_id')->unique()->all())->toBe([$task->id])
        ->and($attachments->pluck('uploader_id')->unique()->all())->toBe([$uploader->id])
        ->and($attachments->pluck('disk')->unique()->all())->toBe(['local']);

    foreach ($attachments as $attachment) {
        Storage::disk('local')->assertExists($attachment->path);
        expect($attachment->path)->toStartWith("task-attachments/{$task->id}/")
            ->and($attachment->path)->not->toContain($attachment->original_name);
    }
});

test('uploading records a single audit entry for the request', function () {
    Storage::fake('local');

    $uploader = uploaderUser();
    $task = Task::factory()->create();

    $this->actingAs($uploader)
        ->post(route('tasks.attachments.store', $task), [
            'files' => [
                UploadedFile::fake()->create('a.pdf', 10, 'application/pdf'),
                UploadedFile::fake()->create('b.pdf', 20, 'application/pdf'),
            ],
        ]);

    $this->assertDatabaseHas('audit_logs', [
        'actor_id' => $uploader->id,
        'action' => AuditAction::TaskAttachmentUploaded->value,
        'subject_type' => $task->getMorphClass(),
        'subject_id' => $task->id,
    ]);

    expect(App\Models\AuditLog::query()->where('action', AuditAction::TaskAttachmentUploaded->value)->count())->toBe(1);
});

test('uploading requires both view and comment permissions', function () {
    Storage::fake('local');

    $user = userWithPermissions([PermissionName::TaskView->value]);
    $task = Task::factory()->create();

    $this->actingAs($user)
        ->post(route('tasks.attachments.store', $task), [
            'files' => [UploadedFile::fake()->create('bao-cao.pdf', 10, 'application/pdf')],
        ])
        ->assertForbidden();

    expect(TaskAttachment::query()->count())->toBe(0);
});

test('an executable file type is rejected', function () {
    Storage::fake('local');

    $task = Task::factory()->create();

    $this->actingAs(uploaderUser())
        ->post(route('tasks.attachments.store', $task), [
            'files' => [UploadedFile::fake()->create('virus.exe', 10, 'application/x-msdownload')],
        ])
        ->assertSessionHasErrors('files.0');

    expect(TaskAttachment::query()->count())->toBe(0);
    expect(Storage::disk('local')->allFiles())->toBe([]);
});

test('a file larger than ten megabytes is rejected', function () {
    Storage::fake('local');

    $task = Task::factory()->create();

    $this->actingAs(uploaderUser())
        ->post(route('tasks.attachments.store', $task), [
            'files' => [UploadedFile::fake()->create('lon.pdf', 10241, 'application/pdf')],
        ])
        ->assertSessionHasErrors('files.0');

    expect(TaskAttachment::query()->count())->toBe(0);
});

test('more than five files in one request are rejected', function () {
    Storage::fake('local');

    $task = Task::factory()->create();
    $files = [];

    for ($index = 0; $index < 6; $index++) {
        $files[] = UploadedFile::fake()->create("tep-{$index}.pdf", 10, 'application/pdf');
    }

    $this->actingAs(uploaderUser())
        ->post(route('tasks.attachments.store', $task), ['files' => $files])
        ->assertSessionHasErrors('files');

    expect(TaskAttachment::query()->count())->toBe(0);
});

test('at least one file is required', function () {
    Storage::fake('local');

    $task = Task::factory()->create();

    $this->actingAs(uploaderUser())
        ->post(route('tasks.attachments.store', $task), [])
        ->assertSessionHasErrors('files');
});
