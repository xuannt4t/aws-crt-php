<?php

namespace App\Http\Controllers;

use App\Actions\Task\StoreTaskAttachmentAction;
use App\Http\Requests\StoreTaskAttachmentRequest;
use App\Models\Task;
use App\Models\TaskAttachment;
use Illuminate\Http\RedirectResponse;
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

        return Storage::disk($attachment->disk)
            ->download($attachment->path, $attachment->original_name);
    }
}
