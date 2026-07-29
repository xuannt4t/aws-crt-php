# Thiết kế: Số lượng dự kiến và thực tế cho công việc (FR-TASK-12)

- Ngày: 30/07/2026
- Trạng thái: đã duyệt
- Nhánh: `feature/task-quantity-tracking`

## 1. Bối cảnh

Nhiều công việc trong DORMIDA WORK là việc lặp lại chân tay, đo được bằng con số: nhập 500 hồ sơ, gọi 200 cuộc, scan 1.000 trang. Hiện người phụ trách phải tự quy đổi khối lượng đã làm ra phần trăm rồi kéo thanh trượt — vừa mất công vừa không chính xác, và người quản lý không thấy được con số thật.

Yêu cầu: cho phép đặt **số lượng dự kiến** khi tạo việc, người phụ trách chỉ nhập **số lượng thực tế** đã làm, hệ thống tự tính tiến độ.

## 2. Phạm vi

Trong phạm vi:

- Ba cột mới trên bảng `tasks`: số lượng dự kiến, số lượng thực tế, đơn vị đo.
- Nhập số lượng dự kiến và đơn vị ở form tạo/sửa công việc.
- Nhập số lượng thực tế ở trang chi tiết công việc, thay cho thanh kéo phần trăm.
- Tự tính `progress` từ số lượng.
- Ghi một dòng dòng thời gian cho mỗi lần đổi số lượng thực tế.

Ngoài phạm vi:

- Hiển thị số lượng ở danh sách công việc và bộ lọc theo số lượng.
- Báo cáo, thống kê sản lượng (thuộc Sprint 6 — Reporting).
- Số lượng theo từng người khi một công việc có nhiều người làm (hiện mỗi công việc chỉ có một người phụ trách).

## 3. Mô hình dữ liệu

Thêm ba cột vào bảng `tasks`, đặt sau `progress`:

| Cột | Kiểu | Null | Mô tả |
|---|---|---|---|
| `planned_quantity` | `unsignedInteger` | có | Số lượng dự kiến. Khác `null` nghĩa là công việc chạy ở **chế độ đo sản lượng**. |
| `actual_quantity` | `unsignedInteger` | có | Số lượng đã làm, là tổng lũy tiến. |
| `quantity_unit` | `string(30)` | có | Đơn vị đo do người dùng tự nhập ("hồ sơ", "cuộc gọi"). Chỉ dùng để hiển thị. |

Ràng buộc bất biến (invariant): `planned_quantity` và `actual_quantity` luôn cùng `null` hoặc cùng khác `null`. `quantity_unit` chỉ được khác `null` khi `planned_quantity` khác `null`.

`Task::$fillable` bổ sung ba cột; `casts()` bổ sung `planned_quantity` và `actual_quantity` là `integer`.

Không thêm bảng mới. Lịch sử thay đổi số lượng nằm ở `task_activities` sẵn có.

## 4. Quy tắc nghiệp vụ

**BR-Q-01 — Chế độ đo.** Công việc có `planned_quantity` khác `null` chạy ở chế độ đo sản lượng. Công việc có `planned_quantity` bằng `null` giữ nguyên hành vi hiện tại: người phụ trách kéo phần trăm bằng tay.

**BR-Q-02 — Công thức tiến độ.** Ở chế độ đo sản lượng, `progress` là giá trị dẫn xuất, không nhập tay:

```
progress = min(100, (int) round(actual_quantity / planned_quantity * 100))
```

`progress` vẫn được lưu vào cột `tasks.progress` (không tính lại lúc đọc) để danh sách, bộ lọc và báo cáo sau này dùng chung một trường như hiện nay.

**BR-Q-03 — Cho phép vượt chỉ tiêu.** `actual_quantity` được phép lớn hơn `planned_quantity`. Giao diện hiển thị đúng con số thật (`520/500`), còn `progress` chốt ở 100.

