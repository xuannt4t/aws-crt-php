# Thiết kế dữ liệu demo theo cơ cấu tổ chức

## Mục tiêu

Tạo một bộ dữ liệu demo đủ rõ để khách hàng nhìn thấy luồng hiện có của DORMIDA WORK: cơ cấu tổ chức nhiều cấp, người dùng thuộc từng đơn vị và công việc được giao trong phạm vi đơn vị. Bộ dữ liệu phải có thể nạp lên TiDB production mà không xoá hoặc làm hỏng dữ liệu đang tồn tại.

## Phạm vi

- Giữ nguyên module hiện có và không bổ sung mô hình Dự án giả.
- Mở rộng cơ cấu thành Ban Điều hành, các phòng Sản phẩm, Kỹ thuật, Kinh doanh, Vận hành và một số nhóm con.
- Tạo tổng cộng khoảng 10–12 tài khoản mẫu, thể hiện các vai trò giám đốc, trưởng phòng và nhân viên.
- Tạo khoảng 15–20 công việc mẫu, phân bổ giữa các đơn vị và bao phủ nhiều trạng thái, mức ưu tiên, thời hạn và người phụ trách.
- Dùng tên, chức danh và nội dung tiếng Việt nhất quán để có thể demo trực tiếp cho khách hàng.

## Nguyên tắc an toàn dữ liệu

- Seeder phải idempotent: chạy lại sẽ cập nhật đúng bản ghi mẫu thay vì tạo bản sao.
- Nhận diện đơn vị bằng mã cố định, người dùng bằng email cố định và công việc bằng cặp đơn vị/tiêu đề cố định.
- Không truncate bảng, không xoá dữ liệu do người dùng tạo và không thay đổi tài khoản quản trị hiện tại.
- Những bản ghi đã soft-delete nhưng thuộc bộ demo có thể được khôi phục; dữ liệu ngoài bộ demo không bị tác động.
- Mật khẩu mẫu dùng chung chỉ dành cho các tài khoản demo, không áp dụng cho tài khoản quản trị.

## Cấu trúc demo

Đơn vị gốc `DORMIDA WORK` chứa Ban Điều hành và bốn phòng chức năng. Phòng Sản phẩm, Kỹ thuật và Kinh doanh có ít nhất một nhóm con để thể hiện quan hệ cha–con. Mỗi phòng có một trưởng phòng và tối thiểu một nhân viên; Ban Điều hành có tài khoản giám đốc.

Công việc mẫu được viết thành các tình huống có thể trình diễn: xây dựng kế hoạch sản phẩm, thiết kế onboarding, phát triển kỹ thuật, rà soát phát hành, chuẩn bị khách hàng tiềm năng, tổng hợp vận hành và xử lý công việc quá hạn. Mỗi đơn vị có đủ dữ liệu để bộ lọc `Đơn vị` tạo ra kết quả khác biệt dễ quan sát.

## Trải nghiệm demo

Người trình diễn vào `Cơ cấu tổ chức` để chỉ ra cây đơn vị và thành viên; sau đó vào `Công việc`, dùng bộ lọc `Đơn vị` để xem công việc của từng phòng. Tài khoản trưởng phòng và nhân viên mẫu có thể được dùng để minh hoạ phạm vi nhìn thấy và quyền thao tác theo vai trò hiện có.

Phần Dự án được mô tả rõ là chưa nằm trong bản hiện tại. Nếu được xác nhận trong phạm vi tiếp theo, Dự án sẽ là một tầng dữ liệu riêng theo luồng `Dự án → Công việc`, không được mô phỏng bằng đơn vị tổ chức.

## Kiểm thử và nghiệm thu

- Kiểm thử tự động xác nhận chạy seeder hai lần không tăng số lượng bản ghi mẫu.
- Xác nhận mọi người dùng mẫu thuộc đúng đơn vị và có đúng vai trò.
- Xác nhận mỗi phòng có công việc và các trạng thái/ưu tiên cần trình diễn.
- Chạy toàn bộ test liên quan, định dạng mã nguồn và build frontend trước khi push.
- Sau khi deploy, chạy seeder trên TiDB rồi kiểm tra trực tiếp cây tổ chức, danh sách người dùng và bộ lọc công việc trên Render.

