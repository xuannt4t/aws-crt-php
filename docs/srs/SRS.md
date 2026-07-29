# Đặc tả yêu cầu phần mềm (SRS) — DORMIDA WORK

| | |
|---|---|
| **Sản phẩm** | DORMIDA WORK — Nền tảng quản trị công việc nội bộ doanh nghiệp |
| **Phiên bản tài liệu** | 1.1 |
| **Ngày phát hành** | 29/07/2026 |
| **Trạng thái dự án** | Đang triển khai — Sprint 2 (Task Core): đã xong tệp đính kèm, chỉ còn activity timeline hợp nhất |
| **Nguồn tổng hợp** | `README.md`, `prompts/*`, `context/*`, `docs/superpowers/specs/*` |

---

## 1. Giới thiệu

### 1.1 Mục đích

Tài liệu này đặc tả đầy đủ các yêu cầu chức năng và phi chức năng của hệ thống DORMIDA WORK. Tài liệu là cơ sở thống nhất giữa các bên: chủ đầu tư, đội phát triển, đội kiểm thử và đội vận hành — dùng để lập kế hoạch sprint, thiết kế kỹ thuật, viết test case và nghiệm thu.

### 1.2 Phạm vi sản phẩm

DORMIDA WORK là nền tảng web nội bộ dành cho doanh nghiệp có nhiều phòng ban và nhiều cấp quản lý. Hệ thống quản lý toàn bộ vòng đời công việc: từ mục tiêu, kế hoạch, dự án, nhiệm vụ, phân công, theo dõi tiến độ, phê duyệt, cho đến báo cáo và đánh giá hiệu suất.

Hệ thống **không** phải là sản phẩm SaaS đa khách hàng (multi-tenant), không có đăng ký công khai. Tài khoản do quản trị viên hệ thống tạo và quản lý.

### 1.3 Định nghĩa và từ viết tắt

| Thuật ngữ | Giải thích |
|---|---|
| Organization Unit | Đơn vị tổ chức (công ty, khối, phòng, ban, tổ) — cấu trúc cây nhiều cấp |
| Đơn vị chính | Đơn vị tổ chức mà một người dùng trực thuộc |
| Task | Công việc / nhiệm vụ — thực thể nghiệp vụ trung tâm |
| Assignee | Người phụ trách chính của công việc |
| Watcher | Người theo dõi công việc, không chịu trách nhiệm thực thi |
| Permission | Quyền cấp chức năng (ví dụ `task.update`) |
| Policy | Lớp quyết định người dùng có được thao tác trên một bản ghi cụ thể hay không |
| Role | Tập hợp permission được đặt tên |
| Audit Log | Nhật ký thao tác nhạy cảm, phục vụ truy vết |
| Soft delete | Xoá mềm — đánh dấu `deleted_at`, không xoá vật lý |
| SRS | Software Requirements Specification |
| DoD | Definition of Done — định nghĩa hoàn thành |

### 1.4 Tài liệu tham chiếu

| Tài liệu | Nội dung |
|---|---|
| `prompts/01_MASTER_PROMPT.md` | Bối cảnh sản phẩm, stack bắt buộc, chuẩn chất lượng |
| `prompts/02_ARCHITECTURE.md` | Kiến trúc modular monolith, phân lớp, transaction, cache, audit |
| `prompts/03_DATABASE.md` | Quy ước database, index, soft delete, history |
| `prompts/05_FRONTEND.md`, `prompts/06_UI_UX.md` | Chuẩn giao diện và trải nghiệm |
| `prompts/08_TESTING.md`, `prompts/09_DEVOPS.md` | Chuẩn kiểm thử và vận hành |
| `context/business-rules.md` | Quy tắc nghiệp vụ gốc |
| `context/permission-matrix.md` | Danh mục permission và nguyên tắc phạm vi |
| `context/task-flow.md` | Máy trạng thái công việc |
| `context/module-dependencies.md` | Thứ tự phụ thuộc giữa các module |
| `context/sprint-plan.md` | Lộ trình triển khai theo sprint |

### 1.5 Quy ước đánh mã yêu cầu

- `FR-<MODULE>-<nn>` — yêu cầu chức năng.
- `BR-<nn>` — quy tắc nghiệp vụ.
- `NFR-<nn>` — yêu cầu phi chức năng.

Cột **Trạng thái** trong các bảng yêu cầu: `Đã triển khai` / `Đang triển khai` / `Kế hoạch`.

---

## 2. Mô tả tổng quan

### 2.1 Bối cảnh sản phẩm

Hệ thống là một ứng dụng web đơn khối theo kiến trúc **modular monolith** trên Laravel 11, giao diện Vue 3 + Inertia.js (SPA-like, không tách REST API riêng cho web UI). Hệ thống chạy trong hạ tầng nội bộ hoặc VPS của doanh nghiệp, kết nối MySQL, Redis và object storage tương thích S3.

### 2.2 Nhóm chức năng chính

1. **Nền tảng**: Authentication, User, Organization, Role & Permission, Audit Log, System Settings.
2. **Nghiệp vụ cốt lõi**: Objectives, Plans, Projects, Tasks, Calendar, Approval, Notifications, Reports, Reviews, Dashboard.

### 2.3 Nhóm người dùng (Actor)

