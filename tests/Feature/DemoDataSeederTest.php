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

    $engineeringUnit = OrganizationUnit::query()
        ->where('code', 'ENGINEERING')
        ->firstOrFail();

    $platformUnit = OrganizationUnit::query()
        ->where('code', 'ENGINEERING_PLATFORM')
        ->firstOrFail();

    $salesUnit = OrganizationUnit::query()
        ->where('code', 'SALES')
        ->firstOrFail();

    $b2bSalesUnit = OrganizationUnit::query()
        ->where('code', 'SALES_B2B')
        ->firstOrFail();

    $developer = User::query()
        ->where('email', 'developer@dormida.test')
        ->firstOrFail();

    $inactiveEmployee = User::query()
        ->where('email', 'inactive.employee@dormida.test')
        ->firstOrFail();

    $engineeringLead = User::query()
        ->where('email', 'engineering.lead@dormida.test')
        ->firstOrFail();

    $salesLead = User::query()
        ->where('email', 'sales.lead@dormida.test')
        ->firstOrFail();

    expect($designUnit->parent_id)->toBe($productUnit->id)
        ->and($platformUnit->parent_id)->toBe($engineeringUnit->id)
        ->and($b2bSalesUnit->parent_id)->toBe($salesUnit->id)
        ->and($developer->employee_code)->toBe('DW-ENG-001')
        ->and($developer->is_system_admin)->toBeFalse()
        ->and($developer->hasRole(RoleName::Employee->value))->toBeTrue()
        ->and($developer->can(PermissionName::TaskComment->value))->toBeTrue()
        ->and($developer->can(PermissionName::UserAssignRole->value))->toBeFalse()
        ->and($developer->email_verified_at)->not->toBeNull()
        ->and(Hash::check('password', $developer->password))->toBeTrue()
        ->and($inactiveEmployee->is_active)->toBeFalse()
        ->and($engineeringLead->hasRole(RoleName::DepartmentManager->value))->toBeTrue()
        ->and($salesLead->hasRole(RoleName::DepartmentManager->value))->toBeTrue()
        ->and(User::query()->where('email', 'account.executive@dormida.test')->exists())->toBeTrue()
        ->and(User::query()->where('email', 'product.analyst@dormida.test')->exists())->toBeTrue()
        ->and(User::query()->where('email', 'operations.specialist@dormida.test')->exists())->toBeTrue();

    $demoAuditLog = AuditLog::query()->where('metadata->source', 'demo_seeder')->firstOrFail();

    expect($demoAuditLog->subject_id)->toBe($inactiveEmployee->id);
    expect(Task::query()->count())->toBe(18)
        ->and(Task::query()
            ->where('assignee_id', $developer->id)
            ->where('title', 'Tối ưu truy vấn danh sách công việc')
            ->exists())->toBeTrue();

    foreach (['PRODUCT', 'ENGINEERING', 'SALES', 'OPERATIONS'] as $code) {
        $unitId = OrganizationUnit::query()->where('code', $code)->value('id');

        expect(Task::query()->where('organization_unit_id', $unitId)->exists())->toBeTrue();
    }

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

    $demoUnitCodes = [
        'EXEC',
        'PRODUCT',
        'PRODUCT_DESIGN',
        'ENGINEERING',
        'ENGINEERING_PLATFORM',
        'SALES',
        'SALES_B2B',
        'OPERATIONS',
    ];

    $demoUserEmails = [
        'director@dormida.test',
        'product.lead@dormida.test',
        'designer@dormida.test',
        'developer@dormida.test',
        'operations@dormida.test',
        'inactive.employee@dormida.test',
        'engineering.lead@dormida.test',
        'sales.lead@dormida.test',
        'account.executive@dormida.test',
        'product.analyst@dormida.test',
        'operations.specialist@dormida.test',
    ];

    expect(OrganizationUnit::query()->whereIn('code', $demoUnitCodes)->count())->toBe(8)
        ->and(User::query()->whereIn('email', $demoUserEmails)->count())->toBe(11)
        ->and(AuditLog::query()->where('metadata->source', 'demo_seeder')->count())->toBe(1)
        ->and(Task::query()->count())->toBe(18);
});
