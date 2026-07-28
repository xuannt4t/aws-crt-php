<?php

namespace App\Actions\OrganizationUnit;

use App\Enums\AuditAction;
use App\Models\OrganizationUnit;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeleteOrganizationUnitAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function execute(User $actor, OrganizationUnit $unit): void
    {
        if ($unit->children()->exists()) {
            throw ValidationException::withMessages([
                'organization_unit' => 'Không thể xoá đơn vị còn đơn vị con. Vui lòng chuyển đơn vị con trước.',
            ]);
        }

        if ($unit->users()->exists()) {
            throw ValidationException::withMessages([
                'organization_unit' => 'Không thể xoá đơn vị còn nhân sự. Vui lòng chuyển nhân sự trước.',
            ]);
        }

        DB::transaction(function () use ($actor, $unit): void {
            $beforeValues = Arr::only($unit->toArray(), [
                'parent_id',
                'name',
                'code',
                'is_active',
            ]);

            $unit->delete();

            $this->auditLogger->record(
                actor: $actor,
                action: AuditAction::OrganizationUnitDeleted,
                subject: $unit,
                beforeValues: $beforeValues,
                afterValues: ['deleted_at' => $unit->deleted_at?->toISOString()],
            );
        });
    }
}
