<?php

namespace App\Http\Controllers;

use App\Actions\Project\CloseProjectAction;
use App\Actions\Project\CreateProjectAction;
use App\Actions\Project\DeleteProjectAction;
use App\Actions\Project\UpdateProjectAction;
use App\Enums\ProjectStatus;
use App\Enums\TaskStatus;
use App\Http\Requests\CloseProjectRequest;
use App\Http\Requests\IndexProjectRequest;
use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Models\OrganizationUnit;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

final class ProjectController extends Controller
{
    public function index(IndexProjectRequest $request): Response
    {
        $filters = $request->validated();

        $projects = Project::query()
            ->with(['organizationUnit:id,name', 'owner:id,name'])
            ->withCount([
                'tasks as task_count',
                'tasks as open_task_count' => fn (Builder $query) => $query->whereNotIn('status', [
                    TaskStatus::Completed->value,
                    TaskStatus::Cancelled->value,
                ]),
                'members as member_count',
            ])
            ->withAvg([
                'tasks as progress_average' => fn (Builder $query) => $query
                    ->where('status', '!=', TaskStatus::Cancelled->value),
            ], 'progress')
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query
                ->where(fn (Builder $inner) => $inner
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query
                ->where('status', $status))
            ->when($filters['organization_unit_id'] ?? null, fn (Builder $query, int $unitId) => $query
                ->where('organization_unit_id', $unitId))
            ->when($filters['owner_id'] ?? null, fn (Builder $query, int $ownerId) => $query
                ->where('owner_id', $ownerId))
            ->when($request->boolean('only_mine'), fn (Builder $query) => $query
                ->whereHas('members', fn (Builder $inner) => $inner
                    ->where('user_id', $request->user()->id)))
            ->latest('id')
            ->paginate(20)
            ->through(fn (Project $project): array => [
                ...$project->toArray(),
                'progress' => $project->progress_average === null ? 0 : (int) round((float) $project->progress_average),
                'task_count' => $project->task_count,
                'open_task_count' => $project->open_task_count,
                'member_count' => $project->member_count,
            ])
            ->withQueryString();

        return Inertia::render('Projects/Index', [
            'projects' => $projects,
            'filters' => $filters,
            'statuses' => $this->enumValues(ProjectStatus::cases()),
            'organizationUnits' => $this->organizationUnits(),
            'users' => $this->activeUsers(),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Project::class);

        return Inertia::render('Projects/Create', [
            'organizationUnits' => $this->organizationUnits(),
            'users' => $this->activeUsers(),
            'statuses' => $this->enumValues(ProjectStatus::cases()),
        ]);
    }

    public function show(Project $project): Response
    {
        $this->authorize('view', $project);

        $project->load(['organizationUnit:id,name', 'owner:id,name']);

        $members = $project->members()
            ->with('user:id,name,avatar_path')
            ->get();

        $tasks = $project->tasks()
            ->with(['assignee:id,name,avatar_path'])
            ->latest('id')
            ->paginate(
                perPage: 20,
                pageName: 'tasks_page',
            )
            ->withQueryString();

        return Inertia::render('Projects/Show', [
            'project' => [
                ...$project->toArray(),
                'progress' => $project->calculateProgress(),
            ],
            'members' => $members,
            'tasks' => $tasks,
            'actions' => [
                'update' => request()->user()->can('update', $project),
                'delete' => request()->user()->can('delete', $project),
                'manageMembers' => request()->user()->can('manageMembers', $project),
                'close' => request()->user()->can('close', $project),
            ],
        ]);
    }

    public function store(StoreProjectRequest $request, CreateProjectAction $action): RedirectResponse
    {
        $action->execute($request->user(), $request->validated());

        return Redirect::route('projects.index')->with('success', 'Tạo dự án thành công.');
    }

    public function edit(Project $project): Response
    {
        $this->authorize('update', $project);

        return Inertia::render('Projects/Edit', [
            'project' => $project->only([
                'id',
                'organization_unit_id',
                'owner_id',
                'code',
                'name',
                'description',
                'status',
                'start_date',
                'end_date',
            ]),
            'organizationUnits' => $this->organizationUnits(),
            'users' => $this->activeUsers(),
            'statuses' => $this->enumValues(ProjectStatus::cases()),
        ]);
    }

    public function update(UpdateProjectRequest $request, Project $project, UpdateProjectAction $action): RedirectResponse
    {
        $action->execute($request->user(), $project, $request->validated());

        return Redirect::route('projects.index')->with('success', 'Cập nhật dự án thành công.');
    }

    public function destroy(Project $project, DeleteProjectAction $action): RedirectResponse
    {
        $this->authorize('delete', $project);

        $action->execute(request()->user(), $project);

        return Redirect::route('projects.index')->with('success', 'Xóa dự án thành công.');
    }

    public function close(CloseProjectRequest $request, Project $project, CloseProjectAction $action): RedirectResponse
    {
        $action->execute($request->user(), $project, $request->validated()['close_reason'] ?? null);

        return Redirect::back()->with('success', 'Đã đóng dự án.');
    }

    /**
     * @param  array<int, ProjectStatus>  $cases
     * @return list<string>
     */
    private function enumValues(array $cases): array
    {
        return array_map(static fn (ProjectStatus $case): string => $case->value, $cases);
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
}
