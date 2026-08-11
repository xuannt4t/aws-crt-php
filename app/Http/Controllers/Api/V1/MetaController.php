<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PermissionName;
use App\Enums\ProjectMemberRole;
use App\Enums\ProjectStatus;
use App\Enums\ProjectTaskVisibility;
use App\Enums\RecurrenceFrequency;
use App\Enums\RoleName;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\OrganizationUnit;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use UnitEnum;

final class MetaController extends Controller
{
    public function index(): JsonResponse
    {
        return ApiResponse::success([
            'task_statuses' => $this->options(TaskStatus::cases()),
            'task_priorities' => $this->options(TaskPriority::cases()),
            'project_statuses' => $this->options(ProjectStatus::cases()),
            'recurrence_frequencies' => $this->options(RecurrenceFrequency::cases()),
            'project_member_roles' => $this->options(ProjectMemberRole::cases()),
            'project_task_visibilities' => $this->options(ProjectTaskVisibility::cases()),
            'roles' => $this->options(RoleName::cases()),
            'limits' => [
                'default_per_page' => 20,
                'maximum_per_page' => 100,
                'avatar_max_kilobytes' => 2048,
                'task_description_max_characters' => 10000,
            ],
        ]);
    }

    public function users(Request $request): JsonResponse
    {
        $query = User::query()
            ->where('is_active', true)
            ->with(['organizationUnit:id,name', 'roles:id,name'])
            ->when(
                ! $request->user()->can(PermissionName::TaskAssign->value),
                fn (Builder $query) => $query->whereKey($request->user()->id),
            )
            ->when($request->string('search')->isNotEmpty(), function (Builder $query) use ($request) {
                $search = $request->string('search')->toString();

                $query->where(fn (Builder $inner) => $inner
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%"));
            })
            ->orderBy('name')
            ->paginate($this->perPage($request));

        $data = $query->getCollection()->map(fn (User $user) => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'job_title' => $user->job_title,
            'role' => $user->roles->first()?->name,
            'organization_unit_id' => $user->organization_unit_id,
            'organization_unit_name' => $user->organizationUnit?->name,
        ])->values()->all();

        return ApiResponse::paginated($query, $data);
    }

    public function projects(Request $request): JsonResponse
    {
        $query = Project::query()
            ->visibleTo($request->user())
            ->open()
            ->when($request->string('search')->isNotEmpty(), function (Builder $query) use ($request) {
                $search = $request->string('search')->toString();
                $query->where(fn (Builder $inner) => $inner
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%"));
            })
            ->orderBy('name')
            ->paginate($this->perPage($request));

        return ApiResponse::paginated($query, $query->getCollection()->map->only(['id', 'name', 'code'])->all());
    }

    public function organizationUnits(Request $request): JsonResponse
    {
        $query = OrganizationUnit::query()
            ->where('is_active', true)
            ->when($request->string('search')->isNotEmpty(), fn (Builder $query) => $query
                ->where('name', 'like', '%'.$request->string('search')->toString().'%'))
            ->orderBy('name')
            ->paginate($this->perPage($request));

        return ApiResponse::paginated($query, $query->getCollection()->map->only(['id', 'name', 'code', 'parent_id'])->all());
    }

    /**
     * @param  list<UnitEnum>  $cases
     * @return list<array{value: string, label: string}>
     */
    private function options(array $cases): array
    {
        return array_map(static fn (UnitEnum $case): array => [
            'value' => $case->value,
            'label' => method_exists($case, 'label') ? $case->label() : Str::headline($case->value),
        ], $cases);
    }

    private function perPage(Request $request): int
    {
        return min(max($request->integer('per_page', 20), 1), 100);
    }
}