| Actor | Mô tả | Đặc trưng quyền |
|---|---|---|
| `system_admin` | Quản trị hệ thống | Toàn quyền cấu hình, người dùng, tổ chức, phân quyền, xem audit log |
| `director` | Ban giám đốc | Xem toàn bộ dữ liệu, phê duyệt cấp cao, xem báo cáo toàn công ty |
| `department_manager` | Trưởng đơn vị | Quản lý công việc và nhân sự trong phạm vi đơn vị quản lý |
| `project_manager` | Quản lý dự án | Quản lý dự án, thành viên dự án và công việc thuộc dự án |
| `employee` | Nhân viên | Thực hiện công việc được giao, cập nhật tiến độ, bình luận |
| `auditor` | Kiểm soát nội bộ | Chỉ đọc, bao gồm audit log; không được sửa dữ liệu |

Role chỉ là tập hợp permission. Nghiệp vụ **không** được hard-code tên role khi permission đã giải quyết được (BR-19).

### 2.4 Môi trường vận hành

| Thành phần | Yêu cầu |
|---|---|
| Backend | PHP 8.3+, Laravel 11 |
| Database | MySQL 8, charset `utf8mb4`, collation thống nhất |
| Cache / Queue / Session | Redis |
| Realtime | Laravel Reverb (WebSocket) — process riêng, reverse proxy hỗ trợ WebSocket |
| Lưu trữ tệp | Cloudflare R2 hoặc S3-compatible |
| Trình duyệt | Các trình duyệt hiện đại bản mới (Chrome, Edge, Firefox, Safari); hỗ trợ desktop, tablet, mobile |
| Môi trường | Tối thiểu `local`, `staging`, `production` — không dùng chung database, Redis prefix hay storage bucket |

### 2.5 Ràng buộc thiết kế và triển khai

- Luồng xử lý mặc định: `Route → Controller → FormRequest → Service/Action → Repository (khi cần) → Eloquent Model → Inertia Response`.
- Controller không chứa query phức tạp, business rule hay side effect nặng.
- Chỉ tạo Repository khi query phức tạp hoặc dùng lại nhiều nơi; không bọc `Model::find()` vô nghĩa.
- Mọi thay đổi cấu trúc database phải qua migration có rollback hợp lệ.
- Mọi danh sách lớn phải phân trang phía server.
- Mọi thao tác nhạy cảm phải ghi audit log.
- Không viết mock/placeholder vào production code; không để TODO không giải thích.
- Không tự đổi tên cột, route hoặc response đang tồn tại.

### 2.6 Giả định và phụ thuộc

- Mỗi người dùng thuộc đúng **một** đơn vị chính; việc tham gia nhiều dự án được mô hình hoá ở module Project, không phải ở trường đơn vị chính.
- Mọi timestamp lưu theo UTC, chuyển timezone ở lớp hiển thị.
- Doanh nghiệp tự cung cấp hạ tầng mail cho tính năng quên mật khẩu / thông báo email (chưa cấu hình ở giai đoạn hiện tại).
- Module cấp cao phụ thuộc module nền tảng theo `context/module-dependencies.md`: Task và Project phụ thuộc User + Organization + Permission; Approval phụ thuộc Task/Project + Permission; Report phụ thuộc Task/Project/Approval; Dashboard phụ thuộc Report; Realtime phụ thuộc Notification và authorized channel.

---

## 3. Yêu cầu chức năng

### 3.1 Authentication (AUTH)

| Mã | Yêu cầu | Trạng thái |
|---|---|---|
| FR-AUTH-01 | Người dùng đăng nhập bằng email và mật khẩu. | Đã triển khai |
| FR-AUTH-02 | Hệ thống **không** cho đăng ký công khai; route `register` phải trả 404. Tài khoản do `system_admin` tạo. | Đã triển khai |
| FR-AUTH-03 | Người dùng khôi phục mật khẩu qua email (quên mật khẩu). | Đã triển khai |
| FR-AUTH-04 | Người dùng đổi mật khẩu và cập nhật hồ sơ cá nhân (kèm ảnh đại diện) ở trang Profile. | Đã triển khai |
| FR-AUTH-05 | Người dùng bị vô hiệu hoá (`is_active = false`) không đăng nhập được, nhưng bản ghi và các quan hệ vẫn giữ nguyên. | Đã triển khai |
| FR-AUTH-06 | Đăng nhập thất bại bất thường phải được ghi nhận phục vụ giám sát. | Kế hoạch |

### 3.2 Organization (ORG)

| Mã | Yêu cầu | Trạng thái |
|---|---|---|
| FR-ORG-01 | Quản lý cây đơn vị tổ chức nhiều cấp, không giới hạn số cấp cứng (tự tham chiếu qua `parent_id`). | Đã triển khai |
| FR-ORG-02 | Tạo, sửa, xoá mềm đơn vị; mỗi đơn vị có `name`, `code` (duy nhất), `is_active`. | Đã triển khai |
| FR-ORG-03 | Hệ thống chặn việc một đơn vị trở thành cha/tổ tiên của chính nó khi cập nhật `parent_id`. | Đã triển khai |
| FR-ORG-04 | Hệ thống chặn xoá đơn vị còn đơn vị con hoặc còn người dùng đang gán vào. | Đã triển khai |
| FR-ORG-05 | Mọi người dùng đã đăng nhập được xem cây tổ chức; chỉ người có quyền quản trị mới tạo/sửa/xoá. | Đã triển khai |

### 3.3 User (USER)

