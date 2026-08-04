<?php

namespace App\Http\Controllers;

use App\Actions\Task\CreateTaskAction;
use App\Actions\Task\DeleteTaskAction;
use App\Actions\Task\TransitionTaskStatusAction;
use App\Actions\Task\UpdateTaskAction;
use App\Actions\Task\UpdateTaskActualQuantityAction;
use App\Actions\Task\UpdateTaskProgressAction;
use App\Enums\PermissionName;
use App\Enums\TaskContext;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Http\Requests\DispatchTaskRequest;
use App\Http\Requests\IndexTaskDepartmentListRequest;
use App\Http\Requests\IndexTaskProjectListRequest;
use App\Http\Requests\IndexTaskRequest;
use App\Http\Requests\RecallTaskRequest;
use App\Http\Requests\StartTaskRequest;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\SubmitTaskRequest;
use App\Http\Requests\UpdateTaskProgressRequest;
use App\Http\Requests\UpdateTaskQuantityRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Models\OrganizationUnit;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskAttachment;
use App\Models\User;
use App\Support\TaskDescriptionSanitizer;
use App\Support\TaskSummary;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

final class TaskController extends Controller
{
    /**
     * Bộ lọc hiển thị của ba màn công việc (spec §5.2) — giống nhau ở cả ba
     * bối cảnh, là một prop tường minh thay vì rải v-if trong template.
     *
     * @var list<string>
     */
    private const AVAILABLE_FILTERS = [
        'search',
        'organization_unit_id',
        'project_id',
        'assignee_ids',
        'status',
        'priority',
    ];

    public function index(IndexTaskRequest $request): Response
    {
        return $this->renderTasks($request, TaskContext::Overview);
    }

