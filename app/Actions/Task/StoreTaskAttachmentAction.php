<?php

namespace App\Actions\Task;

use App\Enums\AuditAction;
use App\Models\Task;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

final readonly class StoreTaskAttachmentAction
{
    private const DISK = 'local';

    public function __construct(private AuditLogger $auditLogger) {}

    /**
     * @param  list<UploadedFile>  $files
     */
    public function execute(User $actor, Task $task, array $files): void
    {
        // Ghi disk trước, ngoài transaction: thao tác I/O chậm không được nằm trong transaction.
        $storedPaths = [];
        $rows = [];

        foreach ($files as $file) {
            $path = $file->store("task-attachments/{$task->id}", self::DISK);

            if (! is_string($path)) {
                $this->discard($storedPaths);

                throw ValidationException::withMessages([
                    'files' => 'Không thể lưu tệp đính kèm. Vui lòng thử lại.',
                ]);
            }

            $storedPaths[] = $path;
            $rows[] = [
                'uploader_id' => $actor->id,
                'disk' => self::DISK,
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getClientMimeType(),
                'size_bytes' => $file->getSize(),
            ];
        }

        try {
            DB::transaction(function () use ($actor, $task, $rows): void {
                foreach ($rows as $row) {
                    $task->attachments()->create($row);
                }

                $this->auditLogger->record(
                    actor: $actor,
                    action: AuditAction::TaskAttachmentUploaded,
                    subject: $task,
                    metadata: [
                        'file_count' => count($rows),
                        'total_size_bytes' => array_sum(array_column($rows, 'size_bytes')),
                        'original_names' => array_column($rows, 'original_name'),
                    ],
                );
            });
        } catch (Throwable $exception) {
            $this->discard($storedPaths);

            throw $exception;
        }
    }

    /**
     * @param  list<string>  $paths
     */
    private function discard(array $paths): void
    {
        foreach ($paths as $path) {
            Storage::disk(self::DISK)->delete($path);
        }
    }
}