| Mã | Yêu cầu | Trạng thái |
|---|---|---|
| FR-USER-01 | Quản trị viên tạo, sửa người dùng với các trường: họ tên, email, mật khẩu ban đầu, đơn vị chính, mã nhân viên, điện thoại, chức danh. | Đã triển khai |
| FR-USER-02 | Đơn vị chính là **bắt buộc** khi tạo/sửa người dùng qua form (nullable ở tầng DB để tránh phụ thuộc vòng khi khởi tạo dữ liệu nền). | Đã triển khai |
| FR-USER-03 | Email và mã nhân viên là duy nhất trong hệ thống. | Đã triển khai |
| FR-USER-04 | Quản trị viên vô hiệu hoá / kích hoạt lại người dùng (`user.disable`) — khác với xoá mềm. | Đã triển khai |
| FR-USER-05 | Danh sách người dùng hỗ trợ tìm kiếm, lọc theo đơn vị tổ chức và phân trang phía server. | Đã triển khai |
| FR-USER-06 | Quản trị viên gán role cho người dùng (`user.assign_role`). | Đã triển khai |
| FR-USER-07 | Gửi email mời / xác thực khi tạo tài khoản mới. | Kế hoạch |

### 3.4 Role & Permission (PERM)

| Mã | Yêu cầu | Trạng thái |
|---|---|---|
| FR-PERM-01 | Hệ thống quản lý role và permission dựa trên Spatie Laravel Permission. | Đã triển khai |
| FR-PERM-02 | Danh mục permission chuẩn được định nghĩa tập trung bằng PHP enum, không rải chuỗi tự do trong code. | Đã triển khai |
| FR-PERM-03 | Kiểm tra quyền cấp chức năng bằng permission middleware; kiểm tra quyền trên bản ghi cụ thể bằng Policy. | Đã triển khai |
| FR-PERM-04 | Một người dùng có thể mang nhiều role; quyền hiệu lực là hợp của các permission. | Đã triển khai |
| FR-PERM-05 | Giao diện quản trị role/permission cho `system_admin`. | Kế hoạch |

### 3.5 Audit Log (AUDIT)

| Mã | Yêu cầu | Trạng thái |
|---|---|---|
| FR-AUDIT-01 | Hệ thống ghi nhật ký cho các thao tác nhạy cảm: thay đổi role/permission, thay đổi trạng thái phê duyệt, xoá dữ liệu, export dữ liệu nhạy cảm, thay đổi cấu hình, chuyển người phụ trách, thay đổi deadline quan trọng, đăng nhập thất bại bất thường. | Đã triển khai |
| FR-AUDIT-02 | Mỗi bản ghi audit lưu: người thực hiện, hành động, đối tượng tác động, thời điểm và metadata cần thiết. | Đã triển khai |
| FR-AUDIT-03 | Bảng audit là append-only — không sửa, không xoá mềm. | Đã triển khai |
| FR-AUDIT-04 | Chỉ người có `system.view_audit_logs` được xem nhật ký. | Đã triển khai |

### 3.6 Task (TASK)

| Mã | Yêu cầu | Trạng thái |
|---|---|---|
| FR-TASK-01 | Tạo công việc với tối thiểu: tiêu đề, người tạo, đơn vị sở hữu, trạng thái, mức ưu tiên; thời hạn khi nghiệp vụ yêu cầu. | Đã triển khai |
| FR-TASK-02 | Công việc có thể có mô tả, công việc cha, người phụ trách chính, tiến độ (0–100), thời hạn và thời điểm hoàn thành. | Đã triển khai |
| FR-TASK-03 | Danh sách công việc hỗ trợ lọc theo trạng thái, mức ưu tiên, người phụ trách, đơn vị, thời hạn; sắp xếp và phân trang phía server. | Đã triển khai |
| FR-TASK-04 | Giao / đổi người phụ trách chính (`task.assign`); giao diện thể hiện rõ người đang phụ trách. | Đã triển khai |
| FR-TASK-05 | Người tạo có thể tự nhận việc về mình. | Đã triển khai |
| FR-TASK-06 | Người phụ trách cập nhật tiến độ 0–100 mà không nhất thiết được đổi người phụ trách. | Đã triển khai |
| FR-TASK-07 | Chuyển trạng thái theo máy trạng thái ở mục 5; mỗi lần chuyển ghi lịch sử (`task_status_histories`). | Đã triển khai |
| FR-TASK-08 | Người phụ trách có thể thu hồi yêu cầu kiểm tra (`waiting_review → in_progress`). | Đã triển khai |
| FR-TASK-09 | Bình luận trên công việc, có phân trang. | Đã triển khai |
| FR-TASK-10 | Đính kèm tệp vào công việc, kiểm tra MIME, dung lượng và quyền truy cập khi tải xuống. | Đã triển khai |
| FR-TASK-11 | Timeline hoạt động hợp nhất (đổi trạng thái, phân công, bình luận, tệp) trên trang chi tiết. | Đang triển khai |
| FR-TASK-12 | Nhiều người phối hợp và người theo dõi trên một công việc. | Kế hoạch |
| FR-TASK-13 | Checklist trong công việc; hoàn thành công việc có thể yêu cầu checklist hoàn tất. | Kế hoạch |
| FR-TASK-14 | Tiến độ công việc cha tính từ công việc con hoặc nhập thủ công tuỳ cấu hình. | Kế hoạch |
| FR-TASK-15 | Export danh sách công việc (`task.export`) theo phạm vi quyền, có audit; export lớn chạy qua queue. | Kế hoạch |

### 3.7 Project (PROJ)

