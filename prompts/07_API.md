# 07 — API

## 1. Phạm vi

Dùng JSON API cho:

- Mobile/PWA khi cần.
- Tích hợp ngoài.
- Tương tác realtime hoặc bất đồng bộ.
- Chức năng không phù hợp Inertia navigation.

## 2. Response thành công

```json
{
  "success": true,
  "message": "Thành công.",
  "data": {},
  "meta": null,
  "errors": null
}
```

## 3. Response lỗi

```json
{
  "success": false,
  "message": "Dữ liệu không hợp lệ.",
  "data": null,
  "meta": {
    "trace_id": "..."
  },
  "errors": {
    "title": [
      "Tiêu đề là bắt buộc."
    ]
  }
}
```

## 4. HTTP status

- 200: Thành công.
- 201: Tạo mới.
- 204: Thành công không có body.
- 400: Request không hợp lệ về mặt tổng quát.
- 401: Chưa xác thực.
- 403: Không có quyền.
- 404: Không tìm thấy.
- 409: Xung đột trạng thái.
- 422: Validation hoặc business rule có lỗi theo field.
- 429: Quá giới hạn.
- 500: Lỗi hệ thống.

## 5. Pagination

```json
{
  "data": [],
  "meta": {
    "current_page": 1,
    "per_page": 20,
    "total": 100,
    "last_page": 5
  }
}
```

Giới hạn `per_page`, ví dụ tối đa 100.

## 6. Filter và sort

Quy ước:

```text
?page=1
&per_page=20
&search=keyword
&status=in_progress
&department_id=10
&sort=-due_at,title
```

- Dấu `-` nghĩa là giảm dần.
- Chỉ cho sort theo whitelist.
- Chỉ filter theo whitelist.

## 7. Versioning

Endpoint public hoặc tích hợp ngoài phải version:

```text
/api/v1/...
```

Inertia internal route không cần version API.

## 8. Idempotency

Dùng idempotency key cho:

- Tạo giao dịch.
- Tích hợp webhook.
- Tác vụ có nguy cơ gửi lặp.
- Submit quan trọng từ mobile mạng không ổn định.

## 9. Webhook

Webhook phải:

- Xác minh chữ ký.
- Kiểm tra timestamp.
- Chống replay.
- Trả phản hồi nhanh.
- Đẩy xử lý nặng vào queue.
- Lưu event ID để chống duplicate.
