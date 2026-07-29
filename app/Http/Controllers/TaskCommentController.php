<?php

namespace App\Http\Controllers;

use App\Actions\Task\CreateTaskCommentAction;
use App\Http\Requests\StoreTaskCommentRequest;
use App\Models\Task;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;

final class TaskCommentController extends Controller
{
    public function store(
        StoreTaskCommentRequest $request,
        Task $task,
        CreateTaskCommentAction $action,
    ): RedirectResponse {
        $action->execute($request->user(), $task, $request->string('body')->toString());

        return Redirect::route('tasks.show', $task)->with('success', 'Đã thêm nội dung trao đổi.');
    }
}
