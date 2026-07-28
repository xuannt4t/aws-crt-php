<?php

namespace App\Actions\OrganizationUnit;

use App\Models\OrganizationUnit;
use Illuminate\Validation\ValidationException;

class UpdateOrganizationUnitAction
{
    public function execute(OrganizationUnit $unit, array $data): OrganizationUnit
    {
        if (array_key_exists('parent_id', $data) && $data['parent_id'] !== null) {
            $this->guardAgainstCircularReference($unit, (int) $data['parent_id']);
        }

        $unit->update($data);

        return $unit;
    }

    private function guardAgainstCircularReference(OrganizationUnit $unit, int $newParentId): void
    {
        if ($newParentId === $unit->id) {
            throw ValidationException::withMessages([
                'parent_id' => 'Đơn vị không thể là cha của chính nó.',
            ]);
        }

        $ancestor = OrganizationUnit::find($newParentId);

        while ($ancestor !== null) {
            if ($ancestor->id === $unit->id) {
                throw ValidationException::withMessages([
                    'parent_id' => 'Không thể chọn một đơn vị con làm đơn vị cha.',
                ]);
            }

            $ancestor = $ancestor->parent;
        }
    }
}
