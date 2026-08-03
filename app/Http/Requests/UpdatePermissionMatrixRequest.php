<?php

namespace App\Http\Requests;

use App\Enums\PermissionName;
use App\Enums\RoleName;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Spatie\Permission\Models\Role;

final class UpdatePermissionMatrixRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(PermissionName::SystemManageSettings->value);
    }

    public function rules(): array
    {
        return [
            'matrix' => ['required', 'array', $this->keysAreExistingRoles()],
            'matrix.*' => ['array'],
            'matrix.*.*' => ['string', Rule::in($this->permissionValues())],
        ];
    }

    public function messages(): array
    {
        return [
            'matrix.required' => 'Vui lòng chọn ma trận phân quyền.',
            'matrix.array' => 'Ma trận phân quyền không hợp lệ.',
            'matrix.*.array' => 'Danh sách quyền của vai trò không hợp lệ.',
            'matrix.*.*.string' => 'Tên quyền không hợp lệ.',
            'matrix.*.*.in' => 'Quyền không hợp lệ.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            /** @var array<string, list<string>> $matrix */
            $matrix = (array) $this->input('matrix', []);

            $this->rejectSystemAdminChanges($validator, $matrix);
            $this->rejectSelfLockout($validator, $matrix);
        });
    }

    /**
     * Vai trò system_admin luôn giữ toàn bộ permission — từ chối mọi thay đổi
     * lên vai trò này, kể cả khi vai trò chưa tồn tại trong CSDL (không thể
     * thay đổi được).
     */
    private function rejectSystemAdminChanges(Validator $validator, array $matrix): void
    {
        $roleName = RoleName::SystemAdmin->value;

        if (! array_key_exists($roleName, $matrix)) {
            return;
        }

        $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();

        $currentPermissions = $role?->permissions
            ->pluck('name')
            ->sort()
            ->values()
            ->all() ?? [];

        $submittedPermissions = collect($matrix[$roleName] ?? [])
            ->unique()
            ->sort()
            ->values()
            ->all();

        if ($submittedPermissions !== $currentPermissions) {
            $validator->errors()->add(
                'matrix.'.$roleName,
                'Không thể thay đổi quyền của vai trò Quản trị hệ thống.',
            );
        }
    }

    /**
     * Người đang đăng nhập không được tự gỡ system.manage_settings khỏi vai
     * trò mà chính họ đang giữ.
     */
    private function rejectSelfLockout(Validator $validator, array $matrix): void
    {
        $permission = PermissionName::SystemManageSettings->value;
        $user = $this->user();

        foreach ($user->getRoleNames() as $roleName) {
            if (! array_key_exists($roleName, $matrix)) {
                continue;
            }

            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();

            $hadPermission = $role?->permissions->pluck('name')->contains($permission) ?? false;
            $willHavePermission = in_array($permission, $matrix[$roleName] ?? [], true);

            if ($hadPermission && ! $willHavePermission) {
                $validator->errors()->add(
                    'matrix.'.$roleName,
                    'Bạn không thể tự gỡ quyền quản lý cài đặt hệ thống khỏi vai trò của chính mình.',
                );
            }
        }
    }

    private function keysAreExistingRoles(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_array($value)) {
                return;
            }

            foreach (array_keys($value) as $roleName) {
                if (! in_array($roleName, $this->roleValues(), true)) {
                    $fail("Vai trò \"{$roleName}\" không tồn tại.");
                }
            }
        };
    }

    /**
     * @return list<string>
     */
    private function permissionValues(): array
    {
        return array_map(
            static fn (PermissionName $permission): string => $permission->value,
            PermissionName::cases(),
        );
    }

    /**
     * @return list<string>
     */
    private function roleValues(): array
    {
        return array_map(
            static fn (RoleName $role): string => $role->value,
            RoleName::cases(),
        );
    }
}
