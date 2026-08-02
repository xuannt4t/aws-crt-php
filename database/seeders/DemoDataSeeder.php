<?php

namespace Database\Seeders;

use App\Enums\AuditAction;
use App\Enums\ProjectMemberRole;
use App\Enums\ProjectStatus;
use App\Enums\RoleName;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\AuditLog;
use App\Models\OrganizationUnit;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Carbon\CarbonInterface;
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

        $platformUnit = $this->upsertOrganizationUnit(
            code: 'ENGINEERING_PLATFORM',
            name: 'Nhóm Nền tảng',
            parentId: $engineeringUnit->id,
        );

        $salesUnit = $this->upsertOrganizationUnit(
            code: 'SALES',
            name: 'Phòng Kinh doanh',
            parentId: $rootUnit->id,
        );

        $b2bSalesUnit = $this->upsertOrganizationUnit(
            code: 'SALES_B2B',
            name: 'Nhóm Kinh doanh B2B',
            parentId: $salesUnit->id,
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
            organizationUnitId: $platformUnit->id,
            name: 'Phạm Quốc Huy',
            employeeCode: 'DW-ENG-001',
            phone: '0901000004',
            jobTitle: 'Backend Developer',
            role: RoleName::Employee,
        );

        $this->upsertUser(
            email: 'engineering.lead@dormida.test',
            organizationUnitId: $engineeringUnit->id,
            name: 'Trần Đức Long',
            employeeCode: 'DW-ENG-002',
            phone: '0901000007',
            jobTitle: 'Trưởng phòng Kỹ thuật',
            role: RoleName::DepartmentManager,
        );

        $this->upsertUser(
            email: 'sales.lead@dormida.test',
            organizationUnitId: $salesUnit->id,
            name: 'Nguyễn Thu Trang',
            employeeCode: 'DW-SAL-001',
            phone: '0901000008',
            jobTitle: 'Trưởng phòng Kinh doanh',
            role: RoleName::DepartmentManager,
        );

        $this->upsertUser(
            email: 'account.executive@dormida.test',
            organizationUnitId: $b2bSalesUnit->id,
            name: 'Đỗ Minh Quân',
            employeeCode: 'DW-SAL-002',
            phone: '0901000009',
            jobTitle: 'Chuyên viên Kinh doanh B2B',
            role: RoleName::Employee,
        );

        $this->upsertUser(
            email: 'product.analyst@dormida.test',
            organizationUnitId: $productUnit->id,
            name: 'Phan Khánh Linh',
            employeeCode: 'DW-PRD-002',
            phone: '0901000010',
            jobTitle: 'Chuyên viên Phân tích sản phẩm',
            role: RoleName::Employee,
        );

        $this->upsertUser(
            email: 'operations.specialist@dormida.test',
            organizationUnitId: $operationsUnit->id,
            name: 'Bùi Hải Yến',
            employeeCode: 'DW-OPS-003',
            phone: '0901000011',
            jobTitle: 'Chuyên viên Vận hành',
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
        $this->seedDemoTasks(
            productUnit: $productUnit,
            engineeringUnit: $engineeringUnit,
            salesUnit: $salesUnit,
            operationsUnit: $operationsUnit,
        );
        $this->seedDemoProjects(
            productUnit: $productUnit,
            engineeringUnit: $engineeringUnit,
            salesUnit: $salesUnit,
            operationsUnit: $operationsUnit,
        );
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

    private function seedDemoTasks(
        OrganizationUnit $productUnit,
        OrganizationUnit $engineeringUnit,
        OrganizationUnit $salesUnit,
        OrganizationUnit $operationsUnit,
    ): void {
        $admin = User::query()->where('email', config('dormida.admin_email'))->firstOrFail();
        $productLead = User::query()->where('email', 'product.lead@dormida.test')->firstOrFail();
        $designer = User::query()->where('email', 'designer@dormida.test')->firstOrFail();
        $developer = User::query()->where('email', 'developer@dormida.test')->firstOrFail();
        $operations = User::query()->where('email', 'operations@dormida.test')->firstOrFail();
        $engineeringLead = User::query()->where('email', 'engineering.lead@dormida.test')->firstOrFail();
        $salesLead = User::query()->where('email', 'sales.lead@dormida.test')->firstOrFail();
        $accountExecutive = User::query()->where('email', 'account.executive@dormida.test')->firstOrFail();
        $productAnalyst = User::query()->where('email', 'product.analyst@dormida.test')->firstOrFail();
        $operationsSpecialist = User::query()->where('email', 'operations.specialist@dormida.test')->firstOrFail();

        $this->upsertTask(
            title: 'Hoàn thiện đặc tả luồng onboarding',
            unit: $productUnit,
            creator: $productLead,
            assignee: $designer,
            status: TaskStatus::InProgress,
            priority: TaskPriority::High,
            dueAt: now()->addDays(3),
            progress: 55,
        );

        $this->upsertTask(
            title: 'Tối ưu truy vấn danh sách công việc',
            unit: $engineeringUnit,
            creator: $admin,
            assignee: $developer,
            status: TaskStatus::Todo,
            priority: TaskPriority::Medium,
            dueAt: now()->addWeek(),
        );

        $this->upsertTask(
            title: 'Rà soát checklist phát hành',
            unit: $engineeringUnit,
            creator: $admin,
            assignee: $developer,
            status: TaskStatus::WaitingReview,
            priority: TaskPriority::Urgent,
            dueAt: now()->addDay(),
            progress: 90,
        );

        $this->upsertTask(
            title: 'Tổng hợp số liệu vận hành tuần',
            unit: $operationsUnit,
            creator: $operations,
            assignee: $operations,
            status: TaskStatus::Completed,
            priority: TaskPriority::Low,
            dueAt: now()->subDay(),
            progress: 100,
            completedAt: now()->subHours(3),
        );

        $this->upsertTask(
            title: 'Cập nhật quy trình xử lý yêu cầu nội bộ',
            unit: $operationsUnit,
            creator: $operations,
            assignee: $operations,
            status: TaskStatus::Todo,
            priority: TaskPriority::High,
            dueAt: now()->subDays(2),
        );

        $this->upsertTask(
            title: 'Chuẩn bị kế hoạch cải tiến quý tới',
            unit: $productUnit,
            creator: $productLead,
            assignee: null,
            status: TaskStatus::Draft,
            priority: TaskPriority::Medium,
            dueAt: null,
        );

        $this->upsertTask(
            title: 'Phân tích phản hồi khách hàng quý III',
            unit: $productUnit,
            creator: $productLead,
            assignee: $productAnalyst,
            status: TaskStatus::InProgress,
            priority: TaskPriority::High,
            dueAt: now()->addDays(5),
            progress: 40,
        );

        $this->upsertTask(
            title: 'Xây dựng lộ trình sản phẩm quý IV',
            unit: $productUnit,
            creator: $productLead,
            assignee: $productAnalyst,
            status: TaskStatus::WaitingApproval,
            priority: TaskPriority::Urgent,
            dueAt: now()->addDays(2),
            progress: 85,
        );

        $this->upsertTask(
            title: 'Chuẩn hoá thư viện thành phần giao diện',
            unit: $productUnit,
            creator: $productLead,
            assignee: $designer,
            status: TaskStatus::Todo,
            priority: TaskPriority::Medium,
            dueAt: now()->addDays(8),
        );

        $this->upsertTask(
            title: 'Thiết lập giám sát hiệu năng API',
            unit: $engineeringUnit,
            creator: $engineeringLead,
            assignee: $developer,
            status: TaskStatus::InProgress,
            priority: TaskPriority::Urgent,
            dueAt: now()->addDays(4),
            progress: 65,
        );

        $this->upsertTask(
            title: 'Nâng cấp quy trình sao lưu dữ liệu',
            unit: $engineeringUnit,
            creator: $engineeringLead,
            assignee: $developer,
            status: TaskStatus::WaitingReview,
            priority: TaskPriority::High,
            dueAt: now()->addDays(2),
            progress: 90,
        );

        $this->upsertTask(
            title: 'Rà soát SLA xử lý yêu cầu',
            unit: $engineeringUnit,
            creator: $engineeringLead,
            assignee: $developer,
            status: TaskStatus::Draft,
            priority: TaskPriority::Medium,
            dueAt: null,
        );

        $this->upsertTask(
            title: 'Chuẩn bị danh sách khách hàng tiềm năng',
            unit: $salesUnit,
            creator: $salesLead,
            assignee: $accountExecutive,
            status: TaskStatus::InProgress,
            priority: TaskPriority::High,
            dueAt: now()->addDays(3),
            progress: 50,
        );

        $this->upsertTask(
            title: 'Hoàn thiện bộ tài liệu chào bán doanh nghiệp',
            unit: $salesUnit,
            creator: $salesLead,
            assignee: $accountExecutive,
            status: TaskStatus::WaitingReview,
            priority: TaskPriority::Urgent,
            dueAt: now()->addDay(),
            progress: 80,
        );

        $this->upsertTask(
            title: 'Theo dõi cơ hội hợp tác tháng 8',
            unit: $salesUnit,
            creator: $salesLead,
            assignee: $accountExecutive,
            status: TaskStatus::Todo,
            priority: TaskPriority::Medium,
            dueAt: now()->addWeek(),
        );

        $this->upsertTask(
            title: 'Đối soát yêu cầu hỗ trợ nội bộ',
            unit: $operationsUnit,
            creator: $operations,
            assignee: $operationsSpecialist,
            status: TaskStatus::Todo,
            priority: TaskPriority::High,
            dueAt: now()->subDay(),
        );

        $this->upsertTask(
            title: 'Lập kế hoạch trực vận hành cuối tuần',
            unit: $operationsUnit,
            creator: $operations,
            assignee: $operationsSpecialist,
            status: TaskStatus::WaitingApproval,
            priority: TaskPriority::Medium,
            dueAt: now()->addDays(2),
            progress: 75,
        );

        $this->upsertTask(
            title: 'Tổng hợp báo cáo điều hành tháng 7',
            unit: $operationsUnit,
            creator: $operations,
            assignee: $operationsSpecialist,
            status: TaskStatus::Completed,
            priority: TaskPriority::Low,
            dueAt: now()->subDays(2),
            progress: 100,
            completedAt: now()->subDay(),
        );
    }

    private function upsertTask(
        string $title,
        OrganizationUnit $unit,
        User $creator,
        ?User $assignee,
        TaskStatus $status,
        TaskPriority $priority,
        ?CarbonInterface $dueAt,
        int $progress = 0,
        ?CarbonInterface $completedAt = null,
        ?Project $project = null,
    ): void {
        $task = Task::withTrashed()->updateOrCreate(
            [
                'organization_unit_id' => $unit->id,
                'title' => $title,
            ],
            [
                'creator_id' => $creator->id,
                'assignee_id' => $assignee?->id,
                'project_id' => $project?->id,
                'description' => 'Dữ liệu mẫu phục vụ kiểm tra giao diện Task Core.',
                'status' => $status,
                'priority' => $priority,
                'progress' => $progress,
                'due_at' => $dueAt,
                'completed_at' => $completedAt,
            ],
        );

        if ($task->trashed()) {
            $task->restore();
        }
    }

    private function seedDemoProjects(
        OrganizationUnit $productUnit,
        OrganizationUnit $engineeringUnit,
        OrganizationUnit $salesUnit,
        OrganizationUnit $operationsUnit,
    ): void {
        $director = User::query()->where('email', 'director@dormida.test')->firstOrFail();
        $productLead = User::query()->where('email', 'product.lead@dormida.test')->firstOrFail();
        $designer = User::query()->where('email', 'designer@dormida.test')->firstOrFail();
        $productAnalyst = User::query()->where('email', 'product.analyst@dormida.test')->firstOrFail();
        $engineeringLead = User::query()->where('email', 'engineering.lead@dormida.test')->firstOrFail();
        $developer = User::query()->where('email', 'developer@dormida.test')->firstOrFail();
        $salesLead = User::query()->where('email', 'sales.lead@dormida.test')->firstOrFail();
        $accountExecutive = User::query()->where('email', 'account.executive@dormida.test')->firstOrFail();
        $operations = User::query()->where('email', 'operations@dormida.test')->firstOrFail();
        $operationsSpecialist = User::query()->where('email', 'operations.specialist@dormida.test')->firstOrFail();

        $productProject = $this->upsertProject(
            code: 'PROJ-PRODUCT-01',
            name: 'Ra mắt tính năng Onboarding nâng cao',
            description: 'Thiết kế và triển khai luồng onboarding mới cho khách hàng.',
            unit: $productUnit,
            owner: $productLead,
            status: ProjectStatus::Active,
            startDate: now()->subWeeks(2),
            endDate: now()->addMonths(2),
        );
        $this->upsertProjectMember($productProject, $productLead, ProjectMemberRole::Manager);
        $this->upsertProjectMember($productProject, $designer, ProjectMemberRole::Member);
        $this->upsertProjectMember($productProject, $productAnalyst, ProjectMemberRole::Member);
        $this->upsertProjectMember($productProject, $director, ProjectMemberRole::Viewer);

        $engineeringProject = $this->upsertProject(
            code: 'PROJ-ENG-01',
            name: 'Nâng cấp hạ tầng API nội bộ',
            description: 'Tạm dừng chờ đánh giá lại ngân sách hạ tầng quý tới.',
            unit: $engineeringUnit,
            owner: $engineeringLead,
            status: ProjectStatus::OnHold,
            startDate: now()->subMonth(),
            endDate: null,
        );
        $this->upsertProjectMember($engineeringProject, $engineeringLead, ProjectMemberRole::Manager);
        $this->upsertProjectMember($engineeringProject, $developer, ProjectMemberRole::Member);
        $this->upsertProjectMember($engineeringProject, $director, ProjectMemberRole::Viewer);

        $salesProject = $this->upsertProject(
            code: 'PROJ-SALES-01',
            name: 'Chiến dịch mở rộng khách hàng B2B',
            description: 'Xây dựng danh sách khách hàng tiềm năng và tài liệu chào bán.',
            unit: $salesUnit,
            owner: $salesLead,
            status: ProjectStatus::Active,
            startDate: now()->subWeek(),
            endDate: now()->addMonths(3),
        );
        $this->upsertProjectMember($salesProject, $salesLead, ProjectMemberRole::Manager);
        $this->upsertProjectMember($salesProject, $accountExecutive, ProjectMemberRole::Member);
        $this->upsertProjectMember($salesProject, $director, ProjectMemberRole::Viewer);

        $operationsProject = $this->upsertProject(
            code: 'PROJ-OPS-01',
            name: 'Chuẩn hoá quy trình vận hành nội bộ',
            description: 'Tổng hợp và chuẩn hoá báo cáo vận hành định kỳ.',
            unit: $operationsUnit,
            owner: $operations,
            status: ProjectStatus::Completed,
            startDate: now()->subMonths(2),
            endDate: now()->subWeek(),
            closedAt: now()->subDay(),
        );
        $this->upsertProjectMember($operationsProject, $operations, ProjectMemberRole::Manager);
        $this->upsertProjectMember($operationsProject, $operationsSpecialist, ProjectMemberRole::Member);
        $this->upsertProjectMember($operationsProject, $director, ProjectMemberRole::Viewer);

        $this->upsertTask(
            title: 'Phân tích phản hồi khách hàng quý III',
            unit: $productUnit,
            creator: $productLead,
            assignee: $productAnalyst,
            status: TaskStatus::InProgress,
            priority: TaskPriority::High,
            dueAt: now()->addDays(5),
            progress: 40,
            project: $productProject,
        );

        $this->upsertTask(
            title: 'Xây dựng lộ trình sản phẩm quý IV',
            unit: $productUnit,
            creator: $productLead,
            assignee: $productAnalyst,
            status: TaskStatus::WaitingApproval,
            priority: TaskPriority::Urgent,
            dueAt: now()->addDays(2),
            progress: 85,
            project: $productProject,
        );

        $this->upsertTask(
            title: 'Thiết lập giám sát hiệu năng API',
            unit: $engineeringUnit,
            creator: $engineeringLead,
            assignee: $developer,
            status: TaskStatus::InProgress,
            priority: TaskPriority::Urgent,
            dueAt: now()->addDays(4),
            progress: 65,
            project: $engineeringProject,
        );

        $this->upsertTask(
            title: 'Nâng cấp quy trình sao lưu dữ liệu',
            unit: $engineeringUnit,
            creator: $engineeringLead,
            assignee: $developer,
            status: TaskStatus::WaitingReview,
            priority: TaskPriority::High,
            dueAt: now()->addDays(2),
            progress: 90,
            project: $engineeringProject,
        );

        $this->upsertTask(
            title: 'Chuẩn bị danh sách khách hàng tiềm năng',
            unit: $salesUnit,
            creator: $salesLead,
            assignee: $accountExecutive,
            status: TaskStatus::InProgress,
            priority: TaskPriority::High,
            dueAt: now()->addDays(3),
            progress: 50,
            project: $salesProject,
        );

        $this->upsertTask(
            title: 'Hoàn thiện bộ tài liệu chào bán doanh nghiệp',
            unit: $salesUnit,
            creator: $salesLead,
            assignee: $accountExecutive,
            status: TaskStatus::WaitingReview,
            priority: TaskPriority::Urgent,
            dueAt: now()->addDay(),
            progress: 80,
            project: $salesProject,
        );

        $this->upsertTask(
            title: 'Tổng hợp số liệu vận hành tuần',
            unit: $operationsUnit,
            creator: $operations,
            assignee: $operations,
            status: TaskStatus::Completed,
            priority: TaskPriority::Low,
            dueAt: now()->subDay(),
            progress: 100,
            completedAt: now()->subHours(3),
            project: $operationsProject,
        );

        $this->upsertTask(
            title: 'Tổng hợp báo cáo điều hành tháng 7',
            unit: $operationsUnit,
            creator: $operations,
            assignee: $operationsSpecialist,
            status: TaskStatus::Completed,
            priority: TaskPriority::Low,
            dueAt: now()->subDays(2),
            progress: 100,
            completedAt: now()->subDay(),
            project: $operationsProject,
        );
    }

    private function upsertProject(
        string $code,
        string $name,
        string $description,
        OrganizationUnit $unit,
        User $owner,
        ProjectStatus $status,
        ?CarbonInterface $startDate,
        ?CarbonInterface $endDate,
        ?CarbonInterface $closedAt = null,
    ): Project {
        $project = Project::withTrashed()->updateOrCreate(
            ['code' => $code],
            [
                'organization_unit_id' => $unit->id,
                'owner_id' => $owner->id,
                'name' => $name,
                'description' => $description,
                'status' => $status,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'closed_at' => $closedAt,
                'close_reason' => null,
            ],
        );

        if ($project->trashed()) {
            $project->restore();
        }

        return $project;
    }

    private function upsertProjectMember(Project $project, User $user, ProjectMemberRole $role): void
    {
        $project->members()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'role' => $role,
                'joined_at' => now(),
            ],
        );
    }
}
