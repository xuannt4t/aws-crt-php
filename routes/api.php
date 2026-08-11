<?php

use App\Http\Responses\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::middleware(['auth:sanctum', 'api.active'])->group(function () {
        Route::get('auth/me', function (Request $request) {
            return ApiResponse::success($request->user());
        });
    });
});
