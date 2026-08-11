<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\TaskRecurrence\CreateTaskRecurrenceAction;
use App\Actions\TaskRecurrence\DeleteTaskRecurrenceAction;
use App\Actions\TaskRecurrence\ToggleTaskRecurrenceAction;
use App\Actions\TaskRecurrence\UpdateTaskRecurrenceAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\IndexTaskRecurrenceRequest;
use App\Http\Requests\StoreTaskRecurrenceRequest;
use App\Http\Requests\UpdateTaskRecurrenceRequest;
use App\Http\Resources\Api\V1\TaskRecurrenceResource;
use App\Http\Resources\Api\V1\TaskResource;
use App\Http\Responses\ApiResponse;
use App\Models\TaskRecurrence;
use App\Support\RecurrenceSchedule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class TaskRecurrenceController extends Controller
{
    public function index(IndexTaskRecurrenceRequest $request, RecurrenceSchedule $schedule): JsonResponse
    {
        $filters = $request->validated();
        $recurrences = TaskRecurrence::query()
            ->visibleTo($request->user())
            ->with(['organizationUnit:id,name', 'project:id,name,code', 'creator:id,name,avatar_path', 'assignee:id,name,avatar_path'])
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where('title', 'like', "%{$search}%"))
            ->when($filters['organization_unit_id'] ?? null, fn (Builder $query, int $id) => $query->where('organization_unit_id', $id))
            ->when($filters['assignee_id'] ?? null, fn (Builder $query, int $id) => $query->where('assignee_id', $id))
            ->when($filters['frequency'] ?? null, fn (Builder $query, string $frequency) => $query->where('frequency', $frequency))
            ->when(array_key_exists('is_active', $filters) && $filters['is_active'] !== null, fn (Builder $query) => $query->where('is_active', $request->boolean('is_active')))
            ->latest('id')
            ->paginate($this->perPage($request));

        $recurrences->getCollection()->each(fn (TaskRecurrence $recurrence) => $this->decorate($recurrence, $schedule));

        return ApiResponse::paginated(
            $recurrences,
            TaskRecurrenceResource::collection($recurrences->getCollection())->resolve($request),
        );
    }

    public function show(Request $request, TaskRecurrence $taskRecurrence, RecurrenceSchedule $schedule): JsonResponse
    {
        $this->authorize('view', $taskRecurrence);
        $this->loadRecurrence($taskRecurrence);
        $this->decorate($taskRecurrence, $schedule);
        $tasks = $taskRecurrence->tasks()
            ->visibleTo($request->user())
            ->with(['organizationUnit:id,name', 'creator:id,name,avatar_path', 'assignee:id,name,avatar_path', 'project:id,name,code,status'])
            ->latest('id')
            ->paginate($this->perPage($request), pageName: 'tasks_page');

        return ApiResponse::success([
            'recurrence' => TaskRecurrenceResource::make($taskRecurrence)->resolve($request),
            'tasks' => [
                'data' => TaskResource::collection($tasks->getCollection())->resolve($request),
                'meta' => [
                    'current_page' => $tasks->currentPage(),
                    'per_page' => $tasks->perPage(),
                    'last_page' => $tasks->lastPage(),
                    'total' => $tasks->total(),
                ],
            ],
        ]);
    }

    public function store(
        StoreTaskRecurrenceRequest $request,
        CreateTaskRecurrenceAction $action,
        RecurrenceSchedule $schedule,
    ): JsonResponse {
        $recurrence = $action->execute($request->user(), $request->validated());

        return ApiResponse::success($this->resource($request, $recurrence, $schedule), 'Tạo công việc định kỳ thành công.', 201);
    }

    public function update(
        UpdateTaskRecurrenceRequest $request,
        TaskRecurrence $taskRecurrence,
        UpdateTaskRecurrenceAction $action,
        RecurrenceSchedule $schedule,
    ): JsonResponse {
        $recurrence = $action->execute($request->user(), $taskRecurrence, $request->validated());

        return ApiResponse::success($this->resource($request, $recurrence, $schedule), 'Cập nhật công việc định kỳ thành công.');
    }

    public function destroy(
        Request $request,
        TaskRecurrence $taskRecurrence,
        DeleteTaskRecurrenceAction $action,
    ): JsonResponse {
        $this->authorize('delete', $taskRecurrence);
        $action->execute($request->user(), $taskRecurrence);

        return ApiResponse::success(null, 'Xóa công việc định kỳ thành công.');
    }

    public function toggle(
        Request $request,
        TaskRecurrence $taskRecurrence,
        ToggleTaskRecurrenceAction $action,
        RecurrenceSchedule $schedule,
    ): JsonResponse {
        $this->authorize('toggle', $taskRecurrence);
        $recurrence = $action->execute($request->user(), $taskRecurrence);

        return ApiResponse::success($this->resource($request, $recurrence, $schedule), 'Đã cập nhật trạng thái công việc định kỳ.');
    }

    private function resource(Request $request, TaskRecurrence $recurrence, RecurrenceSchedule $schedule): array
    {
        $this->loadRecurrence($recurrence);
        $this->decorate($recurrence, $schedule);

        return TaskRecurrenceResource::make($recurrence)->resolve($request);
    }

    private function loadRecurrence(TaskRecurrence $recurrence): void
    {
        $recurrence->load(['organizationUnit:id,name', 'project:id,name,code', 'creator:id,name,avatar_path', 'assignee:id,name,avatar_path']);
    }

    private function decorate(TaskRecurrence $recurrence, RecurrenceSchedule $schedule): void
    {
        $recurrence->setAttribute('cadence', $schedule->describe($recurrence));
        $recurrence->setAttribute(
            'next_occurrence',
            $recurrence->is_active ? $schedule->nextOccurrenceAfter($recurrence, now())?->toDateString() : null,
        );
    }

    private function perPage(Request $request): int
    {
        return min(max($request->integer('per_page', 20), 1), 100);
    }
}