**BR-Q-04 — Điều kiện cập nhật số lượng thực tế.** Chỉ cập nhật được khi công việc ở trạng thái `in_progress`, đúng ràng buộc đang áp cho tiến độ. Quyền là quyền `updateProgress` sẵn có trên `TaskPolicy`.

**BR-Q-05 — Hai lối vào loại trừ nhau.** Route cập nhật phần trăm (`tasks.progress.update`) từ chối công việc đang ở chế độ đo sản lượng, kèm thông báo tiếng Việt. Ngược lại, route cập nhật số lượng (`tasks.quantity.update`) từ chối công việc không có `planned_quantity`. Không có đường nào ghi đè kết quả của đường kia.

**BR-Q-06 — Bật chế độ đo.** Khi người tạo/sửa việc điền `planned_quantity` cho một công việc chưa có, `actual_quantity` được đặt bằng `0` và `progress` được tính lại theo BR-Q-02 (kết quả là 0).

**BR-Q-07 — Sửa số lượng dự kiến.** Sửa `planned_quantity` của công việc đang ở chế độ đo sản lượng thì `progress` được tính lại ngay theo `actual_quantity` hiện có.

**BR-Q-08 — Tắt chế độ đo.** Xoá trắng `planned_quantity` thì `actual_quantity` và `quantity_unit` cùng về `null`, công việc quay lại chế độ kéo tay. `progress` **giữ nguyên** giá trị đã tính gần nhất để không mất tiến độ đã báo cáo.

**BR-Q-09 — Giá trị hợp lệ.** `planned_quantity`: số nguyên từ 1 đến 1.000.000. `actual_quantity`: số nguyên từ 0 đến 1.000.000. `quantity_unit`: chuỗi tối đa 30 ký tự, chỉ nhận khi có `planned_quantity`.

**BR-Q-10 — Ghi nhận không đổi thì bỏ qua.** Gửi lại đúng con số hiện có thì không ghi dòng thời gian và không đổi `updated_at`, giống cách `UpdateTaskProgressAction` đang làm.

## 5. Thay đổi phía backend

### 5.1 Migration

`database/migrations/*_add_quantity_columns_to_tasks_table.php` — thêm ba cột nêu ở §3, `down()` xoá cả ba. Công việc đang có giữ nguyên `null`, tức không công việc nào bị đổi hành vi sau khi chạy migration.

### 5.2 Enum

`App\Enums\TaskActivityType` thêm `case QuantityUpdated = 'quantity_updated';`.

### 5.3 Action mới

`App\Actions\Task\UpdateTaskActualQuantityAction::execute(User $actor, Task $task, int $quantity): Task`

Trong một `DB::transaction`:

1. `lockForUpdate()` để lấy bản ghi mới nhất — bắt buộc, vì số lượng là phép đọc-sửa-ghi và hai người cùng gửi có thể ghi đè nhau (đây đúng lỗi đã sửa ở `UpdateTaskAction` trong sprint trước).
2. Nếu `planned_quantity` là `null` → `ValidationException` với thông báo "Công việc này không theo dõi bằng số lượng."
3. Nếu trạng thái không phải `in_progress` → `ValidationException` với thông báo "Chỉ có thể cập nhật số lượng khi công việc đang được thực hiện."
4. Nếu `actual_quantity` cũ bằng giá trị mới → trả về ngay, không ghi gì (BR-Q-10).
5. Cập nhật `actual_quantity` và `progress` (tính theo BR-Q-02) trong một lệnh `update`.
6. Ghi hoạt động `quantity_updated` với payload `{from, to, planned, unit}` qua `RecordTaskActivityAction`.
7. Trả về bản ghi đã `refresh()`.

Action này **không** ghi thêm hoạt động `progress_updated` dù `progress` có đổi: một hành động của người dùng chỉ sinh một dòng thời gian.

### 5.4 Action sửa

