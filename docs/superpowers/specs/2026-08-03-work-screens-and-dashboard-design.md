# Ba màn công việc và bố cục bảng điều khiển — Thiết kế

**Trạng thái:** đã chốt các lựa chọn với người dùng (2026-08-03).

## 1. Vấn đề

Hai góp ý sau khi module phạm vi dữ liệu hoàn tất:

1. Thanh menu cần tách công việc theo bối cảnh — tổng quan, định kỳ, dự án, phòng ban — và phạm vi mặc định của mỗi bối cảnh phải đúng theo cấp bậc.
2. Màn công việc cần bố cục ba khúc: tóm tắt thống kê, bộ lọc gọn, danh sách chi tiết. Không biểu đồ, không phần trăm tiến độ.

## 2. Lựa chọn đã chốt

| Câu hỏi | Quyết định |
| --- | --- |
| Ô thống kê | Sáu ô: tổng / chưa làm / đang làm / chờ duyệt / hoàn thành / trễ hạn. |
| Ba màn công việc | Một trang dùng chung, khác nhau ở phạm vi mặc định và bộ lọc hiển thị. |
| Quyền xem việc trong dự án | Đặt khi thêm thành viên; mặc định chỉ xem việc của mình. |
| Thành viên dự án đang có | Siết lại theo cấu hình mới. |

## 3. Thay đổi ngược so với bản trước

Ở module phạm vi dữ liệu, `own` của công việc gồm điều kiện "việc thuộc dự án tôi tham gia" — nghĩa là cứ là thành viên thì thấy mọi việc của dự án. Người dùng đã quyết định siết lại: **thành viên chỉ thấy việc của mình trong dự án, trừ khi được cấp quyền xem toàn bộ**.

Đây là thay đổi hành vi có chủ đích, không phải sửa lỗi. Các test đang khoá hành vi cũ phải được cập nhật, không được nới lỏng khẳng định.

## 4. Quyền xem việc trong dự án

### 4.1 Cột mới

`project_members.task_visibility` — varchar(16), không null, mặc định `own`. Enum `App\Enums\ProjectTaskVisibility`:

- `own` — Chỉ việc của mình
- `all` — Toàn bộ việc dự án

### 4.2 Quy tắc

- Thành viên vai trò `manager` luôn hiệu lực `all`, bất kể giá trị cột. Trưởng dự án phải thấy toàn bộ dự án mình quản lý — đây chính là điều người dùng nêu.
- Vai trò `member` và `viewer` dùng đúng giá trị cột, mặc định `own`.
- Đổi được ở form thêm thành viên và ở dòng thành viên trên trang chi tiết dự án, cùng chỗ với đổi vai trò. Chỉ người có `manageMembers` đổi được.
- Nâng một thành viên lên `manager` thì cột chuyển thành `all`; hạ khỏi `manager` thì giữ nguyên giá trị đang có.

### 4.3 Ảnh hưởng lên phạm vi công việc

Điều kiện thứ ba của `own` trong `Task::scopeVisibleTo` đổi từ:

> `project_id` thuộc các dự án tôi là thành viên

thành:

> `project_id` thuộc các dự án tôi là thành viên **với hiệu lực `all`**

Ba điều kiện còn lại (`assignee_id`, `creator_id`, việc con một cấp) không đổi. Vì việc được giao cho tôi vẫn luôn thấy, một thành viên `own` vẫn thấy đầy đủ phần việc của mình trong dự án.

### 4.4 Dữ liệu đang có

Migration đặt `all` cho mọi bản ghi vai trò `manager`, `own` cho phần còn lại. Người đang là thành viên thường sẽ hẹp tầm nhìn lại — đúng ý định.

## 5. Ba màn công việc

### 5.1 Định tuyến

Một controller, một trang Vue, ba đường dẫn. Đăng ký **trước** `Route::resource('tasks', ...)` để không bị `tasks/{task}` nuốt:

| Đường dẫn | Tên route | Bối cảnh |
| --- | --- | --- |
| `GET tasks` | `tasks.index` | `overview` |
| `GET tasks/projects` | `tasks.projects` | `project` |
| `GET tasks/departments` | `tasks.departments` | `department` |

Bối cảnh là hằng số do route truyền vào, không phải tham số người dùng gửi lên — không ai đổi được bối cảnh bằng query string.

### 5.2 Bối cảnh làm gì

| Bối cảnh | Tập dữ liệu | Bộ lọc hiển thị |
| --- | --- | --- |
| `overview` | Mọi việc trong phạm vi người xem | từ khoá, phòng ban, dự án, người phụ trách, tình trạng, ưu tiên |
| `project` | Chỉ việc có `project_id` | như trên |
| `department` | Chỉ việc có `organization_unit_id` | như trên |

Phạm vi dữ liệu vẫn do `Task::scopeVisibleTo` quyết định, không đổi theo bối cảnh. Bối cảnh chỉ thu hẹp thêm, không bao giờ mở rộng.

