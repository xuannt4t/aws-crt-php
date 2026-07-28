<?php

use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\OrganizationUnitController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TaskCommentController;
use App\Http\Controllers\TaskController;
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
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified', 'active'])->name('dashboard');

Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware(['auth', 'verified', 'active'])->group(function () {
    Route::resource('organization-units', OrganizationUnitController::class)
        ->parameters(['organization-units' => 'organizationUnit'])
        ->except('show');

    Route::resource('users', UserController::class)->except('show');
    Route::patch('users/{user}/disable', [UserController::class, 'disable'])->name('users.disable');
    Route::patch('users/{user}/enable', [UserController::class, 'enable'])->name('users.enable');
    Route::get('audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
    Route::patch('tasks/{task}/dispatch', [TaskController::class, 'dispatch'])->name('tasks.dispatch');
    Route::patch('tasks/{task}/start', [TaskController::class, 'start'])->name('tasks.start');
    Route::patch('tasks/{task}/submit', [TaskController::class, 'submit'])->name('tasks.submit');
    Route::patch('tasks/{task}/recall', [TaskController::class, 'recall'])->name('tasks.recall');
    Route::patch('tasks/{task}/progress', [TaskController::class, 'updateProgress'])->name('tasks.progress.update');
    Route::post('tasks/{task}/comments', [TaskCommentController::class, 'store'])->name('tasks.comments.store');
    Route::resource('tasks', TaskController::class);
});

require __DIR__.'/auth.php';
