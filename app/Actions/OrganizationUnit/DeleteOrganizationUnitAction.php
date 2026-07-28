<?php

namespace App\Actions\OrganizationUnit;

use App\Models\OrganizationUnit;
use Illuminate\Validation\ValidationException;

class DeleteOrganizationUnitAction
{
    public function execute(OrganizationUnit $unit): void
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

        $unit->delete();
    }
}
