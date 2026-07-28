<?php

namespace App\Actions\User;

use App\Enums\AuditAction;
use App\Enums\RoleName;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

final class CreateUserAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $actor, array $data): User
    {
        /** @var list<string> $roles */
        $roles = Arr::pull($data, 'roles', [RoleName::Employee->value]);
        $data['password'] = Hash::make($data['password']);

        return DB::transaction(function () use ($actor, $data, $roles): User {
            $user = User::create($data);
            $user->syncRoles($roles);

            $this->auditLogger->record(
                actor: $actor,
                action: AuditAction::UserCreated,
                subject: $user,
                afterValues: [
                    ...Arr::only($user->toArray(), [
                        'name',
                        'email',
                        'organization_unit_id',
                        'employee_code',
                        'phone',
                        'job_title',
                        'is_active',
                    ]),
                    'roles' => $user->getRoleNames()->sort()->values()->all(),
                ],
            );

            return $user;
        });
    }
}
