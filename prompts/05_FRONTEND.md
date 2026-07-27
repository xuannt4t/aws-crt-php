# 05 — FRONTEND

## 1. Cấu trúc

```text
resources/js/
├── Components/
├── Composables/
├── Constants/
├── Layouts/
├── Pages/
├── Services/
├── Types/
└── Utils/
```

## 2. Vue

- Dùng Composition API.
- Dùng `<script setup lang="ts">`.
- Props và emits phải có type.
- Không dùng `any` nếu có thể mô tả kiểu.
- Logic dùng lại đưa vào composable.
- Component lớn phải tách theo trách nhiệm.

## 3. Inertia

- Dùng Inertia cho navigation và form nội bộ.
- Chỉ dùng axios/fetch cho interaction không cần chuyển trang hoặc API độc lập.
- Dùng `preserveState`, `preserveScroll`, `replace` có chủ đích.
- Filter danh sách phải phản ánh lên URL.
- Pagination phía server.

## 4. Form

Mọi form phải có:

- Trạng thái đang gửi.
- Hiển thị lỗi theo trường.
- Chặn submit lặp.
- Reset hợp lý.
- Confirm cho thao tác nguy hiểm.
- Thông báo thành công/thất bại.

## 5. PrimeVue

Ưu tiên component chuẩn:

- DataTable.
- Dialog.
- Drawer.
- TreeSelect.
- MultiSelect.
- DatePicker.
- Toast.
- ConfirmDialog.
- Skeleton.
- ProgressSpinner.
- Menu.
- Tabs.

Không bọc PrimeVue quá mức nếu wrapper không mang lại quy chuẩn hoặc tái sử dụng thực tế.

## 6. Tailwind

- Dùng spacing scale chuẩn trước khi dùng arbitrary value.
- Hạn chế class quá dài bằng component hoặc computed class.
- Responsive mobile-first.
- Không inline style trừ trường hợp giá trị runtime.
- Dùng token màu chung.

## 7. Trang danh sách

Bắt buộc có:

- Search.
- Filter.
- Sort khi cần.
- Pagination.
- Loading state.
- Empty state.
- Error state.
- Permission-aware actions.
- Responsive mode cho màn hình nhỏ.

## 8. Accessibility

- Label cho input.
- Keyboard navigation cho dialog/menu.
- Focus state rõ.
- Không chỉ dùng màu để truyền đạt trạng thái.
- Icon button phải có aria-label hoặc tooltip.
- Modal phải quản lý focus.

## 9. Performance

- Lazy load trang.
- Không tải toàn bộ option lớn.
- Dùng virtual scroll khi cần.
- Debounce search.
- Hạn chế watcher sâu.
- Không render chart khi container chưa sẵn sàng.
- Cleanup listener và timer khi unmount.

## 10. Realtime

Khi nhận broadcast:

- Không duplicate item.
- Kiểm tra quyền và phạm vi hiện tại.
- Cập nhật state tối thiểu.
- Có fallback refresh.
- Không tin payload để hiển thị dữ liệu nhạy cảm chưa được backend cho phép.