`UpdateTaskProgressAction` — thêm chốt chặn ngay sau khi khoá bản ghi: nếu `planned_quantity` khác `null` thì `ValidationException` trên khoá `progress` với thông báo "Công việc này theo dõi bằng số lượng, hãy cập nhật số lượng thực tế." (BR-Q-05).

`CreateTaskAction` — sau khi `Task::create`, nếu `planned_quantity` khác `null` thì đặt `actual_quantity = 0` (BR-Q-06). `progress` vẫn là 0 nên không cần tính thêm.

`UpdateTaskAction` — sau khi cập nhật các trường, chuẩn hoá lại bộ ba cột theo BR-Q-06, BR-Q-07 và BR-Q-08, ngay trong transaction đang có. Việc bật/tắt/đổi chỉ tiêu **không** sinh dòng thời gian riêng; đây là hành vi sửa thông tin công việc, khác với hành vi báo cáo sản lượng.

Logic tính `progress` từ số lượng nằm ở một chỗ duy nhất: phương thức `Task::progressFromQuantity(int $planned, int $actual): int` trên model, để Action nào cũng gọi cùng một công thức.

### 5.5 FormRequest

`StoreTaskRequest` và `UpdateTaskRequest` thêm:

```php
'planned_quantity' => ['nullable', 'integer', 'min:1', 'max:1000000'],
'quantity_unit' => [
    Rule::prohibitedIf(fn () => ! $this->filled('planned_quantity')),
    'nullable', 'string', 'max:30',
],
'actual_quantity' => ['prohibited'],
```

`actual_quantity` bị cấm ở form tạo/sửa vì nó chỉ được đổi qua route riêng.

`UpdateTaskQuantityRequest` (mới): `authorize()` dùng `$this->user()->can('updateProgress', $this->route('task'))`; rule `'actual_quantity' => ['required', 'integer', 'min:0', 'max:1000000']`.

Toàn bộ `messages()` viết bằng tiếng Việt, theo đúng chuẩn đã chốt ở sprint trước.

### 5.6 Route và controller

`Route::patch('tasks/{task}/quantity', [TaskController::class, 'updateQuantity'])->name('tasks.quantity.update');` đặt ngay dưới route `tasks.progress.update`.

`TaskController::updateQuantity(UpdateTaskQuantityRequest $request, Task $task, UpdateTaskActualQuantityAction $action)` gọi Action rồi `back()`, giống hệt `updateProgress`.

`TaskController::show` bổ sung `planned_quantity`, `actual_quantity`, `quantity_unit` vào payload công việc. **Không** thêm cờ quyền mới: giao diện dùng lại `actions.updateProgress` rồi tự rẽ nhánh theo `planned_quantity`, vì quyền và điều kiện trạng thái của hai thao tác giống nhau.

## 6. Thay đổi phía frontend

### 6.1 `TaskForm.vue`

Trong mục *Phạm vi & thời hạn*, thêm hai ô cạnh nhau: "Số lượng dự kiến" (`type="number"`, `min="1"`) và "Đơn vị" (`type="text"`, `maxlength="30"`, placeholder "hồ sơ, cuộc gọi..."). Dưới hai ô là một dòng gợi ý: "Để trống nếu công việc theo dõi bằng phần trăm." Ô đơn vị bị vô hiệu hoá khi chưa nhập số lượng dự kiến, để người dùng không gửi lên tổ hợp bị `prohibited` chặn.

### 6.2 `Pages/Tasks/Show.vue`

Ô "Tiến độ" ở thanh bên: công việc có `planned_quantity` thì hiển thị thêm dòng "480/500 hồ sơ" ngay trên thanh phần trăm; không có thì giữ nguyên như hiện tại.

Form cập nhật: khi `planned_quantity` khác `null`, thay thanh kéo và ô `%` bằng một ô nhập số lượng thực tế (`type="number"`, `min="0"`), nhãn "Số lượng đã làm", hậu tố là đơn vị nếu có, nút bấm ghi "Lưu số lượng". Ngược lại giữ nguyên giao diện kéo phần trăm. Hai nhánh dùng hai `useForm` riêng để tránh lẫn trường và lẫn lỗi.

