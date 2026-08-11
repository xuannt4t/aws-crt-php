<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\IndexAuditLogRequest;
use App\Http\Resources\Api\V1\AuditLogResource;
use App\Http\Responses\ApiResponse;
use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

final class AuditLogController extends Controller
{
    public function index(IndexAuditLogRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $logs = AuditLog::query()
            ->with('actor:id,name,email,avatar_path')
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->whereHas('actor', fn (Builder $actor) => $actor->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")))
            ->when($filters['action'] ?? null, fn (Builder $query, string $action) => $query->where('action', $action))
            ->when($filters['date_from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('created_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn (Builder $query, string $date) => $query->whereDate('created_at', '<=', $date))
            ->latest('id')
            ->paginate(min(max($request->integer('per_page', 25), 1), 100));

        return ApiResponse::paginated($logs, AuditLogResource::collection($logs->getCollection())->resolve($request));
    }
}
