<?php

use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OrganizationUnitController;
use App\Http\Controllers\PermissionMatrixController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectMemberController;
use App\Http\Controllers\TaskAttachmentController;
use App\Http\Controllers\TaskCommentController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TaskRecurrenceController;
use App\Http\Controllers\UserController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', function () {
    return redirect()->route('tasks.index');
})->middleware(['auth', 'verified', 'active'])->name('dashboard');

Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware(['auth', 'verified', 'active'])->group(function () {
    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::patch('notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::patch('notifications/{notification}/read', [NotificationController::class, 'read'])->name('notifications.read');

    Route::resource('organization-units', OrganizationUnitController::class)
        ->parameters(['organization-units' => 'organizationUnit'])
        ->except('show');

    Route::resource('users', UserController::class)->except('show');
    Route::patch('users/{user}/disable', [UserController::class, 'disable'])->name('users.disable');
    Route::patch('users/{user}/enable', [UserController::class, 'enable'])->name('users.enable');
    Route::get('audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
    Route::get('permission-matrix', [PermissionMatrixController::class, 'index'])->name('permission-matrix.index');
    Route::put('permission-matrix', [PermissionMatrixController::class, 'update'])->name('permission-matrix.update');

    // Ba màn công việc (spec §5.1) — đăng ký trước Route::resource('tasks', ...)
    // để không bị `tasks/{task}` nuốt mất.
    Route::get('tasks', [TaskController::class, 'index'])->name('tasks.index');
    Route::get('tasks/projects', [TaskController::class, 'projects'])->name('tasks.projects');
    Route::get('tasks/departments', [TaskController::class, 'departments'])->name('tasks.departments');

    Route::patch('tasks/{task}/dispatch', [TaskController::class, 'dispatch'])->name('tasks.dispatch');
    Route::patch('tasks/{task}/start', [TaskController::class, 'start'])->name('tasks.start');
    Route::patch('tasks/{task}/submit', [TaskController::class, 'submit'])->name('tasks.submit');
    Route::patch('tasks/{task}/recall', [TaskController::class, 'recall'])->name('tasks.recall');
    Route::patch('tasks/{task}/progress', [TaskController::class, 'updateProgress'])->name('tasks.progress.update');
    Route::patch('tasks/{task}/quantity', [TaskController::class, 'updateQuantity'])->name('tasks.quantity.update');
    Route::post('tasks/{task}/comments', [TaskCommentController::class, 'store'])->name('tasks.comments.store');
    Route::post('tasks/{task}/attachments', [TaskAttachmentController::class, 'store'])
        ->name('tasks.attachments.store');
    Route::get('tasks/{task}/attachments/{attachment}/download', [TaskAttachmentController::class, 'download'])
        ->scopeBindings()
        ->name('tasks.attachments.download');
    Route::delete('tasks/{task}/attachments/{attachment}', [TaskAttachmentController::class, 'destroy'])
        ->scopeBindings()
        ->name('tasks.attachments.destroy');
    Route::resource('tasks', TaskController::class)->except('index');

    Route::patch('task-recurrences/{taskRecurrence}/toggle', [TaskRecurrenceController::class, 'toggle'])
        ->name('task-recurrences.toggle');
    Route::resource('task-recurrences', TaskRecurrenceController::class)
        ->parameters(['task-recurrences' => 'taskRecurrence']);

    Route::post('projects/{project}/members', [ProjectMemberController::class, 'store'])
        ->name('projects.members.store');
    Route::patch('projects/{project}/members/{member}', [ProjectMemberController::class, 'update'])
        ->scopeBindings()
        ->name('projects.members.update');
    Route::delete('projects/{project}/members/{member}', [ProjectMemberController::class, 'destroy'])
        ->scopeBindings()
        ->name('projects.members.destroy');

    Route::patch('projects/{project}/close', [ProjectController::class, 'close'])->name('projects.close');

    Route::resource('projects', ProjectController::class);
});

require __DIR__.'/auth.php';
