<?php

namespace App\Actions\User;

use App\Enums\AuditAction;
use App\Enums\RoleName;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class UpdateUserAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $actor, User $user, array $data): User
    {
        /** @var list<string>|null $roles */
        $roles = Arr::pull($data, 'roles');

        if (
            $roles !== null
            && $actor->is($user)
            && $user->hasRole(RoleName::SystemAdmin->value)
            && ! in_array(RoleName::SystemAdmin->value, $roles, true)
        ) {
            throw ValidationException::withMessages([
                'roles' => 'Bạn không thể tự gỡ vai trò quản trị hệ thống của chính mình.',
            ]);
        }

        return DB::transaction(function () use ($actor, $user, $data, $roles): User {
            $auditedFields = [
                'name',
                'email',
                'organization_unit_id',
                'employee_code',
                'phone',
                'job_title',
            ];
            $beforeValues = Arr::only($user->toArray(), $auditedFields);
            $beforeRoles = $user->getRoleNames()->sort()->values()->all();

            $user->update($data);

            if ($roles !== null) {
                $user->syncRoles($roles);
            }

            $user->refresh();
            $afterValues = Arr::only($user->toArray(), $auditedFields);

            if ($beforeValues !== $afterValues) {
                $this->auditLogger->record(
                    actor: $actor,
                    action: AuditAction::UserUpdated,
                    subject: $user,
                    beforeValues: $beforeValues,
                    afterValues: $afterValues,
                );
            }

            $afterRoles = $user->getRoleNames()->sort()->values()->all();

            if ($beforeRoles !== $afterRoles) {
                $this->auditLogger->record(
                    actor: $actor,
                    action: AuditAction::UserRolesUpdated,
                    subject: $user,
                    beforeValues: ['roles' => $beforeRoles],
                    afterValues: ['roles' => $afterRoles],
                );
            }

            return $user;
        });
    }
}
