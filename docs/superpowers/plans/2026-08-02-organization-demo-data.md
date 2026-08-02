# Organization Demo Data Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Mở rộng bộ dữ liệu demo production để thể hiện rõ cơ cấu phòng ban nhiều cấp, người dùng theo vai trò và công việc theo từng đơn vị.

**Architecture:** Giữ `DemoDataSeeder` là entry point idempotent, dùng mã đơn vị, email và cặp đơn vị/tiêu đề làm khóa tự nhiên. Mở rộng trực tiếp các helper hiện có để không đưa mô hình Dự án giả hoặc thêm abstraction không cần thiết; Pest kiểm chứng số lượng, quan hệ, vai trò và khả năng chạy lại.

**Tech Stack:** Laravel 11, Eloquent, Spatie Laravel Permission, Pest 3, MySQL/TiDB Cloud, Render Docker.

## Global Constraints

- Không truncate bảng, không xoá dữ liệu do người dùng tạo và không thay đổi tài khoản quản trị hiện tại.
- Seeder phải idempotent và khôi phục được bản ghi demo đã soft-delete.
- Bộ demo gồm 8 đơn vị ngoài root, 11 người dùng demo và 18 công việc demo.
- Phòng Sản phẩm, Kỹ thuật và Kinh doanh đều có ít nhất một nhóm con.
- Không tạo bảng, model hoặc dữ liệu giả mang tên Dự án.
- Tất cả nội dung demo dùng tiếng Việt và mật khẩu `password` chỉ áp dụng cho tài khoản demo.
- Tuân thủ TDD: test phải fail đúng lý do trước khi sửa production seeder.

---

### Task 1: Mở rộng cơ cấu, người dùng và công việc demo

**Files:**
- Modify: `tests/Feature/DemoDataSeederTest.php`
- Modify: `database/seeders/DemoDataSeeder.php`

**Interfaces:**
- Consumes: `DemoDataSeeder::run(): void`, `upsertOrganizationUnit(...)`, `upsertUser(...)`, `upsertTask(...)` hiện có.
- Produces: các mã đơn vị `EXEC`, `PRODUCT`, `PRODUCT_DESIGN`, `ENGINEERING`, `ENGINEERING_PLATFORM`, `SALES`, `SALES_B2B`, `OPERATIONS`.
- Produces: 11 email demo cố định và 18 công việc nhận diện bằng `organization_unit_id` + `title`.

- [ ] **Step 1: Viết test fail cho cây tổ chức mở rộng**

Trong `tests/Feature/DemoDataSeederTest.php`, mở rộng test đầu để lấy `ENGINEERING_PLATFORM`, `SALES`, `SALES_B2B` và assert quan hệ cha–con:

```php
$engineeringUnit = OrganizationUnit::query()->where('code', 'ENGINEERING')->firstOrFail();
$platformUnit = OrganizationUnit::query()->where('code', 'ENGINEERING_PLATFORM')->firstOrFail();
$salesUnit = OrganizationUnit::query()->where('code', 'SALES')->firstOrFail();
$b2bUnit = OrganizationUnit::query()->where('code', 'SALES_B2B')->firstOrFail();

expect($platformUnit->parent_id)->toBe($engineeringUnit->id)
    ->and($b2bUnit->parent_id)->toBe($salesUnit->id);
```

- [ ] **Step 2: Viết test fail cho người dùng và phân bổ công việc**

Assert đủ 11 email demo, đúng role của trưởng phòng Kỹ thuật/Kinh doanh và mỗi phòng chính có công việc:

```php
$engineeringLead = User::query()->where('email', 'engineering.lead@dormida.test')->firstOrFail();
$salesLead = User::query()->where('email', 'sales.lead@dormida.test')->firstOrFail();

expect($engineeringLead->hasRole(RoleName::DepartmentManager->value))->toBeTrue()
    ->and($salesLead->hasRole(RoleName::DepartmentManager->value))->toBeTrue()
    ->and(User::query()->where('email', 'account.executive@dormida.test')->exists())->toBeTrue()
    ->and(User::query()->where('email', 'product.analyst@dormida.test')->exists())->toBeTrue()
    ->and(User::query()->where('email', 'operations.specialist@dormida.test')->exists())->toBeTrue();

expect(Task::query()->count())->toBe(18);

foreach (['PRODUCT', 'ENGINEERING', 'SALES', 'OPERATIONS'] as $code) {
    $unitId = OrganizationUnit::query()->where('code', $code)->value('id');
    expect(Task::query()->where('organization_unit_id', $unitId)->exists())->toBeTrue();
}
```

- [ ] **Step 3: Mở rộng test idempotent**

Sau hai lần chạy seeder, assert đúng 8 đơn vị demo, 11 email demo, một audit log mẫu và 18 task:

```php
expect(OrganizationUnit::query()->whereIn('code', $demoUnitCodes)->count())->toBe(8)
    ->and(User::query()->whereIn('email', $demoUserEmails)->count())->toBe(11)
    ->and(AuditLog::query()->where('metadata->source', 'demo_seeder')->count())->toBe(1)
    ->and(Task::query()->count())->toBe(18);
```

- [ ] **Step 4: Chạy test đỏ**

Run:

```powershell
& 'C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe' vendor/bin/pest tests/Feature/DemoDataSeederTest.php
```

Expected: FAIL vì các mã đơn vị/email mới chưa tồn tại và task count vẫn là 6.

- [ ] **Step 5: Thêm ba đơn vị demo**

Trong `DemoDataSeeder::run()`, tạo:

```php
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
```

- [ ] **Step 6: Thêm năm người dùng demo**

Dùng `upsertUser()` để tạo chính xác:

