# Mobile API and OpenAPI Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Deliver a complete `/api/v1` JSON API for every current Dormida web module except notifications, with rotating 5-hour/30-day mobile tokens and OpenAPI documentation.

**Architecture:** Dedicated versioned API controllers and resources reuse the existing Actions, Policies, Form Requests, scopes, and services. Sanctum authenticates short-lived access tokens while a separate hashed refresh-session record provides rotation and revocation. Scramble derives OpenAPI 3.1 from routes, requests, resources, and focused PHPDoc.

**Tech Stack:** PHP 8.3, Laravel 11, Sanctum 4, Spatie Permission 6, Pest 3, dedoc/scramble, MySQL/SQLite test database.

## Global Constraints

- API prefix is exactly `/api/v1`.
- Access tokens expire after 18,000 seconds; refresh tokens expire after 2,592,000 seconds.
- Existing web routes and Inertia responses must remain backward compatible.
- Existing Actions, Policies, enums, visibility scopes, and validation rules remain authoritative.
- Notifications and native push are excluded.
- Responses use the `success`, `message`, `data`, optional `meta`, optional `links`, and optional `errors` contract from the approved spec.
- Default pagination is 20 and maximum `per_page` is 100.

---

### Task 1: API routing, response contract, and Sanctum foundation

**Files:**
- Modify: `bootstrap/app.php`
- Modify: `app/Models/User.php`
- Create: `routes/api.php`
- Create: `app/Http/Responses/ApiResponse.php`
- Create: `app/Http/Middleware/EnsureApiUserIsActive.php`
- Create: `tests/Feature/Api/V1/ApiFoundationTest.php`

**Interfaces:**
- Produces: `ApiResponse::success(mixed $data, string $message = 'Thành công', int $status = 200)` and `ApiResponse::paginated(LengthAwarePaginator $paginator, JsonResource|string $resource, string $message = 'Thành công')`.
- Produces: route middleware alias `api.active` and authenticated `/api/v1` route group.

- [ ] Write feature tests proving `/api/v1/auth/me` returns JSON `401` without a token and a health/test response follows the approved envelope.
- [ ] Run `php artisan test tests/Feature/Api/V1/ApiFoundationTest.php` and confirm it fails because API routing is absent.
- [ ] Register `api: __DIR__.'/../routes/api.php'`, add `HasApiTokens` to `User`, implement the response helper and API active-account middleware.
- [ ] Add JSON exception rendering for API authentication, authorization, model-not-found, validation, and domain conflict responses.
- [ ] Run the focused test and existing auth tests.
- [ ] Commit with `feat(api): add versioned api foundation`.

### Task 2: Rotating mobile authentication

**Files:**
- Create: `database/migrations/2026_08_11_000000_create_api_refresh_sessions_table.php`
- Create: `app/Models/ApiRefreshSession.php`
- Create: `app/Http/Requests/Api/V1/LoginRequest.php`
- Create: `app/Http/Requests/Api/V1/RefreshTokenRequest.php`
- Create: `app/Http/Controllers/Api/V1/AuthController.php`
- Create: `app/Services/ApiTokenService.php`
- Modify: `config/sanctum.php`
- Modify: `routes/console.php`
- Modify: `routes/api.php`
- Create: `tests/Feature/Api/V1/AuthenticationTest.php`

**Interfaces:**
- Produces: `ApiTokenService::issue(User $user, string $deviceName): array`, `rotate(string $plainRefreshToken): array`, `revokeCurrent(Request $request): void`, and `revokeAll(User $user): void`.
- Token payload keys: `token_type`, `access_token`, `access_token_expires_in`, `refresh_token`, `refresh_token_expires_in`.

- [ ] Write failing tests for login, invalid credentials, inactive account, five-hour access expiry, 30-day refresh expiry, rotation, replay rejection, multiple devices, logout, and logout-all.
- [ ] Run the authentication test and confirm missing routes/models cause failure.
- [ ] Add the refresh-session migration with `user_id`, `personal_access_token_id`, `name`, unique `token_hash`, `expires_at`, `last_used_at`, `revoked_at`, timestamps, and indexes.
- [ ] Implement issuance using 64 random bytes, SHA-256 storage, Sanctum's per-token `expiresAt`, a database transaction, and constant-time lookup by hash.
- [ ] Implement rotation by locking the session row, rejecting expired/revoked sessions, revoking the old access token/session, and issuing a replacement pair.
- [ ] Add rate-limited auth routes and scheduled pruning for expired Sanctum and refresh records.
- [ ] Run focused authentication and web authentication tests.
- [ ] Commit with `feat(api): add rotating mobile authentication`.

