<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Project\CloseProjectAction;
use App\Actions\Project\CreateProjectAction;
use App\Actions\Project\DeleteProjectAction;
use App\Actions\Project\UpdateProjectAction;
use App\Enums\TaskStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\CloseProjectRequest;
use App\Http\Requests\IndexProjectRequest;
use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Http\Resources\Api\V1\ProjectMemberResource;
use App\Http\Resources\Api\V1\ProjectResource;
use App\Http\Resources\Api\V1\TaskResource;
use App\Http\Responses\ApiResponse;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class ProjectController extends Controller
{
    public function index(IndexProjectRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $user = $request->user();
        $projects = Project::query()
            ->visibleTo($user)
            ->with(['organizationUnit:id,name', 'owner:id,name,avatar_path'])
            ->withCount([
                'tasks as task_count' => fn (Builder $query) => $query->visibleTo($user),
                'tasks as open_task_count' => fn (Builder $query) => $query->visibleTo($user)->whereNotIn('status', [TaskStatus::Completed->value, TaskStatus::Cancelled->value]),
                'members as member_count',
            ])
            ->withAvg(['tasks as progress_average' => fn (Builder $query) => $query->visibleTo($user)->where('status', '!=', TaskStatus::Cancelled->value)], 'progress')
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where(fn (Builder $inner) => $inner
                ->where('name', 'like', "%{$search}%")
                ->orWhere('code', 'like', "%{$search}%")))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['organization_unit_id'] ?? null, fn (Builder $query, int $id) => $query->where('organization_unit_id', $id))
            ->when($filters['owner_id'] ?? null, fn (Builder $query, int $id) => $query->where('owner_id', $id))
            ->when($request->boolean('only_mine'), fn (Builder $query) => $query->whereHas('members', fn (Builder $members) => $members->where('user_id', $user->id)))
            ->latest('id')
            ->paginate($this->perPage($request));

        $projects->getCollection()->each(function (Project $project): void {
            $project->setAttribute('progress', $project->progress_average === null ? 0 : (int) round((float) $project->progress_average));
        });

        return ApiResponse::paginated($projects, ProjectResource::collection($projects->getCollection())->resolve($request));
    }

    public function show(Request $request, Project $project): JsonResponse
    {
        $members = $project->members()->with('user:id,name,email,avatar_path')->get();
        $project->setRelation('members', $members);
        $this->authorize('view', $project);
        $project->load(['organizationUnit:id,name', 'owner:id,name,avatar_path']);
        $project->setAttribute('progress', $project->calculateProgress($request->user()));
        $project->setAttribute('open_task_count', $project->openTasks()->visibleTo($request->user())->count());
        $project->setAttribute('task_count', $project->tasks()->visibleTo($request->user())->count());
        $project->setAttribute('member_count', $members->count());

        $tasks = $request->user()->can('viewAny', Task::class)
            ? $project->tasks()->visibleTo($request->user())
                ->with(['organizationUnit:id,name', 'creator:id,name,avatar_path', 'assignee:id,name,avatar_path'])
                ->latest('id')->paginate($this->perPage($request), pageName: 'tasks_page')
            : null;

        return ApiResponse::success([
            'project' => ProjectResource::make($project)->resolve($request),
            'members' => ProjectMemberResource::collection($members)->resolve($request),
            'tasks' => $tasks ? [
                'data' => TaskResource::collection($tasks->getCollection())->resolve($request),
                'meta' => [
                    'current_page' => $tasks->currentPage(),
                    'per_page' => $tasks->perPage(),
                    'last_page' => $tasks->lastPage(),
                    'total' => $tasks->total(),
                ],
            ] : null,
        ]);
    }

    public function store(StoreProjectRequest $request, CreateProjectAction $action): JsonResponse
    {
        $project = $action->execute($request->user(), $request->validated());

        return ApiResponse::success($this->resource($request, $project), 'Tạo việc dự án thành công.', 201);
    }

    public function update(UpdateProjectRequest $request, Project $project, UpdateProjectAction $action): JsonResponse
    {
        $project = $action->execute($request->user(), $project, $request->validated());

        return ApiResponse::success($this->resource($request, $project), 'Cập nhật việc dự án thành công.');
    }

    public function destroy(Request $request, Project $project, DeleteProjectAction $action): JsonResponse
    {
        $this->authorize('delete', $project);
        $action->execute($request->user(), $project);

        return ApiResponse::success(null, 'Xóa việc dự án thành công.');
    }

    public function close(CloseProjectRequest $request, Project $project, CloseProjectAction $action): JsonResponse
    {
        try {
            $project = $action->execute($request->user(), $project, $request->validated('close_reason'));
        } catch (ValidationException $exception) {
            return ApiResponse::error('Trạng thái việc dự án không hợp lệ.', 409, $exception->errors());
        }

        return ApiResponse::success($this->resource($request, $project), 'Đã đóng việc dự án.');
    }

    private function resource(Request $request, Project $project): array
    {
        $project->load(['organizationUnit:id,name', 'owner:id,name,avatar_path', 'members']);
        $project->setAttribute('progress', $project->calculateProgress($request->user()));
        $project->setAttribute('open_task_count', $project->openTasks()->visibleTo($request->user())->count());
        $project->setAttribute('task_count', $project->tasks()->visibleTo($request->user())->count());
        $project->setAttribute('member_count', $project->members->count());

        return ProjectResource::make($project)->resolve($request);
    }

    private function perPage(Request $request): int
    {
        return min(max($request->integer('per_page', 20), 1), 100);
    }
}
