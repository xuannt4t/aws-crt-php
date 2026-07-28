<?php

namespace App\Http\Controllers;

use App\Actions\User\CreateUserAction;
use App\Actions\User\DeleteUserAction;
use App\Actions\User\SetUserActiveStatusAction;
use App\Actions\User\UpdateUserAction;
use App\Enums\PermissionName;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\OrganizationUnit;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', User::class);

        return Inertia::render('Users/Index', [
            'users' => User::query()
                ->with('organizationUnit:id,name')
                ->with('roles:id,name')
                ->orderBy('name')
                ->get([
                    'id',
                    'name',
                    'email',
                    'organization_unit_id',
                    'is_active',
                    'employee_code',
                    'phone',
                    'job_title',
                ]),
            'organizationUnits' => OrganizationUnit::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', User::class);

        return Inertia::render('Users/Create', [
            'organizationUnits' => OrganizationUnit::query()->orderBy('name')->get(['id', 'name']),
            'roles' => $this->assignableRoles(),
        ]);
    }

    public function store(StoreUserRequest $request, CreateUserAction $action): RedirectResponse
    {
        $action->execute($request->user(), $request->validated());

        return Redirect::route('users.index')->with('success', 'Tạo người dùng thành công.');
    }

    public function edit(User $user): Response
    {
        $this->authorize('update', $user);

        return Inertia::render('Users/Edit', [
            'user' => $user->only([
                'id', 'name', 'email', 'organization_unit_id', 'employee_code', 'phone', 'job_title',
            ]),
            'organizationUnits' => OrganizationUnit::query()->orderBy('name')->get(['id', 'name']),
            'selectedRoles' => $user->getRoleNames(),
            'roles' => $this->assignableRoles(),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user, UpdateUserAction $action): RedirectResponse
    {
        $action->execute($request->user(), $user, $request->validated());

        return Redirect::route('users.index')->with('success', 'Cập nhật người dùng thành công.');
    }

    public function disable(User $user, SetUserActiveStatusAction $action): RedirectResponse
    {
        $this->authorize('disable', $user);

        $action->execute(request()->user(), $user, false);

        return Redirect::route('users.index')->with('success', 'Đã vô hiệu hoá người dùng.');
    }

    public function enable(User $user, SetUserActiveStatusAction $action): RedirectResponse
    {
        $this->authorize('disable', $user);

        $action->execute(request()->user(), $user, true);

        return Redirect::route('users.index')->with('success', 'Đã kích hoạt lại người dùng.');
    }

    public function destroy(User $user, DeleteUserAction $action): RedirectResponse
    {
        $this->authorize('delete', $user);

        $action->execute(request()->user(), $user);

        return Redirect::route('users.index')->with('success', 'Xoá người dùng thành công.');
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    private function assignableRoles(): array
    {
        if (! request()->user()->can(PermissionName::UserAssignRole->value)) {
            return [];
        }

        return Role::query()
            ->where('guard_name', 'web')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->toArray();
    }
}
