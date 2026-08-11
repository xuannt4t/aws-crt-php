<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureApiUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && ! $request->user()->is_active) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Tài khoản đã bị vô hiệu hóa.',
            ], 403);
        }

        return $next($request);
    }
}
