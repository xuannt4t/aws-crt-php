<?php

namespace App\Actions\User;

use App\Enums\AuditAction;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

final class DeleteUserAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function execute(User $actor, User $user): void
    {
        DB::transaction(function () use ($actor, $user): void {
            $beforeValues = Arr::only($user->toArray(), [
                'name',
                'email',
                'organization_unit_id',
                'employee_code',
                'phone',
                'job_title',
                'is_active',
            ]);

            $user->delete();

            $this->auditLogger->record(
                actor: $actor,
                action: AuditAction::UserDeleted,
                subject: $user,
                beforeValues: $beforeValues,
                afterValues: ['deleted_at' => $user->deleted_at?->toISOString()],
            );
        });
    }
}
