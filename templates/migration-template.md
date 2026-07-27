# Migration Checklist

- [ ] Tên bảng đúng quy ước.
- [ ] Kiểu dữ liệu phù hợp.
- [ ] Foreign key có hành vi delete rõ.
- [ ] Index cho filter/sort.
- [ ] Unique constraint khi cần.
- [ ] Không dùng float cho tiền.
- [ ] Timestamp nhất quán.
- [ ] Soft delete có lý do.
- [ ] Rollback hợp lệ.
- [ ] Không sửa migration đã chạy production.
- [ ] Có kế hoạch backfill nếu dữ liệu lớn.
