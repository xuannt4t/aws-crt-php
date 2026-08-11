<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\User\CreateUserAction;
use App\Actions\User\DeleteUserAction;
use App\Actions\User\SetUserActiveStatusAction;
use App\Actions\User\UpdateUserAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use App\Services\ApiTokenService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', User::class);
        $users = User::query()
            ->with(['organizationUnit:id,name', 'roles:id,name', 'permissions:id,name'])
            ->when($request->string('search')->isNotEmpty(), function (Builder $query) use ($request): void {
                $search = $request->string('search')->toString();
                $query->where(fn (Builder $inner) => $inner->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
            })
            ->when($request->filled('organization_unit_id'), fn (Builder $query) => $query->where('organization_unit_id', $request->integer('organization_unit_id')))
            ->when($request->filled('is_active'), fn (Builder $query) => $query->where('is_active', $request->boolean('is_active')))
            ->orderBy('name')
            ->paginate(min(max($request->integer('per_page', 20), 1), 100));

        return ApiResponse::paginated($users, UserResource::collection($users->getCollection())->resolve($request));
    }

    public function show(Request $request, User $user): JsonResponse
    {
        $this->authorize('view', $user);

        return ApiResponse::success($this->resource($request, $user));
    }

    public function store(StoreUserRequest $request, CreateUserAction $action): JsonResponse
    {
        $user = $action->execute($request->user(), $request->validated());

        return ApiResponse::success($this->resource($request, $user), 'Tạo người dùng thành công.', 201);
    }

    public function update(UpdateUserRequest $request, User $user, UpdateUserAction $action): JsonResponse
    {
        $user = $action->execute($request->user(), $user, $request->validated());

        return ApiResponse::success($this->resource($request, $user), 'Cập nhật người dùng thành công.');
    }

    public function disable(Request $request, User $user, SetUserActiveStatusAction $action, ApiTokenService $tokens): JsonResponse
    {
        $this->authorize('disable', $user);
        $user = $action->execute($request->user(), $user, false);
        $tokens->revokeAll($user);

        return ApiResponse::success($this->resource($request, $user), 'Đã vô hiệu hóa người dùng.');
    }

    public function enable(Request $request, User $user, SetUserActiveStatusAction $action): JsonResponse
    {
        $this->authorize('disable', $user);
        $user = $action->execute($request->user(), $user, true);

        return ApiResponse::success($this->resource($request, $user), 'Đã kích hoạt người dùng.');
    }

    public function destroy(Request $request, User $user, DeleteUserAction $action, ApiTokenService $tokens): JsonResponse
    {
        $this->authorize('delete', $user);
        $tokens->revokeAll($user);
        $action->execute($request->user(), $user);

        return ApiResponse::success(null, 'Xóa người dùng thành công.');
    }

    private function resource(Request $request, User $user): array
    {
        return UserResource::make($user->load(['organizationUnit:id,name', 'roles:id,name', 'permissions:id,name']))->resolve($request);
    }
}
