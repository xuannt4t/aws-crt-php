# Realtime Notifications Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Thêm trung tâm thông báo realtime qua Reverb, toast ổn định và title mang thương hiệu DORMIDA WORK.

**Architecture:** Task activities phát domain event sau khi transaction commit. Listener chuyển activity thành Laravel Notification lưu database và broadcast tới private user channel; scheduler phát riêng các mốc hạn. Frontend dùng Echo để nhận realtime và endpoint JSON làm nguồn đồng bộ/read-state.

**Tech Stack:** Laravel 11 Notifications/Events/Scheduler, Reverb, Redis queue, Vue 3, Inertia, Laravel Echo, pusher-js, PrimeVue Toast, Pest 3, Vitest.

## Global Constraints

- Mọi notification phải lưu database trước hoặc đồng thời với broadcast; reconnect không được làm mất lịch sử.
- Actor không nhận notification do chính mình tạo; recipient phải được khử trùng.
- Endpoint notification chỉ truy cập dữ liệu của user đăng nhập.
- Scheduler chạy mỗi 5 phút, `withoutOverlapping`, không gửi trùng cùng mốc/task/user.
- Title luôn là `<Tên trang> - DORMIDA WORK`, không phụ thuộc giá trị `Laravel` còn sót trong `.env` cũ.
- Tất cả nội dung giao diện bằng tiếng Việt; giữ nguyên visual language hiện tại.
- Tuân thủ TDD: test phải fail đúng lý do trước khi viết production code.

---

### Task 1: Hạ tầng database notification và payload broadcast

**Files:**
- Create: `database/migrations/2026_08_02_140000_create_notifications_table.php`
- Create: `app/Notifications/TaskNotification.php`
- Create: `tests/Feature/Notification/TaskNotificationTest.php`

**Interfaces:**
- Produces: `TaskNotification::__construct(string $event, Task $task, string $title, string $message, ?User $actor = null, ?string $deduplicationKey = null)`.
- Produces payload keys: `event`, `title`, `message`, `url`, `task_id`, `actor`, `deduplication_key`, `created_at`.

- [ ] **Step 1: Viết test fail cho database và broadcast channel**

```php
test('task notifications persist a frontend-ready payload and broadcast', function () {
    Notification::fake();
    $recipient = User::factory()->create();
    $actor = User::factory()->create(['name' => 'Người giao việc']);
    $task = Task::factory()->create(['title' => 'Hoàn thành báo cáo']);

    $recipient->notify(new TaskNotification(
        event: 'assigned',
        task: $task,
        title: 'Bạn được giao công việc mới',
        message: 'Người giao việc đã giao “Hoàn thành báo cáo” cho bạn.',
        actor: $actor,
    ));

    Notification::assertSentTo($recipient, TaskNotification::class, function ($notification) use ($recipient) {
        expect($notification->via($recipient))->toBe(['database', 'broadcast']);
        expect($notification->toArray($recipient))->toMatchArray([
            'event' => 'assigned',
            'title' => 'Bạn được giao công việc mới',
        ]);
        return true;
    });
});
```

- [ ] **Step 2: Chạy test đỏ**

Run: `php vendor/bin/pest tests/Feature/Notification/TaskNotificationTest.php`
Expected: FAIL vì class và bảng notifications chưa tồn tại.

- [ ] **Step 3: Tạo migration chuẩn Laravel**

Tạo bảng với `uuid('id')->primary()`, `string('type')`, `morphs('notifiable')`,
`text('data')`, `timestamp('read_at')->nullable()`, timestamps và index phù hợp.

- [ ] **Step 4: Viết `TaskNotification`**

Class extends `Notification`, implements `ShouldQueue`, dùng `Queueable` và
`afterCommit()`. `via()` trả `['database', 'broadcast']`; `toArray()` trả payload
chung; `toBroadcast()` trả `new BroadcastMessage($this->toArray($notifiable))`.

- [ ] **Step 5: Chạy migration test và test xanh**

