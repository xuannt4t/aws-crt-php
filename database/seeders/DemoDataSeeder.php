<?php

namespace Database\Seeders;

use App\Enums\AuditAction;
use App\Enums\RoleName;
use App\Models\AuditLog;
use App\Models\OrganizationUnit;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

final class DemoDataSeeder extends Seeder
{
    private const string DEFAULT_PASSWORD = 'password';

    /**
     * Seed local demo data for manually reviewing the administration screens.
     */
    public function run(): void
    {
        $this->call(DatabaseSeeder::class);

        $rootUnit = OrganizationUnit::query()
            ->where('code', 'ROOT')
            ->firstOrFail();

        $executiveUnit = $this->upsertOrganizationUnit(
            code: 'EXEC',
            name: 'Ban Điều hành',
            parentId: $rootUnit->id,
        );

        $productUnit = $this->upsertOrganizationUnit(
            code: 'PRODUCT',
            name: 'Phòng Sản phẩm',
            parentId: $rootUnit->id,
        );

        $engineeringUnit = $this->upsertOrganizationUnit(
            code: 'ENGINEERING',
            name: 'Phòng Kỹ thuật',
            parentId: $rootUnit->id,
        );

        $operationsUnit = $this->upsertOrganizationUnit(
            code: 'OPERATIONS',
            name: 'Phòng Vận hành',
            parentId: $rootUnit->id,
        );

        $designUnit = $this->upsertOrganizationUnit(
            code: 'PRODUCT_DESIGN',
            name: 'Nhóm Thiết kế sản phẩm',
            parentId: $productUnit->id,
        );

        $this->upsertUser(
            email: 'director@dormida.test',
            organizationUnitId: $executiveUnit->id,
            name: 'Lê Minh Anh',
            employeeCode: 'DW-DIR-001',
            phone: '0901000001',
            jobTitle: 'Giám đốc điều hành',
            role: RoleName::Director,
        );

        $this->upsertUser(
            email: 'product.lead@dormida.test',
            organizationUnitId: $productUnit->id,
            name: 'Nguyễn Hoàng Nam',
            employeeCode: 'DW-PRD-001',
            phone: '0901000002',
            jobTitle: 'Trưởng phòng Sản phẩm',
            role: RoleName::DepartmentManager,
        );

        $this->upsertUser(
            email: 'designer@dormida.test',
            organizationUnitId: $designUnit->id,
            name: 'Trần Ngọc Mai',
            employeeCode: 'DW-DSN-001',
            phone: '0901000003',
            jobTitle: 'Product Designer',
            role: RoleName::Employee,
        );

        $this->upsertUser(
            email: 'developer@dormida.test',
            organizationUnitId: $engineeringUnit->id,
            name: 'Phạm Quốc Huy',
            employeeCode: 'DW-ENG-001',
            phone: '0901000004',
            jobTitle: 'Backend Developer',
            role: RoleName::Employee,
        );

        $this->upsertUser(
            email: 'operations@dormida.test',
            organizationUnitId: $operationsUnit->id,
            name: 'Võ Thanh Hà',
            employeeCode: 'DW-OPS-001',
            phone: '0901000005',
            jobTitle: 'Trưởng phòng Vận hành',
            role: RoleName::DepartmentManager,
        );

        $inactiveEmployee = $this->upsertUser(
            email: 'inactive.employee@dormida.test',
            organizationUnitId: $operationsUnit->id,
            name: 'Đặng Gia Bảo',
            employeeCode: 'DW-OPS-002',
            phone: '0901000006',
            jobTitle: 'Chuyên viên Vận hành',
            isActive: false,
            role: RoleName::Employee,
        );

        $this->seedDemoAuditLog($inactiveEmployee);
    }

    private function upsertOrganizationUnit(
        string $code,
        string $name,
        int $parentId,
    ): OrganizationUnit {
        $unit = OrganizationUnit::withTrashed()->updateOrCreate(
            ['code' => $code],
            [
                'name' => $name,
                'parent_id' => $parentId,
                'is_active' => true,
            ],
        );

        if ($unit->trashed()) {
            $unit->restore();
        }

        return $unit;
    }

    private function upsertUser(
        string $email,
        int $organizationUnitId,
        string $name,
        string $employeeCode,
        string $phone,
        string $jobTitle,
        bool $isActive = true,
        RoleName $role = RoleName::Employee,
    ): User {
        $user = User::withTrashed()->updateOrCreate(
            ['email' => $email],
            [
                'organization_unit_id' => $organizationUnitId,
                'name' => $name,
                'password' => Hash::make(self::DEFAULT_PASSWORD),
                'is_system_admin' => false,
                'is_active' => $isActive,
                'employee_code' => $employeeCode,
                'phone' => $phone,
                'job_title' => $jobTitle,
            ],
        );

        if ($user->trashed()) {
            $user->restore();
        }

        $user->forceFill(['email_verified_at' => now()])->save();
        $user->syncRoles([$role->value]);

        return $user;
    }

    private function seedDemoAuditLog(User $subject): void
    {
        if (AuditLog::query()->where('metadata->source', 'demo_seeder')->exists()) {
            return;
        }

        $actor = User::query()
            ->where('email', config('dormida.admin_email'))
            ->firstOrFail();

        AuditLog::create([
            'actor_id' => $actor->id,
            'action' => AuditAction::UserDisabled->value,
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->id,
            'before_values' => ['is_active' => true],
            'after_values' => ['is_active' => false],
            'metadata' => [
                'source' => 'demo_seeder',
                'note' => 'Bản ghi mẫu để kiểm tra giao diện quản trị.',
            ],
        ]);
    }
}
