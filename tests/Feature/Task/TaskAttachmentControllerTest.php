<?php

use App\Enums\AuditAction;
use App\Enums\PermissionName;
use App\Models\Task;
use App\Models\TaskAttachment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
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

test('files already written to disk are discarded when the transaction fails after storing them', function () {
    Storage::fake('local');

    $task = Task::factory()->create();

    // Mô phỏng lỗi DB xảy ra SAU KHI các file đã được ghi lên disk (vòng lặp store() chạy
    // trước, bên trong cùng khối try/catch với DB::transaction). AuditLogger là "final" nên
    // Mockery không thể tạo mock thoả type hint constructor của action (đã thử: TypeError vì
    // partial mock của final class không được coi là instance thật). Thay vào đó, mô phỏng lỗi
    // bằng cách partial-mock DatabaseManager đứng sau facade DB (không phải final) để
    // DB::transaction() ném exception ngay khi được gọi — đúng nhánh mà store() ném exception
    // giữa vòng lặp cũng sẽ đi qua, vì cả hai đều được bọc chung trong khối try/catch của action.
    $realDatabaseManager = app('db');
    $partialMock = Mockery::mock($realDatabaseManager)->makePartial();
    $partialMock->shouldReceive('transaction')->once()->andThrow(new RuntimeException('Lỗi giao dịch DB giả lập.'));
    $this->app->instance('db', $partialMock);
    DB::clearResolvedInstance('db');

    $this->withoutExceptionHandling();

    $caught = null;

    try {
        $this->actingAs(uploaderUser())
            ->post(route('tasks.attachments.store', $task), [
                'files' => [
                    UploadedFile::fake()->create('a.pdf', 10, 'application/pdf'),
                    UploadedFile::fake()->create('b.pdf', 20, 'application/pdf'),
                ],
            ]);
    } catch (RuntimeException $exception) {
        $caught = $exception;
    }

    expect($caught)->not->toBeNull()
        ->and($caught->getMessage())->toBe('Lỗi giao dịch DB giả lập.');

    expect(TaskAttachment::query()->count())->toBe(0);
    expect(Storage::disk('local')->allFiles())->toBe([]);
});

test('a user who can view the task downloads an attachment under its original name', function () {
    Storage::fake('local');

    $task = Task::factory()->create();
    $path = "task-attachments/{$task->id}/luu-tru.pdf";
    Storage::disk('local')->put($path, 'noi dung tep');

    $attachment = TaskAttachment::factory()->for($task)->create([
        'path' => $path,
        'original_name' => 'Báo cáo quý.pdf',
    ]);

    $viewer = userWithPermissions([PermissionName::TaskView->value]);

    $response = $this->actingAs($viewer)
        ->get(route('tasks.attachments.download', [$task, $attachment]));

    $response->assertOk();
    expect($response->headers->get('content-disposition'))->toContain('attachment');
    expect($response->streamedContent())->toBe('noi dung tep');
});

test('a user without the task view permission cannot download an attachment', function () {
    Storage::fake('local');

    $task = Task::factory()->create();
    $path = "task-attachments/{$task->id}/luu-tru.pdf";
    Storage::disk('local')->put($path, 'noi dung tep');

    $attachment = TaskAttachment::factory()->for($task)->create(['path' => $path]);

    $this->actingAs(userWithPermissions([]))
        ->get(route('tasks.attachments.download', [$task, $attachment]))
        ->assertForbidden();
});

test('an attachment cannot be reached through a different task', function () {
    Storage::fake('local');

    $task = Task::factory()->create();
    $otherTask = Task::factory()->create();
    $attachment = TaskAttachment::factory()->for($otherTask)->create();

    $this->actingAs(userWithPermissions([PermissionName::TaskView->value]))
        ->get(route('tasks.attachments.download', [$task, $attachment]))
        ->assertNotFound();
});
