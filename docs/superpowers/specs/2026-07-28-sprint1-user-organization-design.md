# Sprint 1, Đợt 1 — User + Organization: Design

## 1. Mục tiêu

Xây nền tảng danh tính cho DORMIDA WORK: mô hình tổ chức đa cấp và quản trị người dùng, theo `context/business-rules.md` (mục 1 — Tổ chức) và `context/permission-matrix.md`. Đây là sub-project đầu tiên trong 3 đợt của Sprint 1 (Identity — `context/sprint-plan.md`):

1. **User + Organization** (đợt này)
2. Role & Permission (Spatie) — thay thế cơ chế phân quyền tạm bằng permission thật
3. Audit Log nền tảng

Authentication dùng lại Breeze đã có từ Sprint 0, chỉ điều chỉnh: tắt đăng ký công khai.

## 2. Database

### `organization_units` (mới)

Cây tự tham chiếu, không giới hạn số cấp cứng. Soft delete theo `prompts/03_DATABASE.md` (Organization unit nằm trong danh sách bắt buộc soft delete).

```
id                bigint unsigned, PK
parent_id         bigint unsigned, nullable, FK -> organization_units.id, index
name              varchar
code              varchar, unique
is_active         boolean, default true
created_at
updated_at
deleted_at
```

Business rule ràng buộc ở tầng Service/Action (không chỉ DB):
- Không cho một unit trở thành cha/tổ tiên của chính nó (chống vòng lặp khi cập nhật `parent_id`).
- Không cho xoá (soft delete) một unit còn unit con hoặc còn user đang gán `organization_unit_id` vào nó — phải chuyển/rỗng trước.

### Mở rộng `users` (đã có từ Breeze Sprint 0: `name`, `email`, `password`, `email_verified_at`, soft delete đã có)

```
+ organization_unit_id   bigint unsigned, nullable ở DB, FK -> organization_units.id, index
+ is_system_admin        boolean, default false
+ is_active               boolean, default true
+ employee_code          varchar, nullable, unique
+ phone                  varchar, nullable
+ job_title               varchar, nullable
```

`organization_unit_id` nullable ở tầng DB (tránh chicken-and-egg khi seed user đầu tiên trước khi có unit, và tránh cascade lỗi khi unit bị xoá), nhưng **bắt buộc chọn** khi tạo/sửa user qua form (validate ở FormRequest).

`is_active` khác với soft delete: dùng cho hành động "vô hiệu hoá" (`user.disable` trong permission-matrix.md) — chặn đăng nhập nhưng giữ nguyên record và các quan hệ (task đã giao, lịch sử...). Soft delete (`deleted_at`) dùng cho trường hợp xoá hẳn khỏi danh sách hoạt động, hiếm hơn.

## 3. Authentication — tắt đăng ký công khai

Hệ thống nội bộ doanh nghiệp không cần self-registration. Gỡ bỏ khỏi Breeze scaffold (Sprint 0):
- Route `register` (GET/POST) trong `routes/auth.php`.
- `RegisteredUserController`.
- `resources/js/Pages/Auth/Register.vue`.
- Link "Register" trên trang Login.
- Test `tests/Feature/Auth/RegistrationTest.php` (không còn route để test).

Không để lại code chết theo `prompts/10_CODING_STANDARD.md` §7. Login, quên mật khẩu, xác thực email, đổi mật khẩu (trang Profile) giữ nguyên như Breeze cung cấp.

Khi admin tạo user mới (mục 6), admin nhập mật khẩu ban đầu trực tiếp trong form tạo user — Sprint 0 chưa có hạ tầng gửi mail xác thực/mời qua email, việc đó để ngoài phạm vi đợt này (mục 8). User tự đổi mật khẩu sau qua trang Profile sẵn có.

## 4. Authorization tạm thời

Role & Permission (Spatie) là đợt sau, nhưng `prompts/01_MASTER_PROMPT.md` cấm bỏ qua authorization. Giải pháp: cờ `is_system_admin` trên `users`, dùng bởi Policy thật (không phải kiểm tra tuỳ tiện trong controller):