Nhờ vậy phần người dùng mô tả đã đúng sẵn: trưởng phòng giữ `task.view_department` nên thấy toàn bộ phòng mình và phòng con; ban lãnh đạo và quản trị giữ `task.view_all` nên thấy mọi phòng; nhân viên giữ `task.view_own` nên chỉ thấy việc cá nhân, và cấp thêm `task.view_department` cho ai cần xem cả phòng. Không cần cơ chế mới.

### 5.3 Bộ lọc theo ngữ cảnh nhúng

Danh sách việc nhúng trong trang khác bỏ bớt bộ lọc đã cố định:

- Trang chi tiết dự án: bỏ bộ lọc dự án.
- Trang chi tiết phòng ban (khi có): bỏ bộ lọc phòng ban.

Danh sách bộ lọc hiển thị là một prop, không phải điều kiện `v-if` rải rác trong template.

## 6. Bố cục ba khúc

### 6.1 Khúc 1 — Tóm tắt thống kê

Sáu ô số, không biểu đồ, không phần trăm:

| Ô | Đếm |
| --- | --- |
| Tổng đầu việc | tất cả việc sau khi áp phạm vi, bối cảnh và bộ lọc |
| Chưa làm | `draft` + `todo` |
| Đang làm | `in_progress` |
| Chờ duyệt | `waiting_review` + `waiting_approval` |
| Hoàn thành | `completed` |
| Trễ hạn | `due_at` đã qua và trạng thái không thuộc `completed`/`cancelled` |

Năm ô đầu loại trừ lẫn nhau, trừ `cancelled` không nằm ở ô nào — tổng năm ô có thể nhỏ hơn ô tổng, và đó là chủ ý. Ô trễ hạn cắt ngang các ô kia.

Kèm hai mốc thời gian của tập dữ liệu đang xem: ngày bắt đầu sớm nhất và hạn muộn nhất.

Thống kê tính bằng **một** truy vấn tổng hợp trên cùng điều kiện của danh sách, không phải sáu truy vấn đếm và cũng không đếm trên trang hiện tại.

### 6.2 Khúc 2 — Bộ lọc

Sáu bộ lọc: từ khoá, phòng ban, dự án, người phụ trách, tình trạng, ưu tiên.

Giao diện gọn: một hàng ngang thu gọn được, các ô chọn cùng chiều cao, nút "Áp dụng" và "Xoá lọc". Khi thu gọn vẫn hiện số bộ lọc đang bật. Không chiếm quá một phần tư chiều cao màn hình khi mở.

### 6.3 Khúc 3 — Danh sách chi tiết

Cột: tên việc, tình trạng, mức ưu tiên, người phụ trách, phòng ban, dự án, hạn, người tạo. Nhấn vào tên việc mở trang chi tiết. Phân trang 20.

Đánh dấu trực quan việc trễ hạn ngay trên dòng.

## 7. Thanh menu

Bốn mục công việc, theo đúng thứ tự người dùng nêu:

1. Tổng quan việc — `tasks.index`
2. Việc định kỳ — `task-recurrences.index`
3. Việc dự án — `tasks.projects`
4. Việc phòng ban — `tasks.departments`

Sau đó giữ nguyên các mục quản trị đang có: Dự án, Cơ cấu tổ chức, Người dùng, Nhật ký hệ thống, Phân quyền.

Mục "Tổng quan" cũ trỏ tới `/dashboard` bị thay bằng "Tổng quan việc". `/dashboard` chuyển hướng sang `tasks.index` để mọi liên kết cũ và luồng đăng nhập không gãy.

**Điểm cần xác nhận khi bàn giao:** người dùng liệt kê đúng bốn mục. Thiết kế này hiểu đó là bốn mục của nhóm công việc, còn các mục quản trị vẫn giữ. Nếu ý là menu chỉ còn đúng bốn mục thì phải bàn lại chỗ đặt các màn quản trị.

## 8. Kiểm thử

- `tests/Feature/Project/ProjectMemberTaskVisibilityTest.php` — mặc định `own`; `manager` luôn hiệu lực `all`; đổi giá trị đổi được tập việc nhìn thấy; nâng lên `manager` chuyển thành `all`; người không có `manageMembers` không đổi được.
- `tests/Feature/Task/TaskVisibilityTest.php` — cập nhật điều kiện thứ ba của `own`; thành viên `own` vẫn thấy việc được giao cho mình trong dự án.
- `tests/Feature/Task/TaskContextTest.php` — mỗi bối cảnh trả đúng tập dữ liệu; bối cảnh không đổi được bằng query string; bối cảnh chỉ thu hẹp chứ không mở rộng phạm vi.
- `tests/Feature/Task/TaskSummaryTest.php` — sáu ô đếm đúng cho từng trạng thái; ô trễ hạn cắt ngang; thống kê tính trên toàn tập đã lọc chứ không riêng trang hiện tại; `cancelled` không rơi vào ô nào.
- `tests/Feature/Task/TaskFilterTest.php` — từng bộ lọc, và bộ lọc kết hợp phạm vi không rò dòng ngoài phạm vi.
- Cập nhật `tests/Feature/DemoDataSeederTest.php` nếu seeder đặt `task_visibility`.
