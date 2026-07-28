# Sprint 0 — Foundation: Design

## 1. Mục tiêu

Khởi tạo skeleton ứng dụng DORMIDA WORK theo đúng stack và quy ước đã định trong `dormida-work-ai-kit` (README, `prompts/01_MASTER_PROMPT.md`, `prompts/02_ARCHITECTURE.md`, `context/sprint-plan.md`), làm nền cho các sprint nghiệp vụ tiếp theo (Identity, Task Core, Project, ...).

Đây là sub-project đầu tiên trong chuỗi 8 sprint của `context/sprint-plan.md`. Sprint 0 chỉ bao gồm tooling/hạ tầng — không có business logic hay module nghiệp vụ nào.

## 2. Vị trí code

App Laravel được đặt trực tiếp trong repo `dormida-work-ai-kit` (cùng thư mục gốc với `README.md`, `prompts/`, `context/`, `templates/`), cùng repo GitHub `xuannt4t/dormida-work`. Không tách repo riêng.

## 3. Cách scaffold

- Do Laravel installer yêu cầu thư mục trống, Laravel + Breeze được cài trong thư mục tạm rồi merge thủ công vào repo hiện có, không đè `README.md`, `prompts/`, `context/`, `templates/`.
- Dùng **Laravel Breeze** làm starter kit: `php artisan breeze:install vue --typescript` → cho Inertia + Vue 3 + TypeScript + Tailwind, kèm sẵn luồng auth cơ bản (login/register/forgot-password) làm nền cho module Authentication ở Sprint 1.
- Test framework: **Pest** (mặc định Laravel 11, khớp `prompts/08_TESTING.md`).

## 4. Cấu hình hạ tầng

- Database: MySQL, tên `dormida_work`, host `127.0.0.1:3306`, user `root` không mật khẩu (Laragon default), charset `utf8mb4` (mặc định Laravel 11).
- Redis: `CACHE_STORE=redis`, `QUEUE_CONNECTION=redis`, `SESSION_DRIVER=redis` — khớp `prompts/02_ARCHITECTURE.md` (Redis cho cache/queue/session). Reverb (broadcast/realtime) để dành Sprint 4, không cấu hình ở Sprint 0.

## 5. Frontend

- Tailwind CSS đi kèm sẵn trong Breeze.
- Thêm **PrimeVue** (+ theme) theo `prompts/05_FRONTEND.md`.
- **Không** cài VueUse/FullCalendar/VueDraggable/ApexCharts ở Sprint 0 (YAGNI) — thêm đúng lúc cần ở sprint dùng đến (Calendar, Task, Dashboard).
- Giữ cấu trúc `resources/js/{Components,Layouts,Pages}` sẵn có của Breeze, khớp `prompts/05_FRONTEND.md`.
- **Không** tạo trước các thư mục rỗng `Composables/Constants/Services/Types/Utils` — tạo khi có nội dung thật ở sprint sau.

## 6. Coding convention tooling

- **Laravel Pint** cho PSR-12 (backend), theo `prompts/10_CODING_STANDARD.md`.
- **ESLint + Prettier** cho Vue/TS (cấu hình mới, Breeze mặc định chưa có).
- **Không** tạo trước các thư mục rỗng `app/{Actions,DTO,Enums,Events,Exceptions,Jobs,Listeners,Notifications,Observers,Policies,Repositories,Rules,Services,Support,Traits,ValueObjects}` theo `prompts/02_ARCHITECTURE.md` — tạo cùng lúc với module Identity ở Sprint 1, tránh thư mục rỗng vô nghĩa.

## 7. CI cơ bản (GitHub Actions)

Workflow chạy khi push/PR vào `main`:

```text
composer install
./vendor/bin/pint --test
npm ci
npm run lint (eslint)
npm run build
php artisan test (Pest)
```

## 8. Testing

Sprint 0 không có business logic nên không có test nghiệp vụ. Chỉ cần xác nhận:

- `php artisan test` chạy pass với test mặc định (welcome page / auth scaffold của Breeze).
- Build frontend (`npm run build`) không lỗi.
- Pint và ESLint không báo lỗi trên code scaffold.

## 9. Git

Sau khi scaffold xong, tạo commit riêng biệt cho việc khởi tạo (ví dụ `chore: scaffold Laravel 11 + Breeze (Inertia/Vue/TS) foundation`). Không tự động push — chờ xác nhận của user trước khi push lên GitHub.

## 10. Ngoài phạm vi Sprint 0

- Không có module nghiệp vụ (User, Organization, Role & Permission, Task, ...) — thuộc Sprint 1 trở đi.
- Không cấu hình Reverb/broadcast — Sprint 4.
- Không cấu hình PWA — Sprint 7.
- Không có Spatie Laravel Permission, Laravel Excel, DomPDF, object storage — thêm khi sprint cần dùng đến.