- `OrganizationUnitPolicy`: `viewAny`, `view` → mọi user đã đăng nhập (xem cây tổ chức nội bộ). `create`, `update`, `delete` → chỉ `$user->is_system_admin === true`.
- `UserPolicy`: `viewAny`, `view` → mọi user đã đăng nhập (danh bạ nhân viên). `create`, `update`, `disable`, `delete` → chỉ `$user->is_system_admin === true`.

Đợt Role & Permission sau sẽ thay **nội dung bên trong** hai Policy này bằng permission check thật (`organization.create`, `user.assign_role`, v.v. theo `context/permission-matrix.md`), giữ nguyên chữ ký phương thức và route — không phá vỡ phần đã triển khai ở đợt này.

## 5. Backend deliverables

- Migration: tạo `organization_units`, mở rộng `users`.
- Model `OrganizationUnit` (quan hệ `parent()`, `children()`, `users()`), mở rộng model `User` (quan hệ `organizationUnit()`).
- `OrganizationUnitPolicy`, `UserPolicy`.
- `StoreOrganizationUnitRequest`, `UpdateOrganizationUnitRequest`, `StoreUserRequest`, `UpdateUserRequest`.
- `OrganizationUnitController`, `UserController` (Inertia, theo luồng Route → Controller → FormRequest → Action → Model → Inertia Response của `prompts/02_ARCHITECTURE.md`).
- Routes: `organization-units.{index,create,store,edit,update,destroy}`, `users.{index,create,store,edit,update,destroy}`, cộng action nghiệp vụ `users.disable`/`users.enable`.
- `DatabaseSeeder`: tạo 1 organization unit gốc + 1 user `is_system_admin = true` để đăng nhập lần đầu (email/password đọc từ biến môi trường khi có, mặc định cố định cho local nếu không).
- Gỡ bỏ `RegisteredUserController` và route `register` (mục 3).

## 6. Frontend deliverables

- `Pages/OrganizationUnits/Index.vue` (dạng cây — PrimeVue Tree/TreeTable theo `prompts/05_FRONTEND.md`), `Create.vue`, `Edit.vue`.
- `Pages/Users/Index.vue` (danh sách, filter theo organization unit, action disable/enable), `Create.vue`, `Edit.vue`.
- Đủ loading/empty/error state, permission-aware action (ẩn nút create/edit/delete khi không phải `is_system_admin`), responsive theo `prompts/06_UI_UX.md` và `context/ui-guideline.md`.
- Component dùng chung khi hợp lý (`AppDataTable`, `AppStatusBadge` cho `is_active`, v.v. theo `context/ui-guideline.md` — chỉ tạo mới khi có ít nhất 2 nơi dùng).

## 7. Testing

Theo `prompts/08_TESTING.md`:
- `is_system_admin` tạo/sửa/xoá được organization unit và user; user thường bị từ chối (403).
- Chống vòng lặp cha-con khi cập nhật `parent_id`.
- Chặn xoá (soft delete) organization unit còn con hoặc còn user gán vào.
- Validation: trường bắt buộc thiếu (name/code trùng, email trùng, organization_unit_id bắt buộc khi tạo user).
- Route `register` (GET/POST) trả 404 sau khi gỡ bỏ.
- Seeder tạo được admin đăng nhập thành công.
- Action disable user chặn được đăng nhập của user đó (nhưng không xoá record).

## 8. Ngoài phạm vi đợt này

- Role & Permission thật (Đợt 2 của Sprint 1).
- Audit Log (Đợt 3 của Sprint 1).
- User tham gia nhiều dự án ngoài đơn vị chính (thuộc module Project, sprint sau — khác với việc mỗi user chỉ có một `organization_unit_id`/đơn vị chính).
- Gửi email mời/xác thực khi admin tạo user mới.
- UI quản lý role/permission (chưa có role/permission thật ở đợt này).
