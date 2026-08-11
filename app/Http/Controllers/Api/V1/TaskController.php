<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Task\CreateTaskAction;
use App\Actions\Task\CreateTaskCommentAction;
use App\Actions\Task\DeleteTaskAction;
use App\Actions\Task\DeleteTaskAttachmentAction;
use App\Actions\Task\StoreTaskAttachmentAction;
use App\Actions\Task\TransitionTaskStatusAction;
use App\Actions\Task\UpdateTaskAction;
use App\Actions\Task\UpdateTaskActualQuantityAction;
use App\Actions\Task\UpdateTaskProgressAction;
use App\Enums\TaskStatus;
use App\Enums\TaskStatusBucket;
use App\Http\Controllers\Controller;
use App\Http\Requests\ApproveTaskRequest;
use App\Http\Requests\DispatchTaskRequest;
use App\Http\Requests\IndexTaskDepartmentListRequest;
use App\Http\Requests\IndexTaskRequest;
use App\Http\Requests\RecallTaskRequest;
use App\Http\Requests\RejectTaskRequest;
use App\Http\Requests\StartTaskRequest;
use App\Http\Requests\StoreTaskAttachmentRequest;
use App\Http\Requests\StoreTaskCommentRequest;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\SubmitTaskRequest;
use App\Http\Requests\UpdateTaskProgressRequest;
use App\Http\Requests\UpdateTaskQuantityRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Http\Resources\Api\V1\TaskActivityResource;
use App\Http\Resources\Api\V1\TaskAttachmentResource;
use App\Http\Resources\Api\V1\TaskCommentResource;
use App\Http\Resources\Api\V1\TaskResource;
use App\Http\Responses\ApiResponse;
use App\Models\OrganizationUnit;
use App\Models\Task;
use App\Models\TaskAttachment;
use App\Support\TaskDescriptionSanitizer;
use App\Support\TaskSummary;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class TaskController extends Controller
{
    public function index(IndexTaskRequest $request, TaskSummary $summary): JsonResponse
    {
        return $this->taskListResponse($request, $summary);
    }

    public function departments(IndexTaskDepartmentListRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $units = OrganizationUnit::query()
            ->whereHas('tasks', fn (Builder $query) => $query->visibleTo($request->user()))
            ->with('parent:id,name')
            ->withCount([
                'tasks as task_count' => fn (Builder $query) => $query->visibleTo($request->user()),
                'tasks as overdue_task_count' => fn (Builder $query) => $query->visibleTo($request->user())->overdue(),
            ])
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query
                ->where('name', 'like', "%{$search}%"))
            ->orderBy('name')
            ->paginate($this->perPage($request));

        return ApiResponse::paginated($units, $units->getCollection()->map->toArray()->all());
    }

    public function departmentShow(
        IndexTaskRequest $request,
        OrganizationUnit $organizationUnit,
        TaskSummary $summary,
    ): JsonResponse {
        return $this->taskListResponse($request, $summary, $organizationUnit);
    }

    public function show(Request $request, Task $task, TaskDescriptionSanitizer $sanitizer): JsonResponse
    {
        $this->authorize('view', $task);
        $this->loadTask($task);

        $comments = $task->comments()->with('author:id,name,avatar_path')->latest('id')->paginate(20, pageName: 'comments_page');
        $activities = $task->activities()->with('actor:id,name,avatar_path')->paginate(30, pageName: 'activities_page');
        $attachments = $task->attachments()->with('uploader:id,name,avatar_path')->get();
        $taskData = TaskResource::make($task)->resolve($request);
        $taskData['description_html'] = $sanitizer->sanitize($task->description);

        return ApiResponse::success([
            'task' => $taskData,
            'comments' => $this->nestedPaginator($comments, TaskCommentResource::collection($comments->getCollection())->resolve($request)),
            'attachments' => TaskAttachmentResource::collection($attachments)->resolve($request),
            'activities' => $this->nestedPaginator($activities, TaskActivityResource::collection($activities->getCollection())->resolve($request)),
        ]);
    }

    public function store(StoreTaskRequest $request, CreateTaskAction $action): JsonResponse
    {
        $task = $action->execute($request->user(), $request->validated());

        return ApiResponse::success($this->taskResource($request, $task), 'Tạo công việc thành công.', 201);
    }

    public function update(UpdateTaskRequest $request, Task $task, UpdateTaskAction $action): JsonResponse
    {
        $task = $action->execute($request->user(), $task, $request->validated());

        return ApiResponse::success($this->taskResource($request, $task), 'Cập nhật công việc thành công.');
    }

    public function destroy(Request $request, Task $task, DeleteTaskAction $action): JsonResponse
    {
        $this->authorize('delete', $task);
        $action->execute($request->user(), $task);

        return ApiResponse::success(null, 'Xóa công việc thành công.');
    }

    public function dispatch(DispatchTaskRequest $request, Task $task, TransitionTaskStatusAction $action): JsonResponse
    {
        return $this->workflow($request, fn () => $action->execute($request->user(), $task, TaskStatus::Todo, TaskStatus::Draft), 'Đã giao công việc.');
    }

    public function start(StartTaskRequest $request, Task $task, TransitionTaskStatusAction $action): JsonResponse
    {
        return $this->workflow($request, fn () => $action->execute($request->user(), $task, TaskStatus::InProgress, TaskStatus::Todo), 'Đã bắt đầu công việc.');
    }

    public function submit(SubmitTaskRequest $request, Task $task, TransitionTaskStatusAction $action): JsonResponse
    {
        return $this->workflow($request, fn () => $action->execute($request->user(), $task, TaskStatus::WaitingReview, TaskStatus::InProgress), 'Đã gửi công việc để kiểm tra.');
    }

    public function recall(RecallTaskRequest $request, Task $task, TransitionTaskStatusAction $action): JsonResponse
    {
        return $this->workflow($request, fn () => $action->execute($request->user(), $task, TaskStatus::InProgress, TaskStatus::WaitingReview), 'Đã thu hồi yêu cầu kiểm tra.');
    }

    public function approve(ApproveTaskRequest $request, Task $task, TransitionTaskStatusAction $action): JsonResponse
    {
        return $this->workflow(
            $request,
            fn () => $action->execute($request->user(), $task, TaskStatus::Completed, TaskStatus::WaitingReview, $request->validated('reason')),
            'Đã duyệt và hoàn thành công việc.',
        );
    }

    public function reject(RejectTaskRequest $request, Task $task, TransitionTaskStatusAction $action): JsonResponse
    {
        return $this->workflow(
            $request,
            fn () => $action->execute($request->user(), $task, TaskStatus::InProgress, TaskStatus::WaitingReview, $request->validated('reason')),
            'Đã trả lại công việc.',
        );
    }

    public function updateProgress(UpdateTaskProgressRequest $request, Task $task, UpdateTaskProgressAction $action): JsonResponse
    {
        return $this->workflow(
            $request,
            fn () => $action->execute($request->user(), $task, $request->integer('progress')),
            'Đã cập nhật tiến độ.',
        );
    }

    public function updateQuantity(UpdateTaskQuantityRequest $request, Task $task, UpdateTaskActualQuantityAction $action): JsonResponse
    {
        return $this->workflow(
            $request,
            fn () => $action->execute($request->user(), $task, $request->integer('actual_quantity')),
            'Đã cập nhật số lượng.',
        );
    }

    public function storeComment(StoreTaskCommentRequest $request, Task $task, CreateTaskCommentAction $action): JsonResponse
    {
        $comment = $action->execute($request->user(), $task, $request->string('body')->toString());
        $comment->load('author:id,name,avatar_path');

        return ApiResponse::success(TaskCommentResource::make($comment)->resolve($request), 'Đã thêm nội dung trao đổi.', 201);
    }

    public function storeAttachments(StoreTaskAttachmentRequest $request, Task $task, StoreTaskAttachmentAction $action): JsonResponse
    {
        $action->execute($request->user(), $task, array_values($request->file('files')));
        $attachments = $task->attachments()->with('uploader:id,name,avatar_path')->get();

        return ApiResponse::success(TaskAttachmentResource::collection($attachments)->resolve($request), 'Đã tải tệp lên.', 201);
    }

    public function downloadAttachment(Task $task, TaskAttachment $attachment): StreamedResponse
    {
        $this->authorize('downloadAttachment', $task);
        abort_unless(Storage::disk($attachment->disk)->exists($attachment->path), 404);

        return Storage::disk($attachment->disk)->download($attachment->path, $attachment->original_name);
    }

    public function destroyAttachment(
        Request $request,
        Task $task,
        TaskAttachment $attachment,
        DeleteTaskAttachmentAction $action,
    ): JsonResponse {
        $this->authorize('delete', $attachment);
        $action->execute($request->user(), $task, $attachment);

        return ApiResponse::success(null, 'Đã xóa tệp đính kèm.');
    }

    private function taskListResponse(IndexTaskRequest $request, TaskSummary $summary, ?OrganizationUnit $unit = null): JsonResponse
    {
        $filters = $request->validated();
        $query = Task::query()
            ->with(['organizationUnit:id,name', 'creator:id,name,avatar_path', 'assignee:id,name,avatar_path', 'project:id,name,code,status', 'recurrence:id,title,deleted_at'])
            ->visibleTo($request->user())
            ->when($unit, fn (Builder $query) => $query->whereIn('organization_unit_id', OrganizationUnit::descendantIdsOf($unit->id)))
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where('title', 'like', "%{$search}%"))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['priority'] ?? null, fn (Builder $query, string $priority) => $query->where('priority', $priority))
            ->when(! $unit ? ($filters['organization_unit_id'] ?? null) : null, fn (Builder $query, int $id) => $query->where('organization_unit_id', $id))
            ->when($filters['project_id'] ?? null, fn (Builder $query, int $id) => $query->where('project_id', $id))
            ->when($filters['assignee_ids'] ?? null, fn (Builder $query, array $ids) => $query->whereIn('assignee_id', array_map('intval', $ids)));
        $summaryData = $summary->for(clone $query);
        $tasks = $query
            ->when($filters['bucket'] ?? null, fn (Builder $query, string $bucket) => $query->whereIn('status', TaskStatusBucket::from($bucket)->statuses()))
            ->when($request->boolean('overdue'), fn (Builder $query) => $query->overdue())
            ->latest('id')
            ->paginate($this->perPage($request));

        return ApiResponse::paginated(
            $tasks,
            TaskResource::collection($tasks->getCollection())->resolve($request),
            extraMeta: ['summary' => $summaryData],
        );
    }

    private function taskResource(Request $request, Task $task): array
    {
        $this->loadTask($task);

        return TaskResource::make($task)->resolve($request);
    }

    private function loadTask(Task $task): void
    {
        $task->load(['organizationUnit:id,name', 'creator:id,name,avatar_path', 'assignee:id,name,avatar_path', 'project:id,name,code,status', 'recurrence:id,title,deleted_at']);
    }

    /**
     * @param  Closure(): Task  $operation
     */
    private function workflow(Request $request, Closure $operation, string $message): JsonResponse
    {
        try {
            $task = $operation();
        } catch (ValidationException $exception) {
            return ApiResponse::error('Trạng thái công việc không hợp lệ.', 409, $exception->errors());
        }

        return ApiResponse::success($this->taskResource($request, $task), $message);
    }

    private function perPage(Request $request): int
    {
        return min(max($request->integer('per_page', 20), 1), 100);
    }

    private function nestedPaginator(LengthAwarePaginator $paginator, array $data): array
    {
        return [
            'data' => $data,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
            ],
        ];
    }
}
