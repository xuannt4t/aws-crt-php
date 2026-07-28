<?php

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Models\AuditLog;
use App\Models\OrganizationUnit;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;

test('demo seeder creates organization units and users for administration review', function () {
    Artisan::call('db:seed', ['--class' => DemoDataSeeder::class]);

    $productUnit = OrganizationUnit::query()
        ->where('code', 'PRODUCT')
        ->firstOrFail();

    $designUnit = OrganizationUnit::query()
        ->where('code', 'PRODUCT_DESIGN')
        ->firstOrFail();

    $developer = User::query()
        ->where('email', 'developer@dormida.test')
        ->firstOrFail();

    $inactiveEmployee = User::query()
        ->where('email', 'inactive.employee@dormida.test')
        ->firstOrFail();

    expect($designUnit->parent_id)->toBe($productUnit->id)
        ->and($developer->employee_code)->toBe('DW-ENG-001')
        ->and($developer->is_system_admin)->toBeFalse()
        ->and($developer->hasRole(RoleName::Employee->value))->toBeTrue()
        ->and($developer->can(PermissionName::TaskComment->value))->toBeTrue()
        ->and($developer->can(PermissionName::UserAssignRole->value))->toBeFalse()
        ->and($developer->email_verified_at)->not->toBeNull()
        ->and(Hash::check('password', $developer->password))->toBeTrue()
        ->and($inactiveEmployee->is_active)->toBeFalse();

    $demoAuditLog = AuditLog::query()->where('metadata->source', 'demo_seeder')->firstOrFail();

    expect($demoAuditLog->subject_id)->toBe($inactiveEmployee->id);
    expect(Task::query()->count())->toBe(6)
        ->and(Task::query()
            ->where('assignee_id', $developer->id)
            ->where('title', 'Tối ưu truy vấn danh sách công việc')
            ->exists())->toBeTrue();

    $response = $this->post('/login', [
        'email' => $developer->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticatedAs($developer);
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('demo seeder can run repeatedly without creating duplicate data', function () {
    Artisan::call('db:seed', ['--class' => DemoDataSeeder::class]);
    Artisan::call('db:seed', ['--class' => DemoDataSeeder::class]);

    expect(OrganizationUnit::query()->whereIn('code', [
        'EXEC',
        'PRODUCT',
        'ENGINEERING',
        'OPERATIONS',
        'PRODUCT_DESIGN',
    ])->count())->toBe(5)
        ->and(User::query()->whereIn('email', [
            'director@dormida.test',
            'product.lead@dormida.test',
            'designer@dormida.test',
            'developer@dormida.test',
            'operations@dormida.test',
            'inactive.employee@dormida.test',
        ])->count())->toBe(6);

    expect(AuditLog::query()->where('metadata->source', 'demo_seeder')->count())->toBe(1);
    expect(Task::query()->count())->toBe(6);
});