| Mã | Yêu cầu | Trạng thái |
|---|---|---|
| FR-PROJ-01 | Tạo, sửa, xoá mềm dự án; mỗi dự án có owner. | Kế hoạch |
| FR-PROJ-02 | Quản lý thành viên dự án, mỗi thành viên có vai trò trong dự án. | Kế hoạch |
| FR-PROJ-03 | Gắn công việc vào dự án; xem công việc theo dự án. | Kế hoạch |
| FR-PROJ-04 | Theo dõi tiến độ dự án tổng hợp từ công việc thành phần. | Kế hoạch |
| FR-PROJ-05 | Đóng dự án (`project.close`) phải kiểm tra công việc còn mở, hoặc ghi nhận ngoại lệ có lý do. | Kế hoạch |
| FR-PROJ-06 | Quyền trong dự án không thay thế hoàn toàn permission hệ thống. | Kế hoạch |

### 3.8 Approval (APPR)

| Mã | Yêu cầu | Trạng thái |
|---|---|---|
| FR-APPR-01 | Luồng phê duyệt hỗ trợ một hoặc nhiều bước. | Kế hoạch |
| FR-APPR-02 | Người tạo không mặc định được tự phê duyệt. | Kế hoạch |
| FR-APPR-03 | Mỗi quyết định lưu người thực hiện, thời điểm và ghi chú vào bảng lịch sử. | Kế hoạch |
| FR-APPR-04 | Từ chối hoặc yêu cầu chỉnh sửa bắt buộc có lý do. | Kế hoạch |
| FR-APPR-05 | Nội dung đã duyệt không được sửa nếu chưa mở lại hoặc chưa tạo phiên bản mới. | Kế hoạch |

### 3.9 Notification & Realtime (NOTI)

| Mã | Yêu cầu | Trạng thái |
|---|---|---|
| FR-NOTI-01 | Thông báo trong ứng dụng cho: được phân công, bình luận mới, trạng thái phê duyệt thay đổi, tiến độ dự án thay đổi, sắp/quá hạn. | Kế hoạch |
| FR-NOTI-02 | Đẩy thông báo realtime qua Laravel Reverb trên channel đã được authorize. | Kế hoạch |
| FR-NOTI-03 | Không broadcast dữ liệu nhạy cảm hoặc payload quá lớn. | Kế hoạch |
| FR-NOTI-04 | Người dùng đánh dấu đã đọc / đã đọc tất cả. | Kế hoạch |

### 3.10 Calendar & Planning (CAL)

| Mã | Yêu cầu | Trạng thái |
|---|---|---|
| FR-CAL-01 | Lịch hiển thị công việc theo thời hạn, phạm vi cá nhân / đơn vị / dự án. | Kế hoạch |
| FR-CAL-02 | Quản lý mục tiêu (Objective) theo tổ chức, phòng ban và cá nhân. | Kế hoạch |
| FR-CAL-03 | Quản lý kế hoạch (Plan) và liên kết kế hoạch với công việc, mục tiêu. | Kế hoạch |
| FR-CAL-04 | Nhắc hạn tự động qua scheduler + queue. | Kế hoạch |

### 3.11 Report & Dashboard (REP)

| Mã | Yêu cầu | Trạng thái |
|---|---|---|
| FR-REP-01 | Dashboard theo quyền: mỗi card trả lời một câu hỏi cụ thể, hiển thị thời điểm dữ liệu được cập nhật. | Kế hoạch |
| FR-REP-02 | Báo cáo tiến độ và hiệu suất theo phạm vi `report.view_own` / `view_department` / `view_all`. | Kế hoạch |
| FR-REP-03 | Báo cáo ghi rõ thời điểm chốt dữ liệu. | Kế hoạch |
| FR-REP-04 | Export báo cáo (Excel/PDF) có phân quyền và ghi audit; export lớn chạy nền qua queue. | Kế hoạch |
| FR-REP-05 | Đánh giá hiệu suất định kỳ (Reviews) dựa trên dữ liệu công việc. | Kế hoạch |

### 3.12 System Settings (SYS)

| Mã | Yêu cầu | Trạng thái |
|---|---|---|
| FR-SYS-01 | Quản trị viên cấu hình tham số hệ thống (`system.manage_settings`). | Kế hoạch |
| FR-SYS-02 | Mọi thay đổi cấu hình phải ghi audit log. | Kế hoạch |
| FR-SYS-03 | Cấu hình đọc nhiều, thay đổi ít được cache kèm chiến lược invalidation rõ ràng. | Kế hoạch |

---

## 4. Quy tắc nghiệp vụ

### 4.1 Tổ chức và người dùng

| Mã | Quy tắc |
|---|---|
| BR-01 | Tổ chức có thể có nhiều cấp. |
| BR-02 | Mỗi người dùng có đúng một đơn vị chính. |
| BR-03 | Một người có thể tham gia nhiều dự án ngoài đơn vị chính. |
| BR-04 | Quyền xem dữ liệu phụ thuộc role, permission, đơn vị và quan hệ với bản ghi. |

### 4.2 Công việc và tiến độ

| Mã | Quy tắc |
|---|---|
| BR-05 | Công việc bắt buộc có: tiêu đề, người tạo, đơn vị/phạm vi sở hữu, trạng thái, mức ưu tiên; thời hạn khi nghiệp vụ yêu cầu. |
| BR-06 | Tiến độ nằm trong khoảng 0–100. |
| BR-07 | Công việc hoàn thành phải đạt điều kiện hoàn thành đã xác định. |
| BR-08 | Tiến độ công việc cha có thể tính từ công việc con hoặc nhập thủ công tuỳ cấu hình. |
| BR-09 | Không tự động đổi trạng thái khi chưa có rule rõ ràng. |

