# Module Dependencies

## Nền tảng

1. Authentication
2. User
3. Organization
4. Role & Permission
5. Audit Log
6. System Settings

## Nghiệp vụ cốt lõi

1. Objectives
2. Plans
3. Projects
4. Tasks
5. Calendar
6. Approval
7. Notifications
8. Reports
9. Reviews
10. Dashboard

## Dependency chính

- Task phụ thuộc User, Organization, Permission.
- Project phụ thuộc User, Organization, Permission.
- Approval phụ thuộc Task/Project và Permission.
- Report phụ thuộc dữ liệu từ Task, Project, Approval.
- Dashboard phụ thuộc Report/Aggregate.
- Realtime phụ thuộc Notification và authorized channels.
