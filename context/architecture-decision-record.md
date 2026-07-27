# Architecture Decision Records

## ADR-001 — Modular Monolith

Chọn modular monolith thay vì microservice.

Lý do:

- Dự án nghiệp vụ nội bộ.
- Cần phát triển nhanh.
- Đội ngũ dễ vận hành Laravel.
- Transaction liên module còn nhiều.
- Chưa có bằng chứng cần scale từng service độc lập.

## ADR-002 — Laravel + Inertia

Chọn Inertia cho giao diện nội bộ.

Lý do:

- Giảm chi phí xây API riêng cho mọi màn hình.
- Giữ routing và authorization tập trung ở Laravel.
- Vue vẫn đủ mạnh cho interaction phức tạp.

## ADR-003 — MySQL

Chọn MySQL 8.

Lý do:

- Phù hợp hệ thống CRUD và workflow.
- Đội ngũ quen vận hành.
- Hệ sinh thái Laravel tốt.
- Chưa có yêu cầu đặc thù bắt buộc PostgreSQL.

## ADR-004 — Redis + Reverb

Redis cho queue/cache và Reverb cho realtime.

Lý do:

- Tích hợp Laravel tốt.
- Phù hợp notification và cập nhật công việc.