### 4.3 Deadline

| Mã | Quy tắc |
|---|---|
| BR-10 | Công việc quá hạn khi chưa hoàn thành và thời hạn nhỏ hơn thời điểm hiện tại. `overdue` là trạng thái suy diễn, không lưu trực tiếp. |
| BR-11 | Mọi lần gia hạn phải lưu lịch sử. |
| BR-12 | Thay đổi deadline quan trọng phải có lý do khi chính sách yêu cầu. |

### 4.4 Phê duyệt

| Mã | Quy tắc |
|---|---|
| BR-13 | Người tạo không mặc định được tự phê duyệt. |
| BR-14 | Mỗi quyết định phê duyệt lưu người thực hiện, thời gian và ghi chú. |
| BR-15 | Từ chối hoặc yêu cầu chỉnh sửa phải có lý do. |
| BR-16 | Không sửa nội dung đã duyệt nếu chưa mở lại hoặc chưa tạo phiên bản mới. |

### 4.5 Dự án và báo cáo

| Mã | Quy tắc |
|---|---|
| BR-17 | Hoàn thành dự án phải kiểm tra công việc còn mở hoặc ghi nhận ngoại lệ. |
| BR-18 | Số liệu báo cáo chỉ hiển thị trong phạm vi người dùng được phép xem; export dữ liệu nhạy cảm phải được phân quyền và audit. |

### 4.6 Nguyên tắc phân quyền

| Mã | Quy tắc |
|---|---|
| BR-19 | Role chỉ là tập permission; không hard-code tên role trong nghiệp vụ khi permission giải quyết được. |
| BR-20 | Permission cho phép hành động; Policy quyết định hành động đó có hợp lệ trên bản ghi cụ thể hay không. Ví dụ: có `task.update` không đồng nghĩa được sửa mọi task. |
| BR-21 | `auditor` chỉ đọc, không được sửa bất kỳ dữ liệu nghiệp vụ nào. |

---

## 5. Máy trạng thái công việc

### 5.1 Tập trạng thái

```text
draft
todo
in_progress
waiting_review
waiting_approval
completed
cancelled
```

`overdue` **không** là trạng thái lưu trữ — được suy diễn từ `due_at` và trạng thái hiện tại.

### 5.2 Bảng chuyển trạng thái

| Từ | Đến | Điều kiện |
|---|---|---|
| `draft` | `todo` | Công việc đã đủ thông tin để giao |
| `todo` | `in_progress` | Người phụ trách bắt đầu thực hiện |
| `in_progress` | `waiting_review` | Gửi kiểm tra |
| `waiting_review` | `in_progress` | Người kiểm tra yêu cầu chỉnh sửa, hoặc người phụ trách thu hồi yêu cầu kiểm tra |
| `waiting_review` | `waiting_approval` | Đã qua bước review (nếu luồng có bước phê duyệt) |
| `waiting_approval` | `completed` | Được phê duyệt |
| `waiting_approval` | `in_progress` | Bị từ chối hoặc yêu cầu chỉnh sửa |
| Mọi trạng thái đang hoạt động | `cancelled` | Có quyền và **bắt buộc** nhập lý do |

### 5.3 Quy tắc thực thi

- Backend là nơi duy nhất kiểm tra tính hợp lệ của transition; frontend chỉ hiển thị action hợp lệ.
- Mỗi lần chuyển trạng thái ghi một bản ghi lịch sử: trạng thái cũ, trạng thái mới, người thực hiện, thời điểm, lý do.
- Việc chuyển trạng thái kèm ghi lịch sử phải nằm trong cùng một transaction.

---

## 6. Ma trận phân quyền

### 6.1 Danh mục permission

| Nhóm | Permission |
|---|---|
| Organization | `organization.view`, `organization.create`, `organization.update`, `organization.delete`, `organization.manage_members` |
| User | `user.view`, `user.create`, `user.update`, `user.disable`, `user.assign_role` |
| Task | `task.view`, `task.create`, `task.update`, `task.delete`, `task.assign`, `task.comment`, `task.submit`, `task.approve`, `task.reject`, `task.export` |
| Project | `project.view`, `project.create`, `project.update`, `project.delete`, `project.manage_members`, `project.close`, `project.export` |
| Report | `report.view_own`, `report.view_department`, `report.view_all`, `report.export` |
| System | `system.manage_settings`, `system.view_audit_logs` |

### 6.2 Nguyên tắc phạm vi

Permission trả lời câu hỏi *"người này có được phép làm hành động X không?"*. Policy trả lời câu hỏi *"người này có được làm hành động X trên bản ghi cụ thể này không?"*.

Ví dụ áp dụng:

- Trưởng đơn vị có `task.update` chỉ sửa được công việc thuộc đơn vị mình quản lý.
- Người phụ trách cập nhật được tiến độ nhưng không đương nhiên đổi được người phụ trách.
- `auditor` có `task.view` và `system.view_audit_logs` nhưng mọi Policy ghi đều từ chối.

---

## 7. Yêu cầu giao diện

### 7.1 Giao diện người dùng

