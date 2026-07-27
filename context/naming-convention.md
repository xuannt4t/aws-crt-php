# Naming Convention

## Backend

- `StoreTaskRequest`
- `UpdateTaskRequest`
- `TaskPolicy`
- `TaskResource`
- `TaskService`
- `CreateTaskAction`
- `TaskStatus`
- `TaskPriority`
- `TaskAssigned`
- `SendTaskAssignedNotification`

## Frontend

- Page: `Tasks/Index.vue`
- Component: `TaskForm.vue`
- Composable: `useTaskFilters.ts`
- Type: `task.ts`
- Service: `taskService.ts`
- Handler: `handleSubmit`
- Boolean: `isLoading`, `canApprove`

## Routes

Web:

```text
tasks.index
tasks.store
tasks.show
tasks.update
tasks.destroy
tasks.submit
tasks.approve
```

API:

```text
GET /api/v1/tasks
POST /api/v1/tasks
POST /api/v1/tasks/{task}/submit
POST /api/v1/tasks/{task}/approve
```