### Task 3: API resources and profile/meta endpoints

**Files:**
- Create: `app/Http/Resources/Api/V1/UserResource.php`
- Create: `app/Http/Resources/Api/V1/OrganizationUnitResource.php`
- Create: `app/Http/Resources/Api/V1/ProjectResource.php`
- Create: `app/Http/Resources/Api/V1/TaskResource.php`
- Create: `app/Http/Resources/Api/V1/TaskRecurrenceResource.php`
- Create: `app/Http/Controllers/Api/V1/ProfileController.php`
- Create: `app/Http/Controllers/Api/V1/MetaController.php`
- Create: `app/Http/Requests/Api/V1/UpdatePasswordRequest.php`
- Modify: `routes/api.php`
- Create: `tests/Feature/Api/V1/ProfileMetaTest.php`

**Interfaces:**
- Resources produce stable primitive fields, loaded relationships, ISO 8601 timestamps, and per-resource `permissions` objects.
- `GET /meta` returns enum `{value,label}` arrays and size/validation limits; searchable option endpoints return paginated visible users/projects/units.

- [ ] Write failing tests for profile read/update, avatar upload, password change, metadata authorization, search, and pagination caps.
- [ ] Run the focused test and confirm failures.
- [ ] Implement resources without exposing password, remember token, internal storage paths, or refresh hashes.
- [ ] Implement profile endpoints by reusing `ProfileUpdateRequest`/`UpdateProfileAction` and password validation semantics.
- [ ] Implement metadata using existing enums, `UserOptions`, active organization units, visible open projects, and policy checks.
- [ ] Run focused tests and web profile tests.
- [ ] Commit with `feat(api): add profile resources and metadata`.

### Task 4: Task read/write and workflow API

**Files:**
- Create: `app/Http/Controllers/Api/V1/TaskController.php`
- Create: `app/Http/Resources/Api/V1/TaskDetailResource.php`
- Create: `app/Http/Resources/Api/V1/TaskCommentResource.php`
- Create: `app/Http/Resources/Api/V1/TaskAttachmentResource.php`
- Create: `app/Http/Resources/Api/V1/TaskActivityResource.php`
- Modify: `routes/api.php`
- Create: `tests/Feature/Api/V1/TaskApiTest.php`

**Interfaces:**
- Consumes existing task Form Requests and all `App\Actions\Task` classes.
- Produces task CRUD, department views, state transitions, progress, quantity, comment, attachment upload/download/delete endpoints.

- [ ] Write failing tests for visible list filters, department scope, detail payload, CRUD, every transition, progress/quantity modes, comments, multipart attachments, scoped downloads, and authorization failures.
- [ ] Run the focused tests and confirm routes are missing.
- [ ] Extract or reproduce query composition from the web controller while retaining `Task::visibleTo`, summaries, filter semantics, eager loading, and the 20/100 pagination rules.
- [ ] Implement mutations as thin adapters around existing Actions and return refreshed resources with `200`, `201`, or `204` as appropriate.
- [ ] Map invalid status transitions and locked-project operations to `409` while leaving validation failures as `422`.
- [ ] Run API task tests and the complete existing task suite.
- [ ] Commit with `feat(api): expose task workflows`.

### Task 5: Recurring task API

**Files:**
- Create: `app/Http/Controllers/Api/V1/TaskRecurrenceController.php`
- Create: `app/Http/Resources/Api/V1/TaskRecurrenceDetailResource.php`
- Modify: `routes/api.php`
- Create: `tests/Feature/Api/V1/TaskRecurrenceApiTest.php`

**Interfaces:**
- Consumes recurrence Form Requests, Actions, `TaskRecurrence::visibleTo`, and `RecurrenceSchedule`.
- Produces CRUD/toggle endpoints with cadence, next occurrence, generated tasks, and permission fields.

- [ ] Write failing tests for list filters, visibility, detail schedule fields, CRUD, toggle, closed-project constraints, and forbidden responses.
- [ ] Run focused tests and confirm failures.
- [ ] Implement list/detail queries matching the web controller and mutations through existing Actions.
- [ ] Run API recurrence tests and all existing recurrence tests.
- [ ] Commit with `feat(api): expose recurring tasks`.

### Task 6: Project and member API

