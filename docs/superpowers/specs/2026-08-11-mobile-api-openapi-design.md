# Mobile API and OpenAPI Design

## Goal

Provide a stable, versioned JSON API that exposes every current Dormida web capability to a first-party mobile application, except notifications, which are deferred to a later phase. Publish an accurate OpenAPI 3.1 document and interactive API documentation so the mobile team can integrate without reading the Laravel implementation.

## Scope

The first release covers authentication, profile management, tasks and their workflow, comments, attachments, recurring tasks, projects and project members, organization units, users, the permission matrix, audit logs, and shared form metadata. It preserves the authorization, visibility, validation, audit, and state-transition rules already used by the web application.

Realtime, in-app notifications, FCM/APNs push notifications, and notification preferences are explicitly out of scope for this release.

## Architecture

- All endpoints live below `/api/v1` and are registered from `routes/api.php`.
- API controllers live under `App\Http\Controllers\Api\V1` and return JSON only. Existing Inertia controllers remain unchanged.
- Existing Actions, Policies, enums, validation rules, and domain services remain the single source of business behavior for web and API.
- Form Requests remain responsible for authorization and input validation. API Resources define the public response contract and prevent accidental exposure of model fields.
- Protected routes use `auth:sanctum` and the existing active-account check adapted to return JSON `401`/`403` responses for API requests.
- Public identifiers remain the existing integer model IDs. A future API version may introduce UUIDs without changing v1.

## Authentication and Sessions

The API uses a first-party mobile session design built on Laravel Sanctum:

- `access_token` is a Sanctum Bearer token with a five-hour lifetime.
- `refresh_token` is an opaque random credential with a 30-day lifetime.
- Only a cryptographic hash of each refresh token is stored.
- Every successful refresh rotates both credentials and revokes the old pair.
- Reuse of a rotated or revoked refresh token revokes that device session and returns `401`.
- Each login creates a separate named device session using the submitted `device_name`.
- Logout revokes the current device session. Logout-all revokes every API session for the user.
- Disabled or deleted users cannot log in or refresh, and their active API sessions are rejected.
- Login is rate-limited by normalized email and client IP. Refresh is rate-limited by client IP and session identifier.

Authentication endpoints:

| Method | Path | Purpose |
|---|---|---|
| POST | `/api/v1/auth/login` | Validate email/password/device name and issue token pair |
| POST | `/api/v1/auth/refresh` | Rotate a valid refresh token and issue a new pair |
| POST | `/api/v1/auth/logout` | Revoke the current device session |
| POST | `/api/v1/auth/logout-all` | Revoke all API sessions owned by the user |
| GET | `/api/v1/auth/me` | Return the authenticated user, roles, permissions, and organization unit |

Login and refresh return `token_type: "Bearer"`, `access_token`, `access_token_expires_in: 18000`, `refresh_token`, and `refresh_token_expires_in: 2592000`.

## Response Contract

Successful single-resource responses use:

```json
{
  "success": true,
  "message": "Thành công",
  "data": {}
}
```

Paginated responses add a `meta` object containing `current_page`, `per_page`, `last_page`, `total`, `from`, and `to`, plus `links` containing `first`, `last`, `prev`, and `next`. The default page size is 20 and the maximum accepted `per_page` is 100.

Errors use one stable shape:

```json
{
  "success": false,
  "message": "Dữ liệu không hợp lệ.",
  "errors": {
    "field": ["Thông báo lỗi."]
  }
}
```

Status codes are `400` for malformed requests, `401` for missing/expired credentials, `403` for authorization failures or disabled accounts, `404` for unavailable resources, `409` for invalid workflow/state conflicts, `422` for validation failures, `429` for rate limiting, and `500` for unexpected failures. Production error responses never expose stack traces or secrets.

Dates and timestamps use ISO 8601. Enum values use their existing backend string values. Descriptions returned to clients are sanitized using the same service used by the web detail view.

## Endpoint Surface

### Profile

