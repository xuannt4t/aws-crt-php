<?php

namespace App\Http\Controllers;

use App\Actions\Task\CreateTaskAction;
use App\Actions\Task\DeleteTaskAction;
use App\Actions\Task\TransitionTaskStatusAction;
use App\Actions\Task\UpdateTaskAction;
use App\Actions\Task\UpdateTaskProgressAction;
use App\Enums\PermissionName;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Http\Requests\DispatchTaskRequest;
use App\Http\Requests\IndexTaskRequest;
use App\Http\Requests\RecallTaskRequest;
use App\Http\Requests\StartTaskRequest;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\SubmitTaskRequest;
use App\Http\Requests\UpdateTaskProgressRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Models\OrganizationUnit;
use App\Models\Task;
use App\Models\TaskAttachment;
use App\Models\User;
use App\Support\TaskDescriptionSanitizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

final class TaskController extends Controller
{
    public function index(IndexTaskRequest $request): Response
    {
        $filters = $request->validated();

        if (isset($filters['assignee_ids'])) {
            $filters['assignee_ids'] = array_map('intval', $filters['assignee_ids']);
        }

        $tasks = Task::query()
            ->with([
                'organizationUnit:id,name',
                'creator:id,name,avatar_path',
                'assignee:id,name,avatar_path',
            ])
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query
                ->where('title', 'like', "%{$search}%"))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query
                ->where('status', $status))
            ->when($filters['priority'] ?? null, fn (Builder $query, string $priority) => $query
                ->where('priority', $priority))
            ->when($filters['organization_unit_id'] ?? null, fn (Builder $query, int $unitId) => $query
                ->where('organization_unit_id', $unitId))
            ->when($filters['assignee_ids'] ?? null, fn (Builder $query, array $assigneeIds) => $query
                ->whereIn('assignee_id', $assigneeIds))
            ->when($request->boolean('overdue'), fn (Builder $query) => $query->overdue())
            ->latest('id')
            ->paginate(20)
            ->through(fn (Task $task): array => [
                ...$task->toArray(),
                'is_overdue' => $task->isOverdue(),
            ])
            ->withQueryString();

        return Inertia::render('Tasks/Index', [
            'tasks' => $tasks,
            'filters' => $filters,
            'statuses' => $this->enumValues(TaskStatus::cases()),
            'priorities' => $this->enumValues(TaskPriority::cases()),
            'organizationUnits' => $this->organizationUnits(),
            'users' => $this->activeUsers(),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Task::class);

        return Inertia::render('Tasks/Create', [
            'organizationUnits' => $this->organizationUnits(),
            'assignableUsers' => $this->assignableUsers(includeCurrentUser: true),
            'priorities' => $this->enumValues(TaskPriority::cases()),
        ]);
    }

    public function show(Task $task, TaskDescriptionSanitizer $descriptionSanitizer): Response
    {
        $this->authorize('view', $task);

        $task->load([
            'organizationUnit:id,name',
            'creator:id,name,avatar_path',
            'assignee:id,name,avatar_path',
        ]);

        $comments = $task->comments()
            ->with('author:id,name,avatar_path')
            ->latest('id')
            ->paginate(
                perPage: 20,
                columns: ['id', 'task_id', 'author_id', 'body', 'created_at'],
                pageName: 'comments_page',
            )
            ->withQueryString();

        $attachments = $task->attachments()
            ->with('uploader:id,name,avatar_path')
            ->get()
            ->map(fn (TaskAttachment $attachment): array => [
                'id' => $attachment->id,
                'original_name' => $attachment->original_name,
                'mime_type' => $attachment->mime_type,
                'size_bytes' => $attachment->size_bytes,
                'size_for_humans' => $attachment->size_for_humans,
                'created_at' => $attachment->created_at,
                'uploader' => $attachment->uploader === null ? null : [
                    'id' => $attachment->uploader->id,
                    'name' => $attachment->uploader->name,
                    'avatar_url' => $attachment->uploader->avatar_url,
                ],
                'can_delete' => request()->user()->can('delete', $attachment),
            ])
            ->all();

        $activities = $task->activities()
            ->with('actor:id,name,avatar_path')
            ->paginate(
                perPage: 30,
                columns: ['id', 'task_id', 'actor_id', 'type', 'payload', 'created_at'],
                pageName: 'activities_page',
            )
            ->withQueryString();

        return Inertia::render('Tasks/Show', [
            'task' => [
                ...$task->toArray(),
                'is_overdue' => $task->isOverdue(),
                'description_html' => $descriptionSanitizer->sanitize($task->description),
            ],
            'comments' => $comments,
            'attachments' => $attachments,
            'activities' => $activities,
            'actions' => [
                'dispatch' => $task->status === TaskStatus::Draft
                    && request()->user()->can('dispatch', $task),
                'start' => $task->status === TaskStatus::Todo
                    && request()->user()->can('start', $task),
                'submit' => $task->status === TaskStatus::InProgress
                    && request()->user()->can('submit', $task),
                'updateProgress' => $task->status === TaskStatus::InProgress
                    && request()->user()->can('updateProgress', $task),
                'recall' => $task->status === TaskStatus::WaitingReview
                    && request()->user()->can('recall', $task),
                'comment' => request()->user()->can('comment', $task),
                'attach' => request()->user()->can('attach', $task),
            ],
        ]);
    }

    public function store(StoreTaskRequest $request, CreateTaskAction $action): RedirectResponse
    {
        $action->execute($request->user(), $request->validated());

        return Redirect::route('tasks.index')->with('success', 'Tạo công việc thành công.');
    }

    public function edit(Task $task): Response
    {
        $this->authorize('update', $task);

        return Inertia::render('Tasks/Edit', [
            'task' => $task->only([
                'id',
                'organization_unit_id',
                'parent_id',
                'assignee_id',
                'title',
                'description',
                'priority',
                'due_at',
            ]),
            'organizationUnits' => $this->organizationUnits(),
            'assignableUsers' => $this->assignableUsers(),
            'priorities' => $this->enumValues(TaskPriority::cases()),
        ]);
    }

    public function update(UpdateTaskRequest $request, Task $task, UpdateTaskAction $action): RedirectResponse
    {
        $action->execute($request->user(), $task, $request->validated());

        return Redirect::route('tasks.index')->with('success', 'Cập nhật công việc thành công.');
    }

    public function updateProgress(
        UpdateTaskProgressRequest $request,
        Task $task,
        UpdateTaskProgressAction $action,
    ): RedirectResponse {
        $action->execute($request->user(), $task, $request->integer('progress'));

        return Redirect::back()->with('success', 'Đã cập nhật tiến độ công việc.');
    }

    public function destroy(Task $task, DeleteTaskAction $action): RedirectResponse
    {
        $this->authorize('delete', $task);

        $action->execute(request()->user(), $task);

        return Redirect::route('tasks.index')->with('success', 'Xóa công việc thành công.');
    }

    public function dispatch(
        DispatchTaskRequest $request,
        Task $task,
        TransitionTaskStatusAction $action,
    ): RedirectResponse {
        $action->execute($request->user(), $task, TaskStatus::Todo, TaskStatus::Draft);

        return Redirect::back()->with('success', 'Đã giao công việc.');
    }

    public function start(
        StartTaskRequest $request,
        Task $task,
        TransitionTaskStatusAction $action,
    ): RedirectResponse {
        $action->execute($request->user(), $task, TaskStatus::InProgress, TaskStatus::Todo);

        return Redirect::back()->with('success', 'Đã bắt đầu công việc.');
    }

    public function submit(
        SubmitTaskRequest $request,
        Task $task,
        TransitionTaskStatusAction $action,
    ): RedirectResponse {
        $action->execute($request->user(), $task, TaskStatus::WaitingReview, TaskStatus::InProgress);

        return Redirect::back()->with('success', 'Đã gửi công việc để kiểm tra.');
    }

    public function recall(
        RecallTaskRequest $request,
        Task $task,
        TransitionTaskStatusAction $action,
    ): RedirectResponse {
        $action->execute($request->user(), $task, TaskStatus::InProgress, TaskStatus::WaitingReview);

        return Redirect::back()->with('success', 'Đã thu hồi yêu cầu kiểm tra.');
    }

    /**
     * @param  array<int, TaskStatus|TaskPriority>  $cases
     * @return list<string>
     */
    private function enumValues(array $cases): array
    {
        return array_map(
            static fn (TaskStatus|TaskPriority $case): string => $case->value,
            $cases,
        );
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    private function organizationUnits(): array
    {
        return OrganizationUnit::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->toArray();
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    private function activeUsers(): array
    {
        return User::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->toArray();
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    private function assignableUsers(bool $includeCurrentUser = false): array
    {
        if (request()->user()->can(PermissionName::TaskAssign->value)) {
            return $this->activeUsers();
        }

        if (! $includeCurrentUser) {
            return [];
        }

        return [[
            'id' => request()->user()->id,
            'name' => request()->user()->name,
        ]];
    }
}
