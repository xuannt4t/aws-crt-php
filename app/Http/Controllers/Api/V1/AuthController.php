<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Http\Requests\Api\V1\RefreshTokenRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use App\Services\ApiTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

final class AuthController extends Controller
{
    public function login(LoginRequest $request, ApiTokenService $tokens): JsonResponse
    {
        $user = User::query()->where('email', mb_strtolower($request->string('email')->toString()))->first();

        if (! $user || ! Hash::check($request->string('password')->toString(), $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Thông tin đăng nhập không chính xác.'],
            ]);
        }

        if (! $user->is_active) {
            return ApiResponse::error('Tài khoản đã bị vô hiệu hóa.', 403);
        }

        return ApiResponse::success([
            ...$tokens->issue($user, $request->string('device_name')->toString()),
            'user' => UserResource::make($user->load(['organizationUnit:id,name', 'roles:id,name', 'permissions:id,name']))->resolve($request),
        ], 'Đăng nhập thành công.');
    }

    public function refresh(RefreshTokenRequest $request, ApiTokenService $tokens): JsonResponse
    {
        return ApiResponse::success(
            $tokens->rotate($request->string('refresh_token')->toString()),
            'Làm mới phiên đăng nhập thành công.',
        );
    }

    public function logout(Request $request, ApiTokenService $tokens): JsonResponse
    {
        $tokens->revokeCurrent($request);

        return ApiResponse::success(null, 'Đăng xuất thành công.');
    }

    public function logoutAll(Request $request, ApiTokenService $tokens): JsonResponse
    {
        $tokens->revokeAll($request->user());

        return ApiResponse::success(null, 'Đã đăng xuất khỏi tất cả thiết bị.');
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load(['organizationUnit:id,name', 'roles:id,name', 'permissions:id,name']);

        return ApiResponse::success(UserResource::make($user)->resolve($request));
    }
}