### 6.3 `TaskActivityTimeline.vue`

Thêm `quantity_updated` vào `KNOWN_TYPES`, `iconFor` (dùng `tasks`) và `toneFor` (dùng tông `bg-sky-50 text-sky-700`), cùng một nhánh `describe` sinh câu: `cập nhật sản lượng từ 260 lên 480/500 hồ sơ`. Khi payload không có `unit` thì bỏ phần đơn vị.

### 6.4 `types/index.d.ts`

`Task` thêm `planned_quantity: number | null`, `actual_quantity: number | null`, `quantity_unit: string | null`. `TaskActivityType` thêm `'quantity_updated'`.

## 7. Kiểm thử

Pest, đặt trong `tests/Feature/Task/TaskQuantityTest.php` (và bổ sung vào các file test sẵn có nơi phù hợp):

1. Nhập số lượng thực tế thì `actual_quantity` và `progress` được tính đúng (`120/500` → `24`).
2. Kết quả làm tròn đúng (`1/3` → `33`).
3. Vượt chỉ tiêu thì `actual_quantity` giữ con số thật còn `progress` chốt ở `100`.
4. Công việc không ở trạng thái `in_progress` bị từ chối kèm thông báo tiếng Việt.
5. Công việc không có `planned_quantity` mà gọi route số lượng thì bị từ chối.
6. Công việc **có** `planned_quantity` mà gọi route phần trăm cũ thì bị từ chối, và `progress` không đổi.
7. Người không phải người phụ trách gọi route số lượng nhận 403.
8. Mỗi lần đổi số lượng ghi đúng một dòng `quantity_updated` với payload đủ `from`, `to`, `planned`, `unit`; và **không** sinh kèm dòng `progress_updated`.
9. Gửi lại đúng con số cũ thì không sinh dòng thời gian nào.
10. Validation chặn `planned_quantity` bằng `0`, số âm, và vượt 1.000.000.
11. Gửi `quantity_unit` mà không có `planned_quantity` thì bị chặn.
12. Tạo việc có `planned_quantity` thì `actual_quantity` được đặt `0`.
13. Sửa `planned_quantity` từ 500 xuống 400 khi đã làm 200 thì `progress` nhảy từ 40 lên 50 (BR-Q-07).
14. Xoá trắng `planned_quantity` thì hai cột kia về `null` còn `progress` giữ nguyên (BR-Q-08).
15. Công việc không có `planned_quantity` vẫn kéo phần trăm được như cũ (test hồi quy).

Frontend không có test tự động trong dự án này; kiểm tra bằng `npm run build` và rà tay trên trang chi tiết.

## 8. Rủi ro

- **Đua ghi (race).** Hai tab cùng gửi số lượng sẽ ghi đè nhau nếu không khoá. Đã xử lý bằng `lockForUpdate()` trong Action, đúng bài học từ `UpdateTaskAction` sprint trước.
- **Lệch giữa `progress` và số lượng.** Vì `progress` được lưu chứ không tính lúc đọc, mọi đường ghi vào số lượng đều phải tính lại `progress`. Rủi ro này được thu hẹp bằng cách để công thức ở một chỗ duy nhất (`Task::progressFromQuantity`) và chỉ có hai Action được phép chạm vào ba cột số lượng.
- **Dữ liệu cũ.** Không có, vì migration chỉ thêm cột `null`.

## 9. Tài liệu đi kèm

Sau khi code xong: bổ sung **FR-TASK-12** vào `docs/srs/SRS.md` (§4 yêu cầu chức năng và §10.4 mô hình dữ liệu), ghi các quy tắc BR-Q-01…BR-Q-10 vào phần quy tắc nghiệp vụ, rồi render lại `docs/srs/DORMIDA-WORK-SRS.pdf` bằng `node docs/srs/build-pdf.mjs`.