| Method | Path | Purpose |
|---|---|---|
| GET | `/api/v1/profile` | Current profile |
| PATCH | `/api/v1/profile` | Update profile fields and optional avatar |
| PUT | `/api/v1/profile/password` | Change password and optionally revoke other sessions |

### Tasks

| Method | Path | Purpose |
|---|---|---|
| GET | `/api/v1/tasks` | Paginated visible tasks with the web filters and search |
| POST | `/api/v1/tasks` | Create a task |
| GET | `/api/v1/tasks/{task}` | Task detail, relations, permissions, history, comments, attachments, and activities |
| PATCH | `/api/v1/tasks/{task}` | Update a task |
| DELETE | `/api/v1/tasks/{task}` | Soft-delete a task |
| PATCH | `/api/v1/tasks/{task}/dispatch` | Dispatch an eligible task |
| PATCH | `/api/v1/tasks/{task}/start` | Start an eligible task |
| PATCH | `/api/v1/tasks/{task}/submit` | Submit an eligible task for approval |
| PATCH | `/api/v1/tasks/{task}/recall` | Recall an eligible task |
| PATCH | `/api/v1/tasks/{task}/approve` | Approve a submitted task |
| PATCH | `/api/v1/tasks/{task}/reject` | Reject a submitted task with the existing reason rules |
| PATCH | `/api/v1/tasks/{task}/progress` | Update manual progress |
| PATCH | `/api/v1/tasks/{task}/quantity` | Update actual quantity and derived progress |
| POST | `/api/v1/tasks/{task}/comments` | Add a comment |
| POST | `/api/v1/tasks/{task}/attachments` | Upload attachments using existing size/type limits |
| GET | `/api/v1/tasks/{task}/attachments/{attachment}` | Authorized attachment download |
| DELETE | `/api/v1/tasks/{task}/attachments/{attachment}` | Delete an attachment |
| GET | `/api/v1/task-departments` | Department summary used by the department task view |
| GET | `/api/v1/task-departments/{organizationUnit}` | Tasks scoped to one department |

Task list filters mirror `IndexTaskRequest`, including search, status/bucket, priority, organization unit, assignee, project, due-date range, and the current web screen/context semantics. Responses include explicit `permissions` booleans for actions the current user may perform.

### Recurring Tasks

| Method | Path | Purpose |
|---|---|---|
| GET | `/api/v1/task-recurrences` | Paginated visible recurrence definitions |
| POST | `/api/v1/task-recurrences` | Create a recurrence |
| GET | `/api/v1/task-recurrences/{taskRecurrence}` | Recurrence detail and generated-task summary |
| PATCH | `/api/v1/task-recurrences/{taskRecurrence}` | Update a recurrence |
| DELETE | `/api/v1/task-recurrences/{taskRecurrence}` | Delete a recurrence |
| PATCH | `/api/v1/task-recurrences/{taskRecurrence}/toggle` | Enable or disable generation |

### Projects and Members

| Method | Path | Purpose |
|---|---|---|
| GET | `/api/v1/projects` | Paginated visible projects with web filters |
| POST | `/api/v1/projects` | Create a project |
| GET | `/api/v1/projects/{project}` | Project, progress, members, tasks, and permissions |
| PATCH | `/api/v1/projects/{project}` | Update an open project |
| DELETE | `/api/v1/projects/{project}` | Delete an eligible project |
| PATCH | `/api/v1/projects/{project}/close` | Close a project using existing validation |
| POST | `/api/v1/projects/{project}/members` | Add a member |
| PATCH | `/api/v1/projects/{project}/members/{member}` | Change member role/task visibility |
| DELETE | `/api/v1/projects/{project}/members/{member}` | Remove a member |

### Organization and Administration

