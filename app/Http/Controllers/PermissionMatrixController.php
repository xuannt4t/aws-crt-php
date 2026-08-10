<?php

namespace App\Http\Controllers;

use App\Actions\Permission\UpdateRolePermissionsAction;
use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Http\Requests\UpdatePermissionMatrixRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

final class PermissionMatrixController extends Controller
{
    /**
     * Nhãn tiếng Việt cho từng nhóm module trên màn hình ma trận phân quyền.
     */
    private const MODULE_LABELS = [
        'organization' => 'Đơn vị',
        'user' => 'Người dùng',
        'task' => 'Công việc',
        'project' => 'Việc dự án',
        'report' => 'Báo cáo',
        'system' => 'Hệ thống',
    ];

    public function index(Request $request): Response
    {
        abort_unless($request->user()->can(PermissionName::SystemManageSettings->value), 403);

        $roles = Role::query()
            ->where('guard_name', 'web')
            ->with('permissions')
            ->orderBy('id')
            ->get();

        return Inertia::render('PermissionMatrix/Index', [
            'roles' => $roles
                ->map(fn (Role $role): array => [
                    'id' => $role->id,
                    'name' => $role->name,
                    'label' => $this->roleLabel($role->name),
                ])
                ->values()
                ->all(),
            'permissionGroups' => $this->permissionGroups(),
            'matrix' => $roles
                ->mapWithKeys(fn (Role $role): array => [
                    $role->name => $role->permissions->pluck('name')->sort()->values()->all(),
                ])
                ->all(),
            'lockedRoles' => [RoleName::SystemAdmin->value],
        ]);
    }

    public function update(
        UpdatePermissionMatrixRequest $request,
        UpdateRolePermissionsAction $action,
    ): RedirectResponse {
        $action->execute($request->user(), $request->validated()['matrix']);

        return Redirect::route('permission-matrix.index')
            ->with('success', 'Cập nhật ma trận phân quyền thành công.');
    }

    private function roleLabel(string $roleName): string
    {
        $role = RoleName::tryFrom($roleName);

        return $role?->label() ?? $roleName;
    }

    /**
     * @return list<array{key: string, label: string, permissions: list<array{name: string, label: string}>}>
     */
    private function permissionGroups(): array
    {
        $groups = [];

        foreach (PermissionName::cases() as $permission) {
            $module = $permission->module();

            $groups[$module] ??= [
                'key' => $module,
                'label' => self::MODULE_LABELS[$module] ?? $module,
                'permissions' => [],
            ];

            $groups[$module]['permissions'][] = [
                'name' => $permission->value,
                'label' => $permission->label(),
            ];
        }

        return array_values($groups);
    }
}
