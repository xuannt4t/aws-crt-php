<?php

namespace App\Http\Controllers;

use App\Actions\Task\DeleteTaskAttachmentAction;
use App\Actions\Task\StoreTaskAttachmentAction;
use App\Http\Requests\StoreTaskAttachmentRequest;
use App\Models\Task;
use App\Models\TaskAttachment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class TaskAttachmentController extends Controller
{
    public function store(
        StoreTaskAttachmentRequest $request,
        Task $task,
        StoreTaskAttachmentAction $action,
    ): RedirectResponse {
        $action->execute($request->user(), $task, array_values($request->file('files')));

        return Redirect::route('tasks.show', $task)->with('success', 'Đã tải tệp đính kèm lên.');
    }

    public function download(Task $task, TaskAttachment $attachment): StreamedResponse
    {
        $this->authorize('downloadAttachment', $task);

        // Disk `local` cấu hình 'throw' => false: nếu tệp đã mất trên disk mà bản ghi DB
        // còn, Storage::download() trả file rỗng kèm status 200 thay vì báo lỗi. Kiểm tra
        // tồn tại trước để trả 404 rõ ràng.
        abort_unless(
            Storage::disk($attachment->disk)->exists($attachment->path),
            404,
        );

        return Storage::disk($attachment->disk)
            ->download($attachment->path, $attachment->original_name);
    }

    public function destroy(
        Request $request,
        Task $task,
        TaskAttachment $attachment,
        DeleteTaskAttachmentAction $action,
    ): RedirectResponse {
        $this->authorize('delete', $attachment);

        $action->execute($request->user(), $attachment);

        return Redirect::route('tasks.show', $task)->with('success', 'Đã xoá tệp đính kèm.');
    }
}
