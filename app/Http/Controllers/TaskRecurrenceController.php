<?php

namespace App\Http\Controllers;

use App\Actions\TaskRecurrence\CreateTaskRecurrenceAction;
use App\Actions\TaskRecurrence\DeleteTaskRecurrenceAction;
use App\Actions\TaskRecurrence\ToggleTaskRecurrenceAction;
use App\Actions\TaskRecurrence\UpdateTaskRecurrenceAction;
use App\Enums\PermissionName;
use App\Enums\RecurrenceFrequency;
use App\Enums\TaskPriority;
use App\Http\Requests\IndexTaskRecurrenceRequest;
use App\Http\Requests\StoreTaskRecurrenceRequest;
use App\Http\Requests\UpdateTaskRecurrenceRequest;
use App\Models\OrganizationUnit;
use App\Models\Project;
use App\Models\TaskRecurrence;
use App\Models\User;
use App\Support\RecurrenceSchedule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

final class TaskRecurrenceController extends Controller
{
    public function index(IndexTaskRecurrenceRequest $request, RecurrenceSchedule $schedule): Response
    {
        $filters = $request->validated();

        $recurrences = TaskRecurrence::query()
            ->with([
                'organizationUnit:id,name',
                'project:id,name,code',
                'assignee:id,name,avatar_path',
            ])
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query
                ->where('title', 'like', "%{$search}%"))
            ->when($filters['organization_unit_id'] ?? null, fn (Builder $query, int $unitId) => $query
                ->where('organization_unit_id', $unitId))
            ->when($filters['assignee_id'] ?? null, fn (Builder $query, int $assigneeId) => $query
                ->where('assignee_id', $assigneeId))
            ->when($filters['frequency'] ?? null, fn (Builder $query, string $frequency) => $query
                ->where('frequency', $frequency))
            ->when(array_key_exists('is_active', $filters) && $filters['is_active'] !== null, fn (Builder $query) => $query
                ->where('is_active', $request->boolean('is_active')))
            ->latest('id')
            ->paginate(20)
            ->through(fn (TaskRecurrence $recurrence): array => [
                ...$recurrence->toArray(),
                'cadence' => $schedule->describe($recurrence),
                'next_occurrence' => $recurrence->is_active
                    ? $schedule->nextOccurrenceAfter($recurrence, now())?->toDateString()
                    : null,
            ])
            ->withQueryString();

