<?php

use App\Http\Controllers\Api\V1\AuditLogController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\MetaController;
use App\Http\Controllers\Api\V1\OrganizationUnitController;
use App\Http\Controllers\Api\V1\PermissionMatrixController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\ProjectController;
use App\Http\Controllers\Api\V1\ProjectMemberController;
use App\Http\Controllers\Api\V1\TaskController;
use App\Http\Controllers\Api\V1\TaskRecurrenceController;
use App\Http\Controllers\Api\V1\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::middleware('throttle:10,1')->group(function () {
        Route::post('auth/login', [AuthController::class, 'login']);
        Route::post('auth/refresh', [AuthController::class, 'refresh']);
    });

    Route::middleware(['auth:sanctum', 'api.active'])->group(function () {
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::post('auth/logout-all', [AuthController::class, 'logoutAll']);

        Route::get('profile', [ProfileController::class, 'show']);
        Route::patch('profile', [ProfileController::class, 'update']);
        Route::put('profile/password', [ProfileController::class, 'updatePassword']);

        Route::get('meta', [MetaController::class, 'index']);
        Route::get('meta/users', [MetaController::class, 'users']);
        Route::get('meta/projects', [MetaController::class, 'projects']);
        Route::get('meta/organization-units', [MetaController::class, 'organizationUnits']);

        Route::get('task-departments', [TaskController::class, 'departments']);
        Route::get('task-departments/{organizationUnit}', [TaskController::class, 'departmentShow']);

        Route::patch('tasks/{task}/dispatch', [TaskController::class, 'dispatch']);
        Route::patch('tasks/{task}/start', [TaskController::class, 'start']);
        Route::patch('tasks/{task}/submit', [TaskController::class, 'submit']);
        Route::patch('tasks/{task}/recall', [TaskController::class, 'recall']);
        Route::patch('tasks/{task}/approve', [TaskController::class, 'approve']);
        Route::patch('tasks/{task}/reject', [TaskController::class, 'reject']);
        Route::patch('tasks/{task}/progress', [TaskController::class, 'updateProgress']);
        Route::patch('tasks/{task}/quantity', [TaskController::class, 'updateQuantity']);
        Route::post('tasks/{task}/comments', [TaskController::class, 'storeComment']);
        Route::post('tasks/{task}/attachments', [TaskController::class, 'storeAttachments']);
        Route::get('tasks/{task}/attachments/{attachment}', [TaskController::class, 'downloadAttachment'])->scopeBindings();
        Route::delete('tasks/{task}/attachments/{attachment}', [TaskController::class, 'destroyAttachment'])->scopeBindings();
        Route::apiResource('tasks', TaskController::class);

        Route::patch('task-recurrences/{taskRecurrence}/toggle', [TaskRecurrenceController::class, 'toggle']);
        Route::apiResource('task-recurrences', TaskRecurrenceController::class)
            ->parameters(['task-recurrences' => 'taskRecurrence']);

        Route::post('projects/{project}/members', [ProjectMemberController::class, 'store']);
        Route::patch('projects/{project}/members/{member}', [ProjectMemberController::class, 'update'])->scopeBindings();
        Route::delete('projects/{project}/members/{member}', [ProjectMemberController::class, 'destroy'])->scopeBindings();
        Route::patch('projects/{project}/close', [ProjectController::class, 'close']);
        Route::apiResource('projects', ProjectController::class);

        Route::apiResource('organization-units', OrganizationUnitController::class)
            ->parameters(['organization-units' => 'organizationUnit']);
        Route::patch('users/{user}/disable', [UserController::class, 'disable']);
        Route::patch('users/{user}/enable', [UserController::class, 'enable']);
        Route::apiResource('users', UserController::class);
        Route::get('permission-matrix', [PermissionMatrixController::class, 'index']);
        Route::put('permission-matrix', [PermissionMatrixController::class, 'update']);
        Route::get('audit-logs', [AuditLogController::class, 'index']);
    });
});