- Desktop: sidebar điều hướng, topbar, breadcrumb, vùng nội dung có max-width phù hợp, drawer/panel phụ cho chi tiết nhanh.
- Mobile: sidebar thành drawer, action chính dễ tiếp cận, bảng chuyển sang card hoặc horizontal scroll có kiểm soát, filter đưa vào drawer.
- Mỗi màn hình dữ liệu bắt buộc có đủ: **loading**, **empty**, **error**, **permission denied**, **success feedback**, **disabled state**.
- Màu trạng thái dùng token thống nhất cho draft / todo / in progress / waiting review / waiting approval / completed / rejected / overdue / cancelled — không hard-code màu ở từng trang.
- Form: nhóm trường liên quan, đánh dấu bắt buộc rõ ràng, lỗi hiển thị gần field, không dùng placeholder thay label, form dài chia section hoặc step.
- DataTable: header cố định khi bảng dài, cột action ở vị trí nhất quán, cột phụ ẩn được trên mobile, empty state gợi ý hành động tiếp theo.
- Action bị cấm theo quyền phải được ẩn hoặc vô hiệu hoá ở UI, đồng thời luôn được chặn ở backend.

### 7.2 Giao diện phần mềm ngoài

| Hệ thống ngoài | Mục đích | Yêu cầu |
|---|---|---|
| Object storage (S3/R2) | Lưu tệp đính kèm và ảnh đại diện | Truy cập qua adapter riêng, URL có thời hạn, kiểm tra quyền trước khi cấp |
| SMTP / mail service | Quên mật khẩu, thông báo email | Gửi qua queue, không chặn request |
| Reverb WebSocket | Realtime | Channel authorize bắt buộc |

Mọi tích hợp ngoài phải đi qua adapter/service riêng, có timeout, retry giới hạn, idempotency khi cần, log trace ID và **không** log secret.

---

## 8. Yêu cầu phi chức năng

### 8.1 Hiệu năng

| Mã | Yêu cầu |
|---|---|
| NFR-01 | Mọi danh sách lớn phân trang phía server; không tải toàn bộ bảng về client. |
| NFR-02 | Không có truy vấn N+1 rõ ràng ở các màn hình danh sách và chi tiết; dùng eager loading tường minh. |
| NFR-03 | Không query trong vòng lặp; dữ liệu lớn xử lý bằng chunk/cursor. |
| NFR-04 | Truy vấn nặng chỉ select cột cần thiết và được xác nhận bằng `EXPLAIN`. |
| NFR-05 | Index bắt buộc cho foreign key và các cột thường filter/sort: `status`, `assignee_id`, `organization_unit_id`, `project_id`, `due_at`, `created_at`. |
| NFR-06 | Export lớn, gửi email hàng loạt, tạo báo cáo và xử lý tệp phải chạy nền qua queue. |
| NFR-07 | Cache chỉ áp dụng khi dữ liệu đọc nhiều, thay đổi ít và có chiến lược invalidation rõ ràng (cấu hình hệ thống, permission map, organization tree, dashboard aggregate). |

### 8.2 Bảo mật

| Mã | Yêu cầu |
|---|---|
| NFR-08 | Không có endpoint nào bỏ qua authorization; mọi hành động trên tài nguyên đều đi qua Policy hoặc permission middleware. |
| NFR-09 | Không tin dữ liệu từ frontend; mọi input được validate ở FormRequest. |
| NFR-10 | Bảo vệ mass assignment trên toàn bộ model. |
| NFR-11 | Upload phải kiểm tra MIME, dung lượng và quyền truy cập khi tải xuống. |
| NFR-12 | Không log token, mật khẩu hay dữ liệu bí mật; production không trả stack trace cho người dùng cuối. |
| NFR-13 | Thao tác nhạy cảm (phân quyền, xoá, phê duyệt, export, đổi cấu hình) bắt buộc ghi audit log. |
| NFR-14 | Không commit `.env`; secret lưu ở secret manager hoặc CI/CD variables. |

### 8.3 Toàn vẹn dữ liệu

| Mã | Yêu cầu |
|---|---|
| NFR-15 | Transaction bắt buộc khi: tạo bản ghi chính kèm bản ghi liên quan, đổi trạng thái kèm lịch sử, phê duyệt tác động nhiều bảng, xoá/khôi phục nhiều thực thể, cập nhật tiến độ cha từ nhiều con. |
| NFR-16 | Không đặt thao tác mạng chậm bên trong transaction. |
| NFR-17 | Soft delete áp dụng cho User, Organization unit, Project, Task và danh mục nghiệp vụ; **không** áp dụng cho bảng log append-only, bảng history bất biến, pivot tái tạo được và dữ liệu tạm. |
| NFR-18 | Timestamp lưu theo UTC, chuyển timezone ở lớp hiển thị; deadline phải xác định rõ có bao gồm thời điểm cuối hay không. |
| NFR-19 | Event có side effect phụ thuộc dữ liệu đã ghi phải dispatch sau commit. |

### 8.4 Khả năng bảo trì và chất lượng mã

| Mã | Yêu cầu |
|---|---|
| NFR-20 | Backend tuân thủ PSR-12 (kiểm tra bằng Laravel Pint); frontend tuân thủ ESLint + Prettier. |
| NFR-21 | Có type hint và return type; dùng PHP backed enum cho trạng thái ổn định; tránh magic number và magic string. |
| NFR-22 | Không tạo abstraction khi chưa có nhu cầu thực tế; không để lại code chết. |
| NFR-23 | Mọi chức năng quan trọng có test cho luồng chính và luồng lỗi (Pest). CI chạy Pint, ESLint, build frontend và toàn bộ test trên mỗi push/PR vào `main`. |

### 8.5 Vận hành