**Files:**
- Create: `app/Http/Controllers/Api/V1/ProjectController.php`
- Create: `app/Http/Controllers/Api/V1/ProjectMemberController.php`
- Create: `app/Http/Resources/Api/V1/ProjectDetailResource.php`
- Create: `app/Http/Resources/Api/V1/ProjectMemberResource.php`
- Modify: `routes/api.php`
- Create: `tests/Feature/Api/V1/ProjectApiTest.php`

**Interfaces:**
- Consumes project/member Form Requests, Actions, Policies, `Project::visibleTo`, and `Task::visibleTo`.
- Produces project CRUD/close and member create/update/delete endpoints.

- [ ] Write failing tests for filters, calculated progress, scoped task/member detail, CRUD, close conflicts, member role/visibility changes, and data-scope authorization.
- [ ] Run focused tests and confirm failures.
- [ ] Implement read queries matching web semantics and write endpoints as Action adapters.
- [ ] Run API project tests and all existing project tests.
- [ ] Commit with `feat(api): expose projects and members`.

### Task 7: Organization, users, permissions, and audit API

**Files:**
- Create: `app/Http/Controllers/Api/V1/OrganizationUnitController.php`
- Create: `app/Http/Controllers/Api/V1/UserController.php`
- Create: `app/Http/Controllers/Api/V1/PermissionMatrixController.php`
- Create: `app/Http/Controllers/Api/V1/AuditLogController.php`
- Create: `app/Http/Resources/Api/V1/AuditLogResource.php`
- Modify: `routes/api.php`
- Create: `tests/Feature/Api/V1/AdministrationApiTest.php`

**Interfaces:**
- Consumes existing administrative Form Requests, Policies, Actions, and permission matrix service/action.
- Produces CRUD/enable/disable, permission matrix GET/PUT, and filtered audit log endpoints.

- [ ] Write failing tests for every admin route, tree integrity, user role assignment, session revocation on disable/delete, permission matrix invariants, audit filters, and forbidden access.
- [ ] Run focused tests and confirm failures.
- [ ] Implement thin JSON controllers using existing domain code and stable resources.
- [ ] Ensure disabling/deleting a user revokes Sanctum tokens and refresh sessions in the same operation boundary.
- [ ] Run focused tests and existing organization/user/permission/audit suites.
- [ ] Commit with `feat(api): expose administration modules`.

### Task 8: OpenAPI 3.1 and interactive docs

**Files:**
- Modify: `composer.json`
- Modify: `composer.lock`
- Create: `config/scramble.php`
- Modify: `app/Providers/AppServiceProvider.php`
- Add focused OpenAPI PHPDoc to API controllers/resources/requests where inference is incomplete.
- Create: `tests/Feature/Api/V1/OpenApiDocumentationTest.php`

**Interfaces:**
- Produces `/docs/api` and `/docs/api.json` with Bearer auth, unique operation IDs, schemas, examples, multipart upload, binary download, and standardized errors.

- [ ] Write a failing test that requests the OpenAPI document and asserts every named `/api/v1` operation is present with the expected security and response schemas.
- [ ] Run the focused test and confirm the documentation routes/package are absent.
- [ ] Install `dedoc/scramble`, publish/configure API path matching, and allow production docs through an explicit gate.
- [ ] Add exact summaries, tags, examples, and manual schemas only where Scramble cannot infer the contract.
- [ ] Run the OpenAPI test and inspect generated JSON for missing operation IDs or schemas.
- [ ] Commit with `docs(api): publish openapi documentation`.

### Task 9: Full verification and deployment handoff

**Files:**
- Modify: `.env.example`
- Create: `docs/api-deployment.md`

**Interfaces:**
- Documents `API_ACCESS_TOKEN_TTL_MINUTES=300`, `API_REFRESH_TOKEN_TTL_DAYS=30`, migration/cache commands, docs URLs, and smoke-test commands.

- [ ] Add environment defaults and production deployment/rollback instructions without real secrets.
- [ ] Run `vendor/bin/pint --test` and fix only API-related formatting failures.
- [ ] Run `php artisan test` and resolve all regressions.
- [ ] Run `php artisan route:list --path=api/v1` and verify the endpoint surface.
- [ ] Generate/request `/docs/api.json` and verify valid OpenAPI 3.1 output.
- [ ] Run `npm run build` to prove the existing web build remains intact.
- [ ] Commit with `docs(api): add deployment handoff`.