    /**
     * Cấp 1 của "Việc dự án" (spec §5.2) — danh sách dự án người xem thấy
     * được, không phải danh sách việc làm phẳng. Bấm vào một dòng mới ra
     * `tasks.projects.show` (cấp 2, ba khúc cố định dự án đó).
     */
    public function projects(IndexTaskProjectListRequest $request): Response
    {
        $user = $request->user();
        $filters = $request->validated();

        $projects = Project::query()
            ->visibleTo($user)
            ->with(['organizationUnit:id,name', 'owner:id,name'])
            ->withCount([
                'tasks as task_count' => fn (Builder $query) => $query->visibleTo($user),
                'tasks as overdue_task_count' => fn (Builder $query) => $query->visibleTo($user)->overdue(),
            ])
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query
                ->where(fn (Builder $inner) => $inner
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Tasks/Projects', [
            'projects' => $projects,
            'filters' => $filters,
        ]);
    }

    /**
     * Cấp 2 của "Việc dự án" (spec §5.2) — ba khúc, cố định đúng một dự án
     * lấy từ route binding. 403 nếu dự án ngoài phạm vi người xem
     * (`Project::scopeVisibleTo` qua `ProjectPolicy::view`).
     */
    public function projectShow(IndexTaskRequest $request, Project $project): Response
    {
        $this->authorize('view', $project);

        return $this->renderTasks($request, TaskContext::Project, project: $project);
    }

    /**
     * Cấp 1 của "Việc phòng ban" (spec §5.2) — chỉ những đơn vị còn ít nhất
     * một việc trong phạm vi người xem (`Task::scopeVisibleTo`), với số đếm
     * cũng chỉ tính việc người đó xem được.
     */
    public function departments(IndexTaskDepartmentListRequest $request): Response
    {
        $user = $request->user();
        $filters = $request->validated();

        $units = OrganizationUnit::query()
            ->whereHas('tasks', fn (Builder $query) => $query->visibleTo($user))
            ->withCount([
                'tasks as task_count' => fn (Builder $query) => $query->visibleTo($user),
                'tasks as overdue_task_count' => fn (Builder $query) => $query->visibleTo($user)->overdue(),
            ])
            ->with('parent:id,name')
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query
                ->where('name', 'like', "%{$search}%"))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Tasks/Departments', [
            'units' => $units,
            'filters' => $filters,
        ]);
    }

    /**
     * Cấp 2 của "Việc phòng ban" (spec §5.2) — ba khúc, cố định một phòng lấy
     * từ route binding, gồm cả đơn vị con (`OrganizationUnit::descendantIdsOf`)
     * để trưởng phòng thấy đúng phần mình quản lý. Đơn vị không có việc nào
     * trong phạm vi trả trang rỗng, không phải 403 — không có gì để lộ.
     */
    public function departmentShow(IndexTaskRequest $request, OrganizationUnit $organizationUnit): Response
    {
        return $this->renderTasks($request, TaskContext::Department, organizationUnit: $organizationUnit);
    }

    /**
     * Bối cảnh do route quyết định (spec §5.1) — KHÔNG bao giờ đọc từ query
     * string, và chỉ thu hẹp thêm tập việc của phạm vi dữ liệu hiệu lực
     * (`Task::scopeVisibleTo`), không bao giờ mở rộng nó.
     *
     * $project / $organizationUnit khoá phạm vi cấp 2 (spec §5.2) bằng khoá
     * chính lấy từ route binding — không đọc từ query string.
     */
    private function renderTasks(
        IndexTaskRequest $request,
        TaskContext $context,
        ?Project $project = null,
        ?OrganizationUnit $organizationUnit = null,
    ): Response {
        $filters = $request->validated();

        if (isset($filters['assignee_ids'])) {
            $filters['assignee_ids'] = array_map('intval', $filters['assignee_ids']);
        }

        $baseQuery = Task::query()
            ->with([
                'organizationUnit:id,name',
                'creator:id,name,avatar_path',
                'assignee:id,name,avatar_path',
                'project:id,name,code',
                'recurrence:id,title,deleted_at',
            ])
            ->visibleTo($request->user())
            ->when($context === TaskContext::Project, fn (Builder $query) => $query
                ->whereNotNull('project_id'))
            ->when($context === TaskContext::Department, fn (Builder $query) => $query
                ->whereNotNull('organization_unit_id'))
            ->when($project !== null, fn (Builder $query) => $query
                ->where('project_id', $project->id))
            ->when($organizationUnit !== null, fn (Builder $query) => $query
                ->whereIn('organization_unit_id', OrganizationUnit::descendantIdsOf($organizationUnit->id)))
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query
                ->where('title', 'like', "%{$search}%"))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query
                ->where('status', $status))
            ->when($filters['priority'] ?? null, fn (Builder $query, string $priority) => $query
                ->where('priority', $priority))
            // organization_unit_id/project_id của query string chỉ áp dụng khi
            // cấp 2 KHÔNG đã khoá sẵn trục đó bằng route binding — phạm vi cấp
            // 2 không đọc từ query string (spec §5.2).
            ->when(
                $organizationUnit === null ? ($filters['organization_unit_id'] ?? null) : null,
                fn (Builder $query, int $unitId) => $query->where('organization_unit_id', $unitId),
            )
            ->when($filters['assignee_ids'] ?? null, fn (Builder $query, array $assigneeIds) => $query
                ->whereIn('assignee_id', $assigneeIds))
            ->when(
                $project === null ? ($filters['project_id'] ?? null) : null,
                fn (Builder $query, int $projectId) => $query->where('project_id', $projectId),
            )
            ->when($request->boolean('overdue'), fn (Builder $query) => $query->overdue());

        $summary = app(TaskSummary::class)->for(clone $baseQuery);

        $tasks = (clone $baseQuery)
            ->latest('id')
            ->paginate(20)
            ->through(fn (Task $task): array => [
                ...$task->toArray(),
                'is_overdue' => $task->isOverdue(),
            ])
            ->withQueryString();

        // Khúc 2 (spec §5.2, §5.3): cấp 2 bỏ bộ lọc đã cố định bằng route —
        // dự án khi cố định một dự án, phòng ban khi cố định một đơn vị.
        $availableFilters = array_values(array_filter(
            self::AVAILABLE_FILTERS,
            fn (string $key): bool => ($key !== 'project_id' || $project === null)
                && ($key !== 'organization_unit_id' || $organizationUnit === null),
        ));

        return Inertia::render('Tasks/Index', [
            'tasks' => $tasks,
            'filters' => $filters,
            'statuses' => $this->enumValues(TaskStatus::cases()),
            'priorities' => $this->enumValues(TaskPriority::cases()),
            'organizationUnits' => $this->organizationUnits(),
            'users' => $this->activeUsers(),
            'projects' => $this->openProjects(),
            'summary' => $summary,
            'context' => $context->value,
            'availableFilters' => $availableFilters,
            'scope' => match (true) {
                $project !== null => [
                    'type' => 'project',
                    'id' => $project->id,
                    'name' => $project->name,
                    'backRouteName' => 'tasks.projects',
                ],
                $organizationUnit !== null => [
                    'type' => 'department',
                    'id' => $organizationUnit->id,
                    'name' => $organizationUnit->name,
                    'backRouteName' => 'tasks.departments',
                ],
                default => null,
            },
            'applyRoute' => match (true) {
                $project !== null => ['name' => 'tasks.projects.show', 'params' => ['project' => $project->id]],
                $organizationUnit !== null => [
                    'name' => 'tasks.departments.show',
                    'params' => ['organizationUnit' => $organizationUnit->id],
                ],
                default => ['name' => 'tasks.index', 'params' => []],
            },
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Task::class);

        return Inertia::render('Tasks/Create', [
            'organizationUnits' => $this->organizationUnits(),
            'assignableUsers' => $this->assignableUsers(includeCurrentUser: true),
            'priorities' => $this->enumValues(TaskPriority::cases()),
            'projects' => $this->openProjects(),
        ]);
    }

    public function show(Task $task, TaskDescriptionSanitizer $descriptionSanitizer): Response
    {
        $this->authorize('view', $task);

        $task->load([
            'organizationUnit:id,name',
            'creator:id,name,avatar_path',
            'assignee:id,name,avatar_path',
            'project:id,name,code',
            'recurrence:id,title,deleted_at',
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
                'project_id',
                'parent_id',
                'assignee_id',
                'title',
                'description',
                'priority',
                'due_at',
                'planned_quantity',
                'quantity_unit',
            ]),
            'organizationUnits' => $this->organizationUnits(),
            'assignableUsers' => $this->assignableUsers(),
            'priorities' => $this->enumValues(TaskPriority::cases()),
            'projects' => $this->openProjects(),
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

    public function updateQuantity(
        UpdateTaskQuantityRequest $request,
        Task $task,
        UpdateTaskActualQuantityAction $action,
    ): RedirectResponse {
        $action->execute($request->user(), $task, $request->integer('actual_quantity'));

        return Redirect::back()->with('success', 'Đã cập nhật số lượng đã làm.');
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
     * @return array<int, array{id: int, name: string, code: string}>
     */
    private function openProjects(): array
    {
        return Project::query()
            ->open()
            ->visibleTo(request()->user())
            ->orderBy('name')
            ->get(['id', 'name', 'code'])
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
