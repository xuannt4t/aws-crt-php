<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Permission\UpdateRolePermissionsAction;
use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdatePermissionMatrixRequest;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

final class PermissionMatrixController extends Controller
{
    private const MODULE_LABELS = ['organization' => 'Đơn vị', 'user' => 'Người dùng', 'task' => 'Công việc', 'project' => 'Việc dự án', 'report' => 'Báo cáo', 'system' => 'Hệ thống'];

    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->can(PermissionName::SystemManageSettings->value), 403);

        return ApiResponse::success($this->matrix());
    }

    public function update(UpdatePermissionMatrixRequest $request, UpdateRolePermissionsAction $action): JsonResponse
    {
        $changed = $action->execute($request->user(), $request->validated('matrix'));

        return ApiResponse::success(['changed_roles' => $changed, ...$this->matrix()], 'Cập nhật ma trận phân quyền thành công.');
    }

    private function matrix(): array
    {
        $roles = Role::query()->where('guard_name', 'web')->with('permissions')->orderBy('id')->get();
        $groups = [];
        foreach (PermissionName::cases() as $permission) {
            $module = $permission->module();
            $groups[$module] ??= ['key' => $module, 'label' => self::MODULE_LABELS[$module] ?? $module, 'permissions' => []];
            $groups[$module]['permissions'][] = ['name' => $permission->value, 'label' => $permission->label()];
        }

        return [
            'roles' => $roles->map(fn (Role $role) => ['id' => $role->id, 'name' => $role->name, 'label' => RoleName::tryFrom($role->name)?->label() ?? $role->name])->values()->all(),
            'permission_groups' => array_values($groups),
            'matrix' => $roles->mapWithKeys(fn (Role $role) => [$role->name => $role->permissions->pluck('name')->sort()->values()->all()])->all(),
            'locked_roles' => [RoleName::SystemAdmin->value],
        ];
    }
}
