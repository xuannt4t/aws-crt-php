# 06 — UI/UX

## 1. Nguyên tắc

Giao diện DORMIDA WORK phải:

- Gọn.
- Dễ quét thông tin.
- Phân cấp rõ.
- Tập trung vào công việc.
- Không dùng hiệu ứng thừa.
- Hoạt động tốt trên desktop, tablet và mobile.

## 2. Bố cục

Desktop:

- Sidebar điều hướng.
- Topbar.
- Breadcrumb.
- Vùng nội dung có max-width phù hợp.
- Panel phụ hoặc drawer cho chi tiết nhanh.

Mobile:

- Sidebar chuyển thành drawer.
- Action chính dễ tiếp cận.
- Bảng chuyển sang card hoặc horizontal scroll có kiểm soát.
- Filter đưa vào drawer.

## 3. Trạng thái

Mỗi màn hình dữ liệu phải có:

- Loading.
- Empty.
- Error.
- Permission denied.
- Success feedback.
- Disabled state.

## 4. Màu trạng thái

Dùng token thống nhất cho:

- Draft.
- Todo.
- In progress.
- Waiting review.
- Waiting approval.
- Completed.
- Rejected.
- Overdue.
- Cancelled.

Không hard-code màu riêng ở từng trang.

## 5. Typography

- Tiêu đề trang rõ ràng.
- Heading không nhảy cấp tùy tiện.
- Text phụ đủ tương phản.
- Số liệu dashboard dùng font weight rõ nhưng không quá lớn.
- Nội dung dài có line-height dễ đọc.

## 6. Form

- Trường liên quan đặt theo nhóm.
- Required rõ ràng.
- Help text ngắn.
- Lỗi hiển thị gần field.
- Không dùng placeholder thay label.
- Form dài chia section hoặc step.

## 7. DataTable

- Header cố định khi bảng dài.
- Cột action ở vị trí nhất quán.
- Không nhồi quá nhiều cột.
- Cột phụ có thể ẩn trên mobile.
- Cho phép lưu filter khi nghiệp vụ cần.
- Empty state giải thích hành động tiếp theo.

## 8. Dialog và Drawer

Dialog dùng cho:

- Xác nhận.
- Form ngắn.
- Quyết định tập trung.

Drawer dùng cho:

- Chi tiết nhanh.
- Filter.
- Form vừa.
- Activity timeline.

Trang riêng dùng cho form phức tạp hoặc nội dung dài.

## 9. Dashboard

- Mỗi card trả lời một câu hỏi cụ thể.
- Không đưa chart chỉ để trang trí.
- Hiển thị thời gian dữ liệu được cập nhật.
- Có filter phạm vi thời gian và tổ chức khi cần.
- Số liệu phải phù hợp permission.
