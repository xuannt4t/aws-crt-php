# DORMIDA WORK — AI Development Kit

Bộ tài liệu này là nguồn hướng dẫn chính thức để Claude Code, Codex, ChatGPT, Cursor hoặc Windsurf phân tích, triển khai, kiểm thử và review hệ thống DORMIDA WORK.

## 1. Mục tiêu

DORMIDA WORK là hệ thống quản trị công việc nội bộ doanh nghiệp, hỗ trợ:

- Cơ cấu tổ chức.
- Người dùng, vai trò và phân quyền.
- Mục tiêu và kế hoạch.
- Công việc và công việc con.
- Dự án và thành viên dự án.
- Lịch làm việc.
- Quy trình phê duyệt.
- Báo cáo và dashboard.
- Đánh giá hiệu suất.
- Thông báo và hoạt động thời gian thực.
- Nhật ký thao tác và truy vết thay đổi.

## 2. Công nghệ chính

### Backend

- PHP 8.3+
- Laravel 11
- MySQL 8
- Redis
- Laravel Queue
- Laravel Scheduler
- Laravel Reverb
- Spatie Laravel Permission
- Laravel Excel
- DomPDF
- Cloudflare R2 hoặc S3-compatible storage

### Frontend

- Vue 3
- Inertia.js
- TypeScript
- Tailwind CSS
- PrimeVue
- VueUse
- FullCalendar
- VueDraggable
- ApexCharts
- PWA

## 3. Cách sử dụng

Trước khi triển khai bất kỳ chức năng nào, AI phải đọc theo thứ tự:

1. `prompts/01_MASTER_PROMPT.md`
2. `prompts/02_ARCHITECTURE.md`
3. `prompts/03_DATABASE.md`
4. File chuyên môn tương ứng với nhiệm vụ.
5. Các file trong `context/`.
6. Template phù hợp trong `templates/`.

Ví dụ yêu cầu:

```text
Đọc toàn bộ thư mục ai-kit.
Triển khai module Task theo đúng kiến trúc, business rule, permission, test và checklist review.
Không thay đổi quy ước nếu chưa nêu rõ lý do.
```

## 4. Nguyên tắc bắt buộc

- Không code ngay khi chưa phân tích phạm vi.
- Không tự phát minh business rule.
- Không bỏ qua authorization.
- Không truy vấn trực tiếp trong controller.
- Không đưa logic nghiệp vụ phức tạp vào Vue component.
- Không tạo abstraction khi chưa có nhu cầu thực tế.
- Không làm thay đổi ngoài phạm vi nhiệm vụ.
- Mọi thay đổi database phải có migration.
- Mọi chức năng quan trọng phải có test.
- Mọi danh sách lớn phải hỗ trợ phân trang phía server.
- Mọi thao tác nhạy cảm phải ghi audit log.

## 5. Quy trình triển khai module

1. Phân tích yêu cầu.
2. Xác định actor và permission.
3. Xác định dependency.
4. Thiết kế hoặc cập nhật database.
5. Thiết kế endpoint hoặc Inertia flow.
6. Triển khai backend.
7. Triển khai frontend.
8. Viết test.
9. Kiểm tra bảo mật và hiệu năng.
10. Cập nhật tài liệu.
11. Tự review trước khi kết thúc.

## 6. Định nghĩa hoàn thành

Một module chỉ được coi là hoàn thành khi:

- Business rule đã được triển khai.
- Authorization hoạt động.
- Validation đầy đủ.
- Không có N+1 rõ ràng.
- Có transaction tại luồng ghi nhiều bảng.
- Có test cho luồng chính và luồng lỗi quan trọng.
- Giao diện responsive.
- Có loading, empty state và error state.
- Có audit log với thao tác nhạy cảm.
- Tài liệu liên quan đã cập nhật.
- Không còn placeholder hoặc TODO không giải thích.

## 7. Triển khai

Quy trình dựng server, deploy và rollback: [docs/deployment.md](docs/deployment.md).

## 8. Cấu trúc

```text
ai-kit/
├── README.md
├── prompts/
├── context/
└── templates/
```

Không sửa các quy ước trong bộ tài liệu này chỉ để làm nhanh một chức năng. Khi cần ngoại lệ, phải ghi rõ lý do kỹ thuật và tác động.
