# UI Guideline

## Thành phần dùng chung

- `AppPageHeader`
- `AppBreadcrumb`
- `AppDataTable`
- `AppEmptyState`
- `AppErrorState`
- `AppConfirmDialog`
- `AppStatusBadge`
- `AppUserAvatar`
- `AppActivityTimeline`
- `AppFilterDrawer`

Chỉ tạo component dùng chung khi có ít nhất hai nơi sử dụng hoặc có giá trị quy chuẩn rõ ràng.

## Breakpoint

Dùng breakpoint Tailwind mặc định trừ khi thiết kế yêu cầu khác.

## Form action

- Primary action bên phải.
- Cancel bên trái primary hoặc ở vị trí nhất quán.
- Delete tách khỏi nhóm primary.
- Trên mobile, action quan trọng có thể sticky dưới cùng.

## Table mobile

Ưu tiên:

1. Ẩn cột phụ.
2. Cho phép horizontal scroll.
3. Chuyển sang card nếu dữ liệu cần đọc theo từng bản ghi.
