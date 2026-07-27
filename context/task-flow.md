# Task Flow

## Trạng thái đề xuất

```text
draft
todo
in_progress
waiting_review
waiting_approval
completed
cancelled
```

`overdue` nên là trạng thái suy diễn, không nhất thiết lưu trực tiếp.

## Chuyển trạng thái

- `draft → todo`: công việc đã đủ thông tin để giao.
- `todo → in_progress`: người phụ trách bắt đầu.
- `in_progress → waiting_review`: gửi kiểm tra.
- `waiting_review → in_progress`: yêu cầu chỉnh sửa.
- `waiting_review → waiting_approval`: qua bước review nếu có.
- `waiting_approval → completed`: phê duyệt.
- `waiting_approval → in_progress`: từ chối hoặc yêu cầu chỉnh sửa.
- Trạng thái đang hoạt động có thể chuyển `cancelled` nếu có quyền và lý do.

## Quy tắc

- Mỗi lần chuyển trạng thái phải lưu history.
- Không cho chuyển trạng thái tùy ý.
- Backend kiểm tra transition.
- Frontend chỉ hiển thị action hợp lệ.
- Hoàn thành có thể yêu cầu checklist hoàn tất.
- Cancel phải có lý do.
