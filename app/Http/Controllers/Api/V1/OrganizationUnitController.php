<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\OrganizationUnit\DeleteOrganizationUnitAction;
use App\Actions\OrganizationUnit\UpdateOrganizationUnitAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrganizationUnitRequest;
use App\Http\Requests\UpdateOrganizationUnitRequest;
use App\Http\Resources\Api\V1\OrganizationUnitResource;
use App\Http\Responses\ApiResponse;
use App\Models\OrganizationUnit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class OrganizationUnitController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', OrganizationUnit::class);
        $units = OrganizationUnit::query()->with('parent:id,name')->orderBy('name')->get();

        return ApiResponse::success(OrganizationUnitResource::collection($units)->resolve($request));
    }

    public function show(Request $request, OrganizationUnit $organizationUnit): JsonResponse
    {
        $this->authorize('view', $organizationUnit);

        return ApiResponse::success(OrganizationUnitResource::make($organizationUnit->load('parent:id,name'))->resolve($request));
    }

    public function store(StoreOrganizationUnitRequest $request): JsonResponse
    {
        $unit = OrganizationUnit::query()->create($request->validated());

        return ApiResponse::success(OrganizationUnitResource::make($unit)->resolve($request), 'Tạo đơn vị thành công.', 201);
    }

    public function update(UpdateOrganizationUnitRequest $request, OrganizationUnit $organizationUnit, UpdateOrganizationUnitAction $action): JsonResponse
    {
        $unit = $action->execute($organizationUnit, $request->validated());

        return ApiResponse::success(OrganizationUnitResource::make($unit->load('parent:id,name'))->resolve($request), 'Cập nhật đơn vị thành công.');
    }

    public function destroy(Request $request, OrganizationUnit $organizationUnit, DeleteOrganizationUnitAction $action): JsonResponse
    {
        $this->authorize('delete', $organizationUnit);
        $action->execute($request->user(), $organizationUnit);

        return ApiResponse::success(null, 'Xóa đơn vị thành công.');
    }
}
