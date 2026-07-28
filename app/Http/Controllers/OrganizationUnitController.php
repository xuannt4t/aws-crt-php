<?php

namespace App\Http\Controllers;

use App\Actions\OrganizationUnit\DeleteOrganizationUnitAction;
use App\Actions\OrganizationUnit\UpdateOrganizationUnitAction;
use App\Http\Requests\StoreOrganizationUnitRequest;
use App\Http\Requests\UpdateOrganizationUnitRequest;
use App\Models\OrganizationUnit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationUnitController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', OrganizationUnit::class);

        return Inertia::render('OrganizationUnits/Index', [
            'organizationUnits' => OrganizationUnit::query()
                ->orderBy('name')
                ->get(['id', 'parent_id', 'name', 'code', 'is_active']),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', OrganizationUnit::class);

        return Inertia::render('OrganizationUnits/Create', [
            'organizationUnits' => OrganizationUnit::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(StoreOrganizationUnitRequest $request): RedirectResponse
    {
        OrganizationUnit::create($request->validated());

        return Redirect::route('organization-units.index')->with('success', 'Tạo đơn vị thành công.');
    }

    public function edit(OrganizationUnit $organizationUnit): Response
    {
        $this->authorize('update', $organizationUnit);

        return Inertia::render('OrganizationUnits/Edit', [
            'organizationUnit' => $organizationUnit,
            'organizationUnits' => OrganizationUnit::query()
                ->where('id', '!=', $organizationUnit->id)
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    public function update(
        UpdateOrganizationUnitRequest $request,
        OrganizationUnit $organizationUnit,
        UpdateOrganizationUnitAction $action
    ): RedirectResponse {
        $action->execute($organizationUnit, $request->validated());

        return Redirect::route('organization-units.index')->with('success', 'Cập nhật đơn vị thành công.');
    }

    public function destroy(OrganizationUnit $organizationUnit, DeleteOrganizationUnitAction $action): RedirectResponse
    {
        $this->authorize('delete', $organizationUnit);

        $action->execute(request()->user(), $organizationUnit);

        return Redirect::route('organization-units.index')->with('success', 'Xoá đơn vị thành công.');
    }
}