Run: `php vendor/bin/pest tests/Feature/Notification/TaskNotificationTest.php`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_08_02_140000_create_notifications_table.php app/Notifications/TaskNotification.php tests/Feature/Notification/TaskNotificationTest.php
git commit -m "feat(notification): thêm payload database và broadcast"
```

---

### Task 2: Phát thông báo từ task activities

**Files:**
- Create: `app/Events/TaskActivityRecorded.php`
- Create: `app/Listeners/SendTaskActivityNotifications.php`
- Modify: `app/Actions/Task/RecordTaskActivityAction.php`
- Modify: `app/Providers/AppServiceProvider.php`
- Test: `tests/Feature/Task/TaskActivityNotificationTest.php`

**Interfaces:**
- Consumes: `TaskNotification` từ Task 1.
- Produces: event `TaskActivityRecorded(int $activityId)` implements `ShouldDispatchAfterCommit`.
- Produces: listener ánh xạ `TaskActivityType` sang copy và recipient.

- [ ] **Step 1: Viết test fail cho assignment và loại actor**

Test qua action/controller thật: tạo task có creator A, assignee B; actor A đổi
assignee sang C. Assert C nhận event `assigned`, A không nhận, B không nhận.

- [ ] **Step 2: Viết test fail cho status/progress/comment/attachment**

Mỗi test dùng `Notification::fake()`, gọi action thật và assert creator + assignee
nhận đúng event, actor bị loại, danh sách không trùng khi creator cũng là assignee.

- [ ] **Step 3: Chạy test đỏ**

Run: `php vendor/bin/pest tests/Feature/Task/TaskActivityNotificationTest.php`
Expected: FAIL vì chưa dispatch event/listener.

- [ ] **Step 4: Dispatch event sau khi lưu activity**

Trong `RecordTaskActivityAction::execute()`, sau `TaskActivity::create(...)`, gọi
`TaskActivityRecorded::dispatch($activity->id)` và return activity như hiện tại.
Event implements `ShouldDispatchAfterCommit` để không phát khi transaction rollback.

- [ ] **Step 5: Viết listener mapping**

Listener load `task.creator`, `task.assignee`, `actor`; mapping:

```php
TaskActivityType::Created, TaskActivityType::Assigned => 'assigned';
TaskActivityType::StatusChanged => 'status_changed';
TaskActivityType::ProgressUpdated, TaskActivityType::QuantityUpdated => 'progress_updated';
TaskActivityType::Commented => 'commented';
TaskActivityType::AttachmentAdded => 'attachment_added';
```

Created chỉ gửi nếu task có assignee khác actor. Assigned chỉ gửi assignee mới.
Các loại còn lại gửi creator và assignee, unique theo id, reject actor id/null.
Không phát cho attachment removed vì không thuộc phạm vi đã duyệt.

- [ ] **Step 6: Đăng ký listener và chạy test xanh**

Đăng ký bằng `Event::listen(TaskActivityRecorded::class, SendTaskActivityNotifications::class)`
trong `AppServiceProvider::boot()`.

Run: `php vendor/bin/pest tests/Feature/Task/TaskActivityNotificationTest.php tests/Feature/Task/TaskActivityTest.php`
Expected: PASS, gồm cả test rollback cũ.

- [ ] **Step 7: Commit**

```bash
git add app/Events app/Listeners app/Actions/Task/RecordTaskActivityAction.php app/Providers/AppServiceProvider.php tests/Feature/Task/TaskActivityNotificationTest.php
git commit -m "feat(notification): phát thông báo từ hoạt động công việc"
```

---

### Task 3: Thông báo sắp đến hạn và quá hạn

**Files:**
- Create: `app/Console/Commands/SendTaskDeadlineNotifications.php`
- Modify: `routes/console.php`
- Test: `tests/Feature/Notification/TaskDeadlineNotificationTest.php`

**Interfaces:**
- Consumes: `TaskNotification` và field `deduplication_key`.
- Produces command `tasks:notify-deadlines`.

- [ ] **Step 1: Viết test fail cho hai mốc hạn**

Freeze time. Tạo task active đến hạn sau 12 giờ và task đã quá hạn; assert creator
và assignee nhận lần lượt `due_soon`, `overdue`, actor null.

- [ ] **Step 2: Viết test fail cho dedupe và terminal tasks**

Chạy command hai lần và assert mỗi user chỉ có một database notification/mốc.
Tạo task `completed` và assert không nhận notification.

- [ ] **Step 3: Chạy test đỏ**

Run: `php vendor/bin/pest tests/Feature/Notification/TaskDeadlineNotificationTest.php`
Expected: FAIL vì command chưa tồn tại.

- [ ] **Step 4: Implement command**

Query task không soft-delete, status khác `completed` và `cancelled`, có `due_at`; chunk theo id.
Xác định `due_soon` nếu `now <= due_at <= now + 24h`, `overdue` nếu `due_at < now`.
Recipient là creator + assignee unique. Key:
`task:{taskId}:{event}:user:{userId}`. Trước khi notify, query
`$user->notifications()->where('data->deduplication_key', $key)->exists()`.

- [ ] **Step 5: Đăng ký scheduler**

```php
Schedule::command('tasks:notify-deadlines')
    ->everyFiveMinutes()
    ->withoutOverlapping();