```php
['engineering.lead@dormida.test', $engineeringUnit->id, 'Trần Đức Long', 'DW-ENG-002', '0901000007', 'Trưởng phòng Kỹ thuật', RoleName::DepartmentManager],
['sales.lead@dormida.test', $salesUnit->id, 'Nguyễn Thu Trang', 'DW-SAL-001', '0901000008', 'Trưởng phòng Kinh doanh', RoleName::DepartmentManager],
['account.executive@dormida.test', $b2bSalesUnit->id, 'Đỗ Minh Quân', 'DW-SAL-002', '0901000009', 'Chuyên viên Kinh doanh B2B', RoleName::Employee],
['product.analyst@dormida.test', $productUnit->id, 'Phan Khánh Linh', 'DW-PRD-002', '0901000010', 'Chuyên viên Phân tích sản phẩm', RoleName::Employee],
['operations.specialist@dormida.test', $operationsUnit->id, 'Bùi Hải Yến', 'DW-OPS-003', '0901000011', 'Chuyên viên Vận hành', RoleName::Employee],
```

Gọi helper bằng named arguments để giữ đúng signature hiện có; không thay password tài khoản admin.

- [ ] **Step 7: Thêm 12 công việc thành 18 bản ghi demo**

Mở rộng `seedDemoTasks()` nhận thêm `$salesUnit`, load năm user mới và gọi `upsertTask()` cho các tiêu đề sau:

```text
Phân tích phản hồi khách hàng quý III
Xây dựng lộ trình sản phẩm quý IV
Chuẩn hoá thư viện thành phần giao diện
Thiết lập giám sát hiệu năng API
Nâng cấp quy trình sao lưu dữ liệu
Chuẩn bị danh sách khách hàng tiềm năng
Hoàn thiện bộ tài liệu chào bán doanh nghiệp
Theo dõi cơ hội hợp tác tháng 8
Đối soát yêu cầu hỗ trợ nội bộ
Lập kế hoạch trực vận hành cuối tuần
Rà soát SLA xử lý yêu cầu
Tổng hợp báo cáo điều hành tháng 7
```

Phân bổ 3 task cho Sản phẩm, 3 task cho Kỹ thuật, 3 task cho Kinh doanh, 3 task cho Vận hành; dùng đủ `Draft`, `Todo`, `InProgress`, `WaitingReview`, `WaitingApproval`, `Completed`, các priority và cả hạn tương lai/quá hạn.

- [ ] **Step 8: Chạy test xanh và định dạng**

Run:

```powershell
& 'C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe' vendor/bin/pest tests/Feature/DemoDataSeederTest.php
& 'C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe' vendor/bin/pint database/seeders/DemoDataSeeder.php tests/Feature/DemoDataSeederTest.php
```

Expected: 2 tests PASS; Pint exit 0.

- [ ] **Step 9: Commit**

```powershell
git add database/seeders/DemoDataSeeder.php tests/Feature/DemoDataSeederTest.php
git commit -m "feat(demo): expand organization sample data"
```

---

### Task 2: Xác minh, push và nạp dữ liệu production

**Files:**
- Modify: `docs/deployment.md`

**Interfaces:**
- Consumes: `DemoDataSeeder` idempotent từ Task 1.
- Produces: TiDB production có 8 đơn vị demo ngoài root, 11 người dùng demo và 18 công việc demo; Render phục vụ dữ liệu mới.

- [ ] **Step 1: Bổ sung runbook nạp demo an toàn**

Thêm vào `docs/deployment.md`:

```markdown
### Nạp dữ liệu demo

Chạy trong Render Shell sau khi migration hoàn tất:

`php artisan db:seed --class=Database\\Seeders\\DemoDataSeeder --force`

Seeder chỉ upsert các bản ghi có mã/email/tiêu đề demo cố định, không truncate và có thể chạy lại.
```

- [ ] **Step 2: Chạy verification toàn dự án**

Run:

```powershell
& 'C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe' vendor/bin/pest
& 'C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe' vendor/bin/pint --test
npm run test:unit
npm run lint
npm run format:check
npm run build
git diff --check
```

Expected: Pest 0 failures; frontend tests, lint, format and build đều exit 0.

- [ ] **Step 3: Commit runbook và push main**

```powershell
git add docs/deployment.md
git commit -m "docs(demo): add production seed runbook"
git push origin main
```

Expected: `origin/main` trỏ tới commit mới và working tree sạch.

- [ ] **Step 4: Chờ Render deploy commit mới ở trạng thái live**

Mở service `dormida-work` trên Render, theo dõi deploy log đến khi health check `/up` thành công và các process `php-fpm`, `nginx`, `queue`, `reverb`, `scheduler` ở trạng thái RUNNING.

- [ ] **Step 5: Chạy seeder qua Render Shell**

Trong Shell của service production chạy:

```bash
php artisan db:seed --class='Database\Seeders\DemoDataSeeder' --force
```

Expected: command exit 0; không có lỗi unique key hoặc foreign key.

- [ ] **Step 6: Kiểm tra trực tiếp production**

Đăng nhập bằng tài khoản quản trị hiện có và xác nhận:

```text
Cơ cấu tổ chức: có Phòng Kinh doanh, Nhóm Kinh doanh B2B và Nhóm Nền tảng.
Người dùng: tìm thấy engineering.lead@dormida.test và sales.lead@dormida.test.
Công việc: tổng cộng 18 task demo; lọc Sản phẩm/Kỹ thuật/Kinh doanh/Vận hành đều có kết quả.
```

Không nhập hoặc hiển thị mật khẩu/secret trong log công cụ hay commit.

