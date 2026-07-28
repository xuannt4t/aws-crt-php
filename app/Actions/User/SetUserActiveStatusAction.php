<?php

namespace App\Actions\User;

use App\Enums\AuditAction;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

final class SetUserActiveStatusAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function execute(User $actor, User $user, bool $isActive): User
    {
        return DB::transaction(function () use ($actor, $user, $isActive): User {
            $before = ['is_active' => $user->is_active];

            $user->forceFill([
                'is_active' => $isActive,
                ...($isActive ? [] : ['remember_token' => null]),
            ])->save();

            $this->auditLogger->record(
                actor: $actor,
                action: $isActive ? AuditAction::UserEnabled : AuditAction::UserDisabled,
                subject: $user,
                beforeValues: $before,
                afterValues: ['is_active' => $user->is_active],
            );

            return $user;
        });
    }
}