| Mã | Yêu cầu |
|---|---|
| NFR-24 | Queue chạy bằng Supervisor/systemd, có timeout, tries, backoff và theo dõi failed job; job idempotent khi có thể. |
| NFR-25 | Scheduler dùng `withoutOverlapping` và `onOneServer` khi cần, ghi log lỗi. |
| NFR-26 | Backup định kỳ database và metadata storage, có kiểm tra restore định kỳ. |
| NFR-27 | Giám sát: HTTP 5xx, response time, slow query, queue delay, failed job, Redis memory, disk, kết nối Reverb, đăng nhập thất bại bất thường. |
| NFR-28 | Mỗi release phải xác định được: khả năng rollback code, tính tương thích ngược của migration, feature flag và nhu cầu backfill. |

### 8.6 Khả dụng và trải nghiệm

| Mã | Yêu cầu |
|---|---|
| NFR-29 | Giao diện responsive trên desktop, tablet và mobile. |
| NFR-30 | Đáp ứng tiêu chuẩn accessibility cơ bản: tương phản đủ, điều hướng bàn phím, heading không nhảy cấp tuỳ tiện. |
| NFR-31 | Hỗ trợ PWA (Sprint 7). |
| NFR-32 | Toàn bộ nội dung giao diện dùng tiếng Việt, mã hoá `utf8mb4`. |

---

## 9. Kiến trúc và ràng buộc kỹ thuật

### 9.1 Kiểu kiến trúc

Modular monolith trên Laravel 11. Mục tiêu: triển khai nhanh, giữ domain rõ ràng, không chia microservice quá sớm, vẫn cho phép tách service về sau khi có nhu cầu thực tế.

### 9.2 Phân lớp backend

```text
app/
├── Actions/        Use case nhỏ, độc lập, input/output rõ ràng
├── DTO/            Dữ liệu truyền qua nhiều lớp
├── Enums/          Trạng thái, permission, role, audit action
├── Events/         TaskAssigned, TaskSubmittedForApproval, ApprovalRejected, ...
├── Http/
│   ├── Controllers/  Nhận request → authorize → gọi service/action → trả response
│   ├── Middleware/
│   ├── Requests/     Validation
│   └── Resources/
├── Jobs/           Export, import, mail hàng loạt, báo cáo, xử lý tệp
├── Listeners/      Notification, activity, đồng bộ dashboard, broadcast
├── Models/
├── Policies/       Authorization theo tài nguyên
├── Repositories/   Chỉ khi query phức tạp hoặc dùng lại
└── Services/       Use case nhiều bước, có transaction
```

### 9.3 Frontend

`resources/js/{Components,Layouts,Pages}` — Vue 3 Composition API + TypeScript + Inertia.js, Tailwind CSS và PrimeVue. Component dùng chung chỉ tạo khi có ít nhất hai nơi sử dụng. Thư viện bổ sung (VueUse, FullCalendar, VueDraggable, ApexCharts) được thêm đúng sprint cần dùng, không cài trước.

### 9.4 Quy ước database

- Tên bảng số nhiều snake_case; khoá ngoại `{model}_id`; boolean bắt đầu bằng `is_`/`has_`/`can_`; thời điểm kết thúc `_at`; ngày kết thúc `_date`.
- Khoá chính mặc định `bigint unsigned auto increment`; chỉ dùng UUID/ULID khi ID lộ ra ngoài cần khó đoán hoặc có đồng bộ phân tán.
- Trạng thái lưu bằng `varchar` ngắn ánh xạ sang PHP backed enum, không dùng MySQL ENUM.
- Không mặc định cascade delete; chọn `restrict` / `set null` / `cascade` theo nghiệp vụ.

---

## 10. Từ điển dữ liệu (các bảng đã triển khai)

### 10.1 `organization_units`

| Cột | Kiểu | Ghi chú |
|---|---|---|
| `id` | bigint unsigned | PK |
| `parent_id` | bigint unsigned, nullable | FK → `organization_units.id`, có index |
| `name` | varchar | Tên đơn vị |
| `code` | varchar | Duy nhất |
| `is_active` | boolean | Mặc định `true` |
| `created_at` / `updated_at` / `deleted_at` | timestamp | Soft delete |

### 10.2 `users`

| Cột | Kiểu | Ghi chú |
|---|---|---|
| `id` | bigint unsigned | PK |
| `name`, `email`, `password`, `email_verified_at` | — | Từ scaffold Breeze; `email` duy nhất |
| `organization_unit_id` | bigint unsigned, nullable | FK → `organization_units.id`; bắt buộc ở tầng form |
| `is_system_admin` | boolean | Mặc định `false` |
| `is_active` | boolean | Mặc định `true`; `false` = bị vô hiệu hoá, chặn đăng nhập |
| `employee_code` | varchar, nullable | Duy nhất |
| `phone`, `job_title` | varchar, nullable | |
| `avatar_path` | varchar, nullable | Ảnh đại diện |
| `created_at` / `updated_at` / `deleted_at` | timestamp | Soft delete |

### 10.3 `tasks`

