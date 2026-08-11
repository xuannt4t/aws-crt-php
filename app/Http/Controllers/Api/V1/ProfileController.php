<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\User\UpdateProfileAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdatePasswordRequest;
use App\Http\Requests\ProfileUpdateRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Http\Responses\ApiResponse;
use App\Services\ApiTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

final class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return ApiResponse::success($this->resource($request));
    }

    public function update(ProfileUpdateRequest $request, UpdateProfileAction $action): JsonResponse
    {
        $action->execute($request->user(), $request->validated());

        return ApiResponse::success($this->resource($request), 'Đã cập nhật hồ sơ.');
    }

    public function updatePassword(UpdatePasswordRequest $request, ApiTokenService $tokens): JsonResponse
    {
        $request->user()->forceFill([
            'password' => Hash::make($request->string('password')->toString()),
        ])->save();

        if ($request->boolean('revoke_other_sessions')) {
            $tokens->revokeOthers($request);
        }

        return ApiResponse::success(null, 'Đã cập nhật mật khẩu.');
    }

    private function resource(Request $request): array
    {
        $user = $request->user()->fresh()->load(['organizationUnit:id,name', 'roles:id,name', 'permissions:id,name']);

        return UserResource::make($user)->resolve($request);
    }
}