```

- [ ] **Step 6: Chạy test xanh và commit**

Run: `php vendor/bin/pest tests/Feature/Notification/TaskDeadlineNotificationTest.php`
Expected: PASS.

```bash
git add app/Console/Commands/SendTaskDeadlineNotifications.php routes/console.php tests/Feature/Notification/TaskDeadlineNotificationTest.php
git commit -m "feat(notification): nhắc hạn công việc không gửi trùng"
```

---

### Task 4: API danh sách và read-state

**Files:**
- Create: `app/Http/Controllers/NotificationController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Notification/NotificationControllerTest.php`

**Interfaces:**
- Produces JSON `{ notifications: NotificationItem[], unread_count: number }`.
- `NotificationItem`: `id`, `event`, `title`, `message`, `url`, `task_id`, `actor`, `created_at`, `read_at`.

- [ ] **Step 1: Viết test fail cho index**

Tạo 12 notifications cho user đăng nhập và 1 cho user khác. GET index phải trả
đúng 10 bản mới nhất, unread count của user hiện tại, không lộ bản của user khác.

- [ ] **Step 2: Viết test fail cho read authorization**

PATCH notification của mình trả 200 và set `read_at`; PATCH id của user khác trả
404; PATCH read-all chỉ cập nhật notification của mình. Guest bị redirect login.

- [ ] **Step 3: Chạy test đỏ**

Run: `php vendor/bin/pest tests/Feature/Notification/NotificationControllerTest.php`
Expected: FAIL 404 vì route chưa có.

- [ ] **Step 4: Implement controller và route**

Controller resolve notification bằng
`$request->user()->notifications()->whereKey($id)->firstOrFail()`. Index map payload
và timestamps ISO. Routes trong group `auth`, đặt tên `notifications.index`,
`notifications.read`, `notifications.read-all`.

- [ ] **Step 5: Chạy test xanh và commit**

Run: `php vendor/bin/pest tests/Feature/Notification/NotificationControllerTest.php`
Expected: PASS.

```bash
git add app/Http/Controllers/NotificationController.php routes/web.php tests/Feature/Notification/NotificationControllerTest.php
git commit -m "feat(notification): thêm API danh sách và trạng thái đã đọc"
```

---

### Task 5: Echo, chuông realtime và đồng bộ reconnect

**Files:**
- Modify: `package.json`, `package-lock.json`
- Create: `resources/js/echo.ts`
- Create: `resources/js/types/notification.ts`
- Create: `resources/js/Components/AppNotificationBell.vue`
- Create: `resources/js/Composables/useRealtimeNotifications.ts`
- Modify: `resources/js/bootstrap.ts`
- Modify: `resources/js/Layouts/AuthenticatedLayout.vue`
- Modify: `.env.example`, `.env.production.example`
- Test: `resources/js/Composables/useRealtimeNotifications.test.ts`

**Interfaces:**
- Consumes endpoint Task 4 và private channel `App.Models.User.{id}`.
- Produces composable state `notifications`, `unreadCount`, `isLoading`, `error`;
  methods `refresh`, `markAsRead`, `markAllAsRead`, `connect`, `disconnect`.

- [ ] **Step 1: Cài dependency test và realtime**

Run: `npm install laravel-echo pusher-js && npm install -D vitest`

Thêm script `"test:unit": "vitest run"`.

- [ ] **Step 2: Viết test fail cho merge realtime**

Test pure state behavior trong composable helper: notification broadcast mới được
prepend, id trùng được thay thế, unread count tăng đúng một lần, giữ tối đa 10.

- [ ] **Step 3: Chạy test đỏ**

Run: `npm run test:unit`
Expected: FAIL vì composable/helper chưa tồn tại.

- [ ] **Step 4: Tạo Echo client**

`echo.ts` khởi tạo `new Echo({ broadcaster: 'reverb', key, wsHost, wsPort,
wssPort, forceTLS, enabledTransports: ['ws', 'wss'] })` từ `VITE_REVERB_*`.
Export singleton; bootstrap gán Axios headers như hiện tại.

- [ ] **Step 5: Implement composable**

`refresh()` gọi `/notifications`; `connect(userId)` subscribe
`.private('App.Models.User.' + userId).notification(handler)`. Khi Pusher connection
event `connected` chạy, gọi refresh để bù dữ liệu bị lỡ. `disconnect()` leave channel.

- [ ] **Step 6: Implement bell dropdown**

Component dùng visual token hiện tại: nút size 40, badge đỏ, panel 380px top-right,
unread row nền brand nhẹ, dot unread, thời gian tương đối `Intl.RelativeTimeFormat`,
empty/loading/error states, Escape và click-outside. Click row mark read rồi
`router.visit(notification.url)`.

- [ ] **Step 7: Mount trong layout và thêm env**

Đặt bell ngay trước profile link. `.env.example` thêm `VITE_REVERB_APP_KEY`,
`VITE_REVERB_HOST`, `VITE_REVERB_PORT`, `VITE_REVERB_SCHEME` tham chiếu biến server;
production example dùng host public và HTTPS/WSS phù hợp domain.

- [ ] **Step 8: Chạy unit test, typecheck và commit**

Run: `npm run test:unit && npm run build`
Expected: PASS.

```bash
git add package.json package-lock.json resources/js .env.example .env.production.example
git commit -m "feat(notification): thêm chuông realtime qua Reverb"
```

---

### Task 6: Toast lặp lại và title DORMIDA WORK

**Files:**
- Create: `resources/js/Support/documentTitle.ts`
- Modify: `resources/js/app.ts`
- Modify: `resources/js/Components/AppToast.vue`
- Modify: `.env.example`
- Test: `resources/js/Support/documentTitle.test.ts`
- Test: `tests/Feature/ProfileTest.php`

**Interfaces:**
- Produces `documentTitle(pageTitle: string): string`.
- Toast subscribes Inertia `success` events and reads flash object sau mỗi visit.

- [ ] **Step 1: Viết test fail cho title**

```ts
expect(documentTitle('Công việc')).toBe('Công việc - DORMIDA WORK');
expect(documentTitle('')).toBe('DORMIDA WORK');
```

- [ ] **Step 2: Chạy test đỏ**

Run: `npm run test:unit`
Expected: FAIL vì helper chưa tồn tại.

- [ ] **Step 3: Implement title cố định thương hiệu**

`app.ts` dùng `title: documentTitle`; `.env.example` đổi
`APP_NAME="DORMIDA WORK"`. Cập nhật `.env` local (ignored) cùng giá trị để Blade
title trước hydration cũng đúng; không commit `.env`.

- [ ] **Step 4: Sửa toast theo visit thay vì primitive watcher**

AppToast giữ `onMounted` cho initial visit và đăng ký `router.on('success', event =>
showFlash(event.detail.page.props.flash))`; cleanup listener ở `onUnmounted`. Điều
này phát lại cả khi message text giống lần trước.

- [ ] **Step 5: Chạy test xanh và regression**

Run: `npm run test:unit && php vendor/bin/pest tests/Feature/ProfileTest.php`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add resources/js/Support resources/js/app.ts resources/js/Components/AppToast.vue .env.example tests/Feature/ProfileTest.php
git commit -m "fix(ui): sửa toast và title DORMIDA WORK"
```

---

### Task 7: Verification và cập nhật vận hành

**Files:**
- Modify: `docs/deployment.md`

- [ ] **Step 1: Cập nhật runbook realtime**

Ghi rõ frontend cần các `VITE_REVERB_*` tại build time, queue worker phải chạy,
nginx proxy `/app`, lệnh kiểm tra `supervisorctl status dormida-reverb` và cách
kiểm tra WebSocket trong DevTools.

- [ ] **Step 2: Chạy toàn bộ verification**

```bash
php vendor/bin/pest
php vendor/bin/pint --test
npm run test:unit
npm run lint
npm run format:check
npm run build
bash -n deploy/backup-db.sh
git diff --check
```

Expected: tất cả exit 0; Pest không failure; build hoàn tất.

- [ ] **Step 3: Rà giao diện**

Mở hai phiên đăng nhập A/B. A giao task cho B; B nhận badge/dropdown/toast mà
không reload. Đánh dấu đọc cập nhật badge; reload vẫn giữ trạng thái. Tab hiển thị
`Công việc - DORMIDA WORK`.

- [ ] **Step 4: Commit**

```bash
git add docs/deployment.md
git commit -m "docs(notification): bổ sung vận hành thông báo realtime"
```