        return Inertia::render('TaskRecurrences/Index', [
            'recurrences' => $recurrences,
            'filters' => $filters,
            'frequencies' => $this->frequencyOptions(),
            'organizationUnits' => $this->organizationUnits(),
            'users' => $this->activeUsers(),
            'can' => [
                'create' => $request->user()->can('create', TaskRecurrence::class),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', TaskRecurrence::class);

        return Inertia::render('TaskRecurrences/Create', [
            'organizationUnits' => $this->organizationUnits(),
            'assignableUsers' => $this->assignableUsers($request, includeCurrentUser: true),
            'priorities' => $this->enumValues(TaskPriority::cases()),
            'frequencies' => $this->frequencyOptions(),
            'projects' => $this->openProjects($request),
        ]);
    }

    public function show(Request $request, TaskRecurrence $taskRecurrence, RecurrenceSchedule $schedule): Response
    {
        $this->authorize('view', $taskRecurrence);

        $taskRecurrence->load([
            'organizationUnit:id,name',
            'project:id,name,code',
            'creator:id,name,avatar_path',
            'assignee:id,name,avatar_path',
        ]);

        $tasks = $taskRecurrence->tasks()
            ->with(['assignee:id,name,avatar_path'])
            ->paginate(
                perPage: 20,
                pageName: 'tasks_page',
            )
            ->withQueryString();

        return Inertia::render('TaskRecurrences/Show', [
            'recurrence' => [
                ...$taskRecurrence->toArray(),
                'cadence' => $schedule->describe($taskRecurrence),
                'next_occurrence' => $taskRecurrence->is_active
                    ? $schedule->nextOccurrenceAfter($taskRecurrence, now())?->toDateString()
                    : null,
            ],
            'tasks' => $tasks,
            'actions' => [
                'update' => $request->user()->can('update', $taskRecurrence),
                'delete' => $request->user()->can('delete', $taskRecurrence),
                'toggle' => $request->user()->can('toggle', $taskRecurrence),
            ],
        ]);
    }

    public function store(StoreTaskRecurrenceRequest $request, CreateTaskRecurrenceAction $action): RedirectResponse
    {
        $action->execute($request->user(), $request->validated());

        return Redirect::route('task-recurrences.index')->with('success', 'Tạo mẫu công việc định kỳ thành công.');
    }

    public function edit(Request $request, TaskRecurrence $taskRecurrence): Response
    {
        $this->authorize('update', $taskRecurrence);

        return Inertia::render('TaskRecurrences/Edit', [
            'recurrence' => $taskRecurrence->only([
                'id',
                'organization_unit_id',
                'project_id',
                'assignee_id',
                'title',
                'description',
                'priority',
                'planned_quantity',
                'quantity_unit',
                'frequency',
                'interval',
                'weekdays',
                'day_of_month',
                'start_date',
                'due_time',
                'is_active',
            ]),
            'organizationUnits' => $this->organizationUnits(),
            'assignableUsers' => $this->assignableUsers($request, includeCurrentUser: true),
            'priorities' => $this->enumValues(TaskPriority::cases()),
            'frequencies' => $this->frequencyOptions(),
            'projects' => $this->openProjects($request),
        ]);
    }

    public function update(
        UpdateTaskRecurrenceRequest $request,
        TaskRecurrence $taskRecurrence,
        UpdateTaskRecurrenceAction $action,
    ): RedirectResponse {
        $action->execute($request->user(), $taskRecurrence, $request->validated());

        return Redirect::route('task-recurrences.index')->with('success', 'Cập nhật mẫu công việc định kỳ thành công.');
    }

    public function destroy(Request $request, TaskRecurrence $taskRecurrence, DeleteTaskRecurrenceAction $action): RedirectResponse
    {
        $this->authorize('delete', $taskRecurrence);

        $action->execute($request->user(), $taskRecurrence);

        return Redirect::route('task-recurrences.index')->with('success', 'Xóa mẫu công việc định kỳ thành công.');
    }

    public function toggle(Request $request, TaskRecurrence $taskRecurrence, ToggleTaskRecurrenceAction $action): RedirectResponse
    {
        $this->authorize('toggle', $taskRecurrence);

        $action->execute($request->user(), $taskRecurrence);

        return Redirect::back()->with('success', 'Đã cập nhật trạng thái mẫu công việc định kỳ.');
    }

    /**
     * @param  array<int, TaskPriority|RecurrenceFrequency>  $cases
     * @return list<string>
     */
    private function enumValues(array $cases): array
    {
        return array_map(
            static fn (TaskPriority|RecurrenceFrequency $case): string => $case->value,
            $cases,
        );
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function frequencyOptions(): array
    {
        return array_map(static fn (RecurrenceFrequency $case): array => [
            'value' => $case->value,
            'label' => $case->label(),
        ], RecurrenceFrequency::cases());
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
    private function openProjects(Request $request): array
    {
        return Project::query()
            ->open()
            ->visibleTo($request->user())
            ->orderBy('name')
            ->get(['id', 'name', 'code'])
            ->toArray();
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    private function assignableUsers(Request $request, bool $includeCurrentUser = false): array
    {
        if ($request->user()->can(PermissionName::TaskAssign->value)) {
            return $this->activeUsers();
        }

        if (! $includeCurrentUser) {
            return [];
        }

        return [[
            'id' => $request->user()->id,
            'name' => $request->user()->name,
        ]];
    }
}
