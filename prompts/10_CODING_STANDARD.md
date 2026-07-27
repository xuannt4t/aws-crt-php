# 10 — CODING STANDARD

## 1. PHP

- PSR-12.
- Constructor property promotion khi phù hợp.
- Type hint đầy đủ.
- Return type đầy đủ.
- Dùng `final` cho class không dự kiến kế thừa.
- Dùng readonly DTO khi phù hợp.
- Không dùng helper toàn cục tùy tiện.
- Không catch `Throwable` rồi bỏ qua lỗi.
- Không dùng static service locator.

## 2. Laravel

- Route model binding.
- FormRequest.
- Policy.
- Resource.
- Eloquent scope cho filter dùng lại.
- Transaction cho multi-write.
- `afterCommit` cho side effect phụ thuộc commit.
- Config thay cho env ngoài file config.

## 3. Naming

- Class: PascalCase.
- Method/variable: camelCase.
- Table/column: snake_case.
- Permission: `resource.action`.
- Event: quá khứ, ví dụ `TaskAssigned`.
- Command: động từ, ví dụ `SendDeadlineReminders`.
- Job: hành động, ví dụ `GenerateProjectReport`.

## 4. Method

- Một method làm một việc chính.
- Tránh parameter quá nhiều; dùng DTO.
- Guard clause thay cho lồng if sâu.
- Không comment điều code đã nói rõ.
- Comment lý do, không comment cú pháp.

## 5. Vue/TypeScript

- `<script setup lang="ts">`.
- Không dùng `any` tùy tiện.
- Props, emits, API response có interface/type.
- Tên component PascalCase.
- Composable bắt đầu bằng `use`.
- Handler bắt đầu bằng `handle`.
- Boolean bắt đầu bằng `is`, `has`, `can`, `should`.

## 6. Git

Commit nhỏ, rõ mục đích.

Ví dụ:

```text
feat(task): add approval workflow
fix(project): prevent duplicate members
refactor(auth): move permission mapping to service
test(task): cover overdue transition
```

Không trộn refactor lớn với feature nếu có thể tách.

## 7. Review checklist

- Đúng business rule.
- Đúng permission.
- Validation đủ.
- Không N+1.
- Transaction hợp lý.
- Không log secret.
- Query có index phù hợp.
- UI có loading/empty/error.
- Responsive.
- Test đủ.
- Không thay đổi ngoài phạm vi.
- Không còn code chết.
