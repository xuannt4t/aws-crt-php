<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final readonly class AuditLogger
{
    public function __construct(private Request $request) {}

    /**
     * @param  array<string, mixed>  $beforeValues
     * @param  array<string, mixed>  $afterValues
     * @param  array<string, mixed>  $metadata
     */
    public function record(
        User $actor,
        AuditAction $action,
        Model $subject,
        array $beforeValues = [],
        array $afterValues = [],
        array $metadata = [],
    ): AuditLog {
        return AuditLog::create([
            'actor_id' => $actor->getKey(),
            'action' => $action->value,
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'before_values' => $beforeValues ?: null,
            'after_values' => $afterValues ?: null,
            'ip_address' => $this->request->ip(),
            'user_agent' => Str::limit((string) $this->request->userAgent(), 1000, ''),
            'metadata' => [
                'route' => $this->request->route()?->getName(),
                ...$metadata,
            ],
        ]);
    }
}
