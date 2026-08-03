<?php

namespace App\Models;

use Database\Factories\OrganizationUnitFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrganizationUnit extends Model
{
    /** @use HasFactory<OrganizationUnitFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'parent_id',
        'name',
        'code',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(OrganizationUnit::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(OrganizationUnit::class, 'parent_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'organization_unit_id');
    }

    /**
     * Return the given unit's id together with every descendant id at any
     * depth. Fetches all (id, parent_id) pairs in a single query and walks
     * the tree in PHP, guarding against cyclic parent/child data.
     *
     * @return list<int>
     */
    public static function descendantIdsOf(int $unitId): array
    {
        $parentById = static::query()->pluck('parent_id', 'id');

        if (! $parentById->has($unitId)) {
            return [];
        }

        $childrenByParent = [];

        foreach ($parentById as $id => $parentId) {
            $childrenByParent[$parentId ?? 0][] = (int) $id;
        }

        $visited = [$unitId => true];
        $queue = [$unitId];

        while ($queue !== []) {
            $currentId = array_shift($queue);

            foreach ($childrenByParent[$currentId] ?? [] as $childId) {
                if (isset($visited[$childId])) {
                    continue;
                }

                $visited[$childId] = true;
                $queue[] = $childId;
            }
        }

        return array_keys($visited);
    }
}
