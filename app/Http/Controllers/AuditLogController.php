<?php

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Http\Requests\IndexAuditLogRequest;
use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Builder;
use Inertia\Inertia;
use Inertia\Response;

final class AuditLogController extends Controller
{
    public function index(IndexAuditLogRequest $request): Response
    {
        $filters = $request->validated();

        $auditLogs = AuditLog::query()
            ->with('actor:id,name,email')
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $query->whereHas('actor', function (Builder $actorQuery) use ($search): void {
                    $actorQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($filters['action'] ?? null, fn (Builder $query, string $action) => $query->where('action', $action))
            ->when($filters['date_from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('created_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn (Builder $query, string $date) => $query->whereDate('created_at', '<=', $date))
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('AuditLogs/Index', [
            'auditLogs' => $auditLogs,
            'filters' => $filters,
            'actions' => array_map(
                static fn (AuditAction $action): string => $action->value,
                AuditAction::cases(),
            ),
        ]);
    }
}
