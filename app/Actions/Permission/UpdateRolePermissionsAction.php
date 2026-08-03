<?php

namespace App\Actions\Permission;

use App\Enums\AuditAction;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

final class UpdateRolePermissionsAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /**
     * @param  array<string, list<string>>  $matrix  map tên vai trò → danh sách tên permission
     * @return int số vai trò thực sự đổi
     */
    public function execute(User $actor, array $matrix): int
    {
        return DB::transaction(function () use ($actor, $matrix): int {
            $changedCount = 0;

            foreach ($matrix as $roleName => $permissions) {
                $role = Role::findByName($roleName, 'web');

                $beforePermissions = $role->permissions
                    ->pluck('name')
                    ->sort()
                    ->values()
                    ->all();

                $afterPermissions = collect($permissions)
                    ->unique()
                    ->sort()
                    ->values()
                    ->all();

                if ($beforePermissions === $afterPermissions) {
                    continue;
                }

                $role->syncPermissions($afterPermissions);
                $changedCount++;

                $this->auditLogger->record(
                    actor: $actor,
                    action: AuditAction::RolePermissionsUpdated,
                    subject: $role,
                    beforeValues: ['permissions' => $beforePermissions],
                    afterValues: ['permissions' => $afterPermissions],
                );
            }

            app(PermissionRegistrar::class)->forgetCachedPermissions();

            return $changedCount;
        });
    }
}