| Cột | Kiểu | Ghi chú |
|---|---|---|
| `id` | bigint unsigned | PK |
| `organization_unit_id` | bigint unsigned | FK, `restrict on delete` |
| `parent_id` | bigint unsigned, nullable | FK → `tasks.id`, `null on delete` |
| `creator_id` | bigint unsigned | FK → `users.id`, `restrict on delete` |
| `assignee_id` | bigint unsigned, nullable | FK → `users.id`, `null on delete` |
| `title` | varchar | Bắt buộc |
| `description` | text, nullable | |
| `status` | varchar(30) | Ánh xạ enum `TaskStatus` |
| `priority` | varchar(20) | Ánh xạ enum `TaskPriority` |
| `progress` | tinyint unsigned | 0–100, mặc định 0 |
| `due_at`, `completed_at` | datetime, nullable | |
| `created_at` / `updated_at` / `deleted_at` | timestamp | Soft delete |

Index: `(organization_unit_id, status)`, `(assignee_id, status)`, `(status, due_at)`.

### 10.4 Bảng phụ trợ

| Bảng | Vai trò |
|---|---|
| `task_status_histories` | Lịch sử chuyển trạng thái: trạng thái cũ, mới, người thực hiện, thời điểm, lý do — bất biến |
| `task_comments` | Bình luận công việc, hỗ trợ phân trang |
| `audit_logs` | Nhật ký thao tác nhạy cảm — append-only, không soft delete |
| `task_attachments` | Tệp đính kèm của công việc: disk, path, tên gốc, MIME, dung lượng, người tải lên — xoá mềm |
| Bảng của Spatie Permission | `roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions` |

---

## 11. Lộ trình triển khai

| Sprint | Nội dung | Trạng thái |
|---|---|---|
| Sprint 0 — Foundation | Laravel 11, Vue 3 + Inertia + TypeScript, Tailwind + PrimeVue, MySQL + Redis, CI cơ bản, quy chuẩn code | Hoàn tất |
| Sprint 1 — Identity | Authentication, User, Organization, Role & Permission, Audit log nền tảng | Hoàn tất |
| Sprint 2 — Task Core | Task CRUD, assignment, status flow, comment, attachment, activity timeline | Đang triển khai |
| Sprint 3 — Project | Project CRUD, project member, task theo project, project progress | Kế hoạch |
| Sprint 4 — Approval | Approval flow, review, notification, realtime | Kế hoạch |
| Sprint 5 — Calendar & Planning | Calendar, plan, objective, deadline reminder | Kế hoạch |
| Sprint 6 — Reporting | Dashboard, report, export, performance review | Kế hoạch |
| Sprint 7 — Hardening | Security review, performance review, accessibility, PWA, backup và monitoring | Kế hoạch |

**Đã hoàn tất trong Sprint 2 tính đến ngày phát hành**: Task CRUD, người phụ trách chính, filter, phân trang, bình luận có phân trang, luồng `draft → todo → in_progress → waiting_review` kèm lịch sử, và tệp đính kèm (upload, tải xuống có kiểm quyền, xoá mềm kèm audit). **Công việc kế tiếp**: activity timeline hợp nhất.

---

## 12. Tiêu chí nghiệm thu

### 12.1 Định nghĩa hoàn thành cho một module

Một module chỉ được coi là hoàn thành khi thoả **toàn bộ** các điều kiện sau:

1. Business rule liên quan đã được triển khai đúng.
2. Authorization hoạt động ở cả tầng permission và tầng Policy.
3. Validation đầy đủ cho mọi input quan trọng.
4. Không có truy vấn N+1 rõ ràng.
5. Có transaction ở luồng ghi nhiều bảng.
6. Có test cho luồng chính và các luồng lỗi quan trọng, toàn bộ test pass.
7. Giao diện responsive, có đủ loading / empty / error state.
8. Thao tác nhạy cảm đã ghi audit log.
9. Tài liệu liên quan đã được cập nhật.
10. Không còn placeholder hoặc TODO không giải thích.

### 12.2 Điều kiện dừng bắt buộc

Không được tuyên bố hoàn thành khi còn thiếu authorization, thiếu validation dữ liệu quan trọng, thiếu transaction cần thiết, thiếu test cho use case chính, còn lỗi build hoặc test, hoặc UI thiếu trạng thái loading/empty/error ở luồng quan trọng.

### 12.3 Thứ tự ưu tiên khi có xung đột yêu cầu

1. Business rule đã được xác nhận.
2. Bảo mật và toàn vẹn dữ liệu.
3. Tương thích với hệ thống hiện tại.
4. Kiến trúc trong bộ tài liệu chuẩn.
5. Tính đơn giản.
6. Tối ưu hiệu năng.
7. Sở thích cá nhân.

---

## 13. Ngoài phạm vi

Các nội dung sau **không** thuộc phạm vi hệ thống ở phiên bản hiện tại:

- Đăng ký tài khoản công khai và cơ chế self-service onboarding.
- Vận hành đa khách hàng (multi-tenant) trên một cài đặt.
- Ứng dụng native cho iOS/Android (chỉ hỗ trợ PWA ở Sprint 7).
- Tích hợp với hệ thống nhân sự, chấm công hoặc kế toán bên ngoài.
- Chấm công, tính lương, quản lý hợp đồng lao động.
- Chat/nhắn tin thời gian thực giữa người dùng (chỉ có bình luận trong ngữ cảnh công việc).
- Đa ngôn ngữ giao diện (chỉ tiếng Việt).

---

## 14. Lịch sử phiên bản

| Phiên bản | Ngày | Nội dung |
|---|---|---|
| 1.0 | 28/07/2026 | Bản đầu tiên — tổng hợp từ bộ tài liệu chuẩn và trạng thái triển khai đến Sprint 2 |
| 1.1 | 29/07/2026 | Cập nhật FR-TASK-10 (tệp đính kèm) sang trạng thái đã triển khai |