| Method | Path | Purpose |
|---|---|---|
| GET | `/api/v1/organization-units` | Authorized unit list/tree |
| POST | `/api/v1/organization-units` | Create a unit |
| GET | `/api/v1/organization-units/{organizationUnit}` | Unit detail |
| PATCH | `/api/v1/organization-units/{organizationUnit}` | Update a unit |
| DELETE | `/api/v1/organization-units/{organizationUnit}` | Delete an eligible unit |
| GET | `/api/v1/users` | Paginated authorized user list |
| POST | `/api/v1/users` | Create a user |
| GET | `/api/v1/users/{user}` | User detail |
| PATCH | `/api/v1/users/{user}` | Update user fields and roles |
| DELETE | `/api/v1/users/{user}` | Delete an eligible user |
| PATCH | `/api/v1/users/{user}/enable` | Enable a user |
| PATCH | `/api/v1/users/{user}/disable` | Disable a user and invalidate API sessions |
| GET | `/api/v1/permission-matrix` | Read roles, permissions, and matrix |
| PUT | `/api/v1/permission-matrix` | Update the permission matrix |
| GET | `/api/v1/audit-logs` | Paginated searchable/filterable audit events |

### Shared Metadata

`GET /api/v1/meta` returns only values visible or useful to the authenticated caller: enums with labels, permitted roles, organization-unit options, assignable users, visible projects, and limits needed by mobile forms. Large option collections accept search and pagination through dedicated `/api/v1/meta/users`, `/api/v1/meta/projects`, and `/api/v1/meta/organization-units` endpoints rather than being embedded without a limit.

## Authorization and Data Visibility

Every model query applies the existing policy and `visibleTo` scope where one exists. Route-model binding alone is never treated as authorization. Nested member and attachment routes use scoped binding and verify that the child belongs to the parent. API Resources expose per-resource action permissions so the app can hide unavailable controls, while the server remains authoritative.

Write endpoints call the existing Actions so audit logs, activity records, status history, queued broadcasts, and project/task constraints stay identical to web behavior. The API must not duplicate those transitions in controllers.

## OpenAPI and Interactive Documentation

The project will use `dedoc/scramble`, which supports Laravel 10+ and generates OpenAPI 3.1 from Laravel routes, Form Requests, and API Resources. Documentation is served at:

- `/docs/api` for the interactive UI.
- `/docs/api.json` for OpenAPI client generation and import into Swagger/Postman tooling.

The document defines the Bearer security scheme, tags endpoints by module, describes request/response schemas, documents pagination and standard errors, and provides realistic examples. Auth endpoints, multipart attachment upload, binary download, enum fields, and workflow conflict responses receive explicit documentation where static inference is insufficient.

The documentation contains no credentials or production data. Its routes may be public so the mobile team can integrate, while all functional endpoints retain normal authentication and authorization.

## Testing and Acceptance

- Pest feature tests cover every route's success path, unauthenticated response, forbidden response, validation failure, and principal workflow conflict where applicable.
- Authentication tests cover five-hour access expiry, 30-day refresh expiry, rotation, replay rejection, current-session logout, logout-all, multiple devices, rate limiting, and disabled users.
- Contract tests assert the response envelope, pagination metadata, ISO 8601 dates, resource field names, and stable HTTP status codes.
- Existing web tests must remain green, demonstrating that the API does not change Inertia behavior.
- OpenAPI generation must complete without errors, expose every `/api/v1` route, and contain no undocumented operation IDs or schemas.
- A smoke test logs in, refreshes, exercises representative read/write endpoints in every module, downloads an authorized attachment, and logs out.

## Deployment and Compatibility

Database migrations add refresh-session storage and Sanctum personal access token storage if it is not already installed. Deployment runs migrations before caches are rebuilt. API configuration exposes access and refresh lifetimes through environment-backed config with defaults fixed at five hours and 30 days. The existing web session login, Nginx site, queue workers, Reverb process, scheduler, and TLS configuration remain unchanged.

The mobile app integrates only with `/api/v1`; breaking contract changes require `/api/v2`. Additive fields may be introduced in v1, but existing fields, enum values, semantics, and status codes remain backward compatible.
