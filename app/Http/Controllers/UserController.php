<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\OrganizationUnit;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', User::class);

        return Inertia::render('Users/Index', [
            'users' => User::query()
                ->with('organizationUnit:id,name')
                ->orderBy('name')
                ->get(['id', 'name', 'email', 'organization_unit_id', 'is_active', 'is_system_admin']),
            'organizationUnits' => OrganizationUnit::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', User::class);

        return Inertia::render('Users/Create', [
            'organizationUnits' => OrganizationUnit::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['password'] = Hash::make($data['password']);

        User::create($data);

        return Redirect::route('users.index')->with('success', 'Tạo người dùng thành công.');
    }

    public function edit(User $user): Response
    {
        $this->authorize('update', $user);

        return Inertia::render('Users/Edit', [
            'user' => $user->only([
                'id', 'name', 'email', 'organization_unit_id', 'employee_code', 'phone', 'job_title', 'is_system_admin',
            ]),
            'organizationUnits' => OrganizationUnit::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $user->update($request->validated());

        return Redirect::route('users.index')->with('success', 'Cập nhật người dùng thành công.');
    }

    public function disable(User $user): RedirectResponse
    {
        $this->authorize('disable', $user);

        $user->forceFill(['is_active' => false, 'remember_token' => null])->save();

        return Redirect::route('users.index')->with('success', 'Đã vô hiệu hoá người dùng.');
    }

    public function enable(User $user): RedirectResponse
    {
        $this->authorize('disable', $user);

        $user->update(['is_active' => true]);

        return Redirect::route('users.index')->with('success', 'Đã kích hoạt lại người dùng.');
    }

    public function destroy(User $user): RedirectResponse
    {
        $this->authorize('delete', $user);

        $user->delete();

        return Redirect::route('users.index')->with('success', 'Xoá người dùng thành công.');
    }
}
