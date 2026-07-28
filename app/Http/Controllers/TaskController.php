<?php

namespace App\Http\Controllers;

use App\Actions\Task\CreateTaskAction;
use App\Actions\Task\DeleteTaskAction;
use App\Actions\Task\TransitionTaskStatusAction;
use App\Actions\Task\UpdateTaskAction;
use App\Enums\PermissionName;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Http\Requests\DispatchTaskRequest;
use App\Http\Requests\IndexTaskRequest;
use App\Http\Requests\StartTaskRequest;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\SubmitTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Models\OrganizationUnit;
use App\Models\Task;
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
            'assignableUsers' => $this->assignableUsers(),
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
            'statusHistories.actor:id,name,avatar_path',
        ]);

        return Inertia::render('Tasks/Show', [
            'task' => [
                ...$task->toArray(),
                'is_overdue' => $task->isOverdue(),
                'description_html' => $descriptionSanitizer->sanitize($task->description),
            ],
            'actions' => [
                'dispatch' => $task->status === TaskStatus::Draft
                    && request()->user()->can('dispatch', $task),
                'start' => $task->status === TaskStatus::Todo
                    && request()->user()->can('start', $task),
                'submit' => $task->status === TaskStatus::InProgress
                    && request()->user()->can('submit', $task),
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
        $action->execute($task, $request->validated());

        return Redirect::route('tasks.index')->with('success', 'Cập nhật công việc thành công.');
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
        $action->execute($request->user(), $task, TaskStatus::Todo);

        return Redirect::back()->with('success', 'Đã giao công việc.');
    }

    public function start(
        StartTaskRequest $request,
        Task $task,
        TransitionTaskStatusAction $action,
    ): RedirectResponse {
        $action->execute($request->user(), $task, TaskStatus::InProgress);

        return Redirect::back()->with('success', 'Đã bắt đầu công việc.');
    }

    public function submit(
        SubmitTaskRequest $request,
        Task $task,
        TransitionTaskStatusAction $action,
    ): RedirectResponse {
        $action->execute($request->user(), $task, TaskStatus::WaitingReview);

        return Redirect::back()->with('success', 'Đã gửi công việc để kiểm tra.');
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
    private function assignableUsers(): array
    {
        if (! request()->user()->can(PermissionName::TaskAssign->value)) {
            return [];
        }

        return $this->activeUsers();
    }
}
