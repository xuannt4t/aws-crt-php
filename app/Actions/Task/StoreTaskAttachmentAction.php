<?php

namespace App\Actions\Task;

use App\Enums\AuditAction;
use App\Enums\TaskActivityType;
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
    public function __construct(
        private AuditLogger $auditLogger,
        private RecordTaskActivityAction $recordActivity,
    ) {}

    /**
     * @param  list<UploadedFile>  $files
     */
    public function execute(User $actor, Task $task, array $files): void
    {
        // Ghi disk trước, ngoài transaction: thao tác I/O chậm không được nằm trong transaction.
        // Toàn bộ thân hàm (vòng lặp ghi file lẫn DB::transaction) nằm trong một try/catch duy nhất
        // để bất kỳ lỗi nào xảy ra sau khi đã ghi một phần file đều dọn dẹp được file mồ côi.
        $storedPaths = [];
        $disk = $this->disk();

        try {
            $rows = [];

            foreach ($files as $file) {
                $path = $file->store("task-attachments/{$task->id}", $disk);

                if (! is_string($path)) {
                    throw ValidationException::withMessages([
                        'files' => 'Không thể lưu tệp đính kèm. Vui lòng thử lại.',
                    ]);
                }

                $storedPaths[] = $path;
                $rows[] = [
                    'uploader_id' => $actor->id,
                    'disk' => $disk,
                    'path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    // MIME thật do server phát hiện (khớp rule `mimetypes` đã validate),
                    // không dùng getClientMimeType() vì đó là nhãn client tự khai, không đáng tin.
                    // getMimeType() trả về null khi không đoán được MIME; rule `mimetypes` đã
                    // validate thành công nên trường hợp này gần như không xảy ra, nhưng vẫn
                    // dự phòng bằng nhãn client để không ghi giá trị null vào cột bắt buộc.
                    'mime_type' => $file->getMimeType() ?? $file->getClientMimeType(),
                    'size_bytes' => $file->getSize(),
                ];
            }

            DB::transaction(function () use ($actor, $task, $rows): void {
                $attachments = [];

                foreach ($rows as $row) {
                    $attachments[] = $task->attachments()->create($row);
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

                $this->recordActivity->execute($actor, $task, TaskActivityType::AttachmentAdded, [
                    'attachment_ids' => array_map(static fn ($attachment): int => $attachment->id, $attachments),
                    'original_names' => array_column($rows, 'original_name'),
                    'file_count' => count($rows),
                ]);
            });
        } catch (Throwable $exception) {
            $this->discard($disk, $storedPaths);

            throw $exception;
        }
    }

    private function disk(): string
    {
        return (string) config('dormida.attachments.disk', 'local');
    }

    /**
     * @param  list<string>  $paths
     */
    private function discard(string $disk, array $paths): void
    {
        foreach ($paths as $path) {
            Storage::disk($disk)->delete($path);
        }
    }
}
