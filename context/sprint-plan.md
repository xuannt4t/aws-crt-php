# Sprint Plan

## Trạng thái hiện tại

- Sprint 0 — Foundation: hoàn tất.
- Sprint 1 — Identity: hoàn tất Authentication, User, Organization, Role & Permission và Audit Log nền tảng.
- Sprint 2 — Task Core: hoàn tất; đã hoàn tất Task CRUD, người phụ trách chính, filter, phân trang, comment, luồng `draft → todo → in_progress → waiting_review` có history, Attachment (upload, tải xuống có kiểm quyền, xoá mềm kèm audit) và Activity timeline hợp nhất.
- Sprint 3 — Project: hoàn tất; đã hoàn tất Project CRUD, quản lý thành viên dự án (`manager`/`member`/`viewer`), gán Task theo project, đóng dự án (kiểm tra công việc mở) và dữ liệu demo/tài liệu.
- Công việc lặp lại định kỳ: hoàn tất; mẫu lặp `task_recurrences` (ngày/tuần/tháng/quý), lệnh `tasks:generate-recurring` chạy 00:05 hằng ngày sinh công việc từ mẫu, CRUD mẫu, bật/tắt và trang "Việc định kỳ".
- Công việc kế tiếp: Sprint 4 — Approval.

## Sprint 0 — Foundation

- Khởi tạo Laravel 11.
- Vue 3 + Inertia + TypeScript.
- Tailwind + PrimeVue.
- MySQL + Redis.
- CI cơ bản.
- Quy chuẩn code.

## Sprint 1 — Identity

- Authentication.
- User.
- Organization.
- Role & Permission.
- Audit log nền tảng.

## Sprint 2 — Task Core

- Task CRUD.
- Assignment.
- Status flow.
- Comment.
- Attachment.
- Activity timeline.

## Sprint 3 — Project

- Project CRUD.
- Project member.
- Task theo project.
- Project progress.

## Sprint 4 — Approval

- Approval flow.
- Review.
- Notification.
- Realtime.

## Sprint 5 — Calendar & Planning

- Calendar.
- Plan.
- Objective.
- Deadline reminder.

## Sprint 6 — Reporting

- Dashboard.
- Report.
- Export.
- Performance review.

## Sprint 7 — Hardening

- Security review.
- Performance review.
- Accessibility.
- PWA.
- Backup và monitoring.

## Tài liệu dự án (xuyên suốt các sprint)

- Soạn tài liệu SRS (Đặc tả yêu cầu phần mềm) cho DORMIDA WORK: hoàn tất bản 1.0 tại `docs/srs/SRS.md`.
- Xuất SRS ra file PDF để bàn giao: `docs/srs/DORMIDA-WORK-SRS.pdf`, build bằng `node docs/srs/build-pdf.mjs`.
- Cập nhật lại SRS sau mỗi sprint hoàn tất và render lại PDF.
