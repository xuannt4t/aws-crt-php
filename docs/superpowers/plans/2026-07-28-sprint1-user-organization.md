# Sprint 1, Đợt 1 — User + Organization Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the organization tree, extend the user model with identity fields, add temporary `is_system_admin`-based authorization, remove public registration, seed a bootstrap admin, and ship full backend + Inertia UI for managing both — on top of the Sprint 0 Laravel 11 + Breeze foundation.

**Architecture:** Standard Route → Controller → FormRequest → (Action for real business rules) → Model → Inertia Response flow per `prompts/02_ARCHITECTURE.md`. `OrganizationUnit` is a self-referencing tree (`parent_id`). `User` gains `organization_unit_id`, `is_system_admin`, `is_active`, and profile fields. Policies gate mutations behind `is_system_admin` until the Role & Permission sub-project replaces the check inside them.

**Tech Stack:** Laravel 11, Pest, MySQL, Inertia + Vue 3 + TypeScript, PrimeVue (TreeTable, DataTable, Button).

## Global Constraints

- Repo root: `c:/laragon/www/dormida-work-ai-kit` (Git Bash: `/c/laragon/www/dormida-work-ai-kit`).
- PHP binary: `/c/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe`. Composer: same PHP + `/c/laragon/bin/composer/composer.phar`. Use full paths — nothing is on system PATH in this shell.
- Local MySQL `dormida_work` and local Redis must be running (start `redis-server.exe` in background and confirm MySQL is up before running any `artisan migrate`/`pest` command — see Sprint 0 notes if either is down).
- Test framework: Pest, `RefreshDatabase` already applied to everything under `tests/Feature` via `tests/Pest.php` — put every DB-touching test there (not `tests/Unit`).
- Every DB write must go through validated FormRequests; controllers stay thin (no business logic, no raw queries) per `prompts/02_ARCHITECTURE.md` §3.
- Authorization is never skipped: every mutating controller action calls `$this->authorize(...)` or is gated by a FormRequest's `authorize()`.
- `organization_units` and `users` both use `SoftDeletes` (`prompts/03_DATABASE.md` §10 — Organization unit and User are both on the soft-delete list).
- `is_active` on `users` is distinct from soft delete: it blocks login without deleting the record.
- Route resource `organization-units` is remapped to the `organizationUnit` (camelCase) parameter name so it matches the controller's `$organizationUnit` variable and FormRequest's `$this->route('organizationUnit')` calls exactly — every task touching these routes/requests must use `organizationUnit`, not `organization_unit`.
- Every task ends with a commit. Follow the project's commit style (`type: short description`, see `git log --oneline`).

---

### Task 1: `OrganizationUnit` model, migration, factory

**Files:**
- Create: `database/migrations/{timestamp}_create_organization_units_table.php`
- Create: `app/Models/OrganizationUnit.php`
- Create: `database/factories/OrganizationUnitFactory.php`
- Test: `tests/Feature/OrganizationUnit/OrganizationUnitModelTest.php`

**Interfaces:**
- Produces: `App\Models\OrganizationUnit` with `parent(): BelongsTo`, `children(): HasMany`, `users(): HasMany`, fillable `[parent_id, name, code, is_active]`, cast `is_active` to boolean, `SoftDeletes`. `OrganizationUnit::factory()` usable by every later task.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/OrganizationUnit/OrganizationUnitModelTest.php`:
```php
<?php

use App\Models\OrganizationUnit;

test('an organization unit can have a parent and children', function () {
    $parent = OrganizationUnit::factory()->create();
    $child = OrganizationUnit::factory()->create(['parent_id' => $parent->id]);

    expect($child->parent->is($parent))->toBeTrue();
    expect($parent->children)->toHaveCount(1);
    expect($parent->children->first()->is($child))->toBeTrue();
});

test('an organization unit is soft deleted, not removed from the database', function () {
    $unit = OrganizationUnit::factory()->create();

    $unit->delete();

    $this->assertSoftDeleted($unit);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `"/c/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe" vendor/bin/pest tests/Feature/OrganizationUnit/OrganizationUnitModelTest.php`
Expected: FAIL — `Class "App\Models\OrganizationUnit" not found`.

- [ ] **Step 3: Create the migration**

Run: `"/c/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe" artisan make:migration create_organization_units_table`

Edit the generated file to:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('organization_units')->nullOnDelete();
            $table->string('name');
            $table->string('code')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_units');
    }
};
```

- [ ] **Step 4: Create the model**

Create `app/Models/OrganizationUnit.php`:
```php
<?php

namespace App\Models;

use Database\Factories\OrganizationUnitFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrganizationUnit extends Model
{
    /** @use HasFactory<OrganizationUnitFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'parent_id',
        'name',
        'code',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(OrganizationUnit::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(OrganizationUnit::class, 'parent_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'organization_unit_id');
    }
}
```

- [ ] **Step 5: Create the factory**

Create `database/factories/OrganizationUnitFactory.php`:
```php
<?php

namespace Database\Factories;

use App\Models\OrganizationUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrganizationUnit>
 */
class OrganizationUnitFactory extends Factory
{
    protected $model = OrganizationUnit::class;

    public function definition(): array
    {
        return [
            'parent_id' => null,
            'name' => fake()->unique()->company(),
            'code' => fake()->unique()->regexify('[A-Z]{3}[0-9]{3}'),
            'is_active' => true,
        ];
    }
}
```

- [ ] **Step 6: Migrate and run the test**

Run:
```bash
cd /c/laragon/www/dormida-work-ai-kit
"/c/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe" artisan migrate
"/c/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe" vendor/bin/pest tests/Feature/OrganizationUnit/OrganizationUnitModelTest.php
```
Expected: PASS (2/2).

- [ ] **Step 7: Commit**

```bash
git add database/migrations database/factories/OrganizationUnitFactory.php app/Models/OrganizationUnit.php tests/Feature/OrganizationUnit/OrganizationUnitModelTest.php
git commit -m "feat(organization): add OrganizationUnit model, migration and factory"
```

---

### Task 2: Extend `users` with identity fields

**Files:**
- Create: `database/migrations/{timestamp}_add_identity_fields_to_users_table.php`
- Modify: `app/Models/User.php`
- Modify: `database/factories/UserFactory.php`
- Modify: `tests/Feature/ProfileTest.php` (existing Breeze test — the delete-account assertion changes semantics once `User` gains `SoftDeletes`)
- Test: `tests/Feature/User/UserModelTest.php`

**Interfaces:**
- Consumes: `App\Models\OrganizationUnit` (Task 1).
- Produces: `User` fillable gains `organization_unit_id, is_system_admin, is_active, employee_code, phone, job_title`; casts `is_system_admin`/`is_active` to boolean; relation `organizationUnit(): BelongsTo`; `SoftDeletes`. `UserFactory` default state now includes a valid `organization_unit_id`, `is_system_admin => false`, `is_active => true` — every later task's `User::factory()->create()` relies on this.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/User/UserModelTest.php`:
```php
<?php

use App\Models\OrganizationUnit;
use App\Models\User;

test('a user belongs to an organization unit', function () {
    $unit = OrganizationUnit::factory()->create();
    $user = User::factory()->create(['organization_unit_id' => $unit->id]);

    expect($user->organizationUnit->is($unit))->toBeTrue();
});

test('a user is soft deleted, not removed from the database', function () {
    $user = User::factory()->create();

    $user->delete();

    $this->assertSoftDeleted($user);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `"/c/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe" vendor/bin/pest tests/Feature/User/UserModelTest.php`
Expected: FAIL — `organization_unit_id` column doesn't exist / `organizationUnit()` undefined.

- [ ] **Step 3: Create the migration**

Run: `"/c/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe" artisan make:migration add_identity_fields_to_users_table --table=users`

Edit the generated file to:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('organization_unit_id')->nullable()->after('id')->constrained('organization_units')->nullOnDelete();
            $table->boolean('is_system_admin')->default(false)->after('password');
            $table->boolean('is_active')->default(true)->after('is_system_admin');
            $table->string('employee_code')->nullable()->unique()->after('is_active');
            $table->string('phone')->nullable()->after('employee_code');
            $table->string('job_title')->nullable()->after('phone');
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['organization_unit_id']);
            $table->dropSoftDeletes();
            $table->dropColumn([
                'organization_unit_id',
                'is_system_admin',
                'is_active',
                'employee_code',
                'phone',
                'job_title',
            ]);
        });
    }
};
```

- [ ] **Step 4: Update the User model**

Replace `app/Models/User.php` with:
```php
<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'organization_unit_id',
        'is_system_admin',
        'is_active',
        'employee_code',
        'phone',
        'job_title',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_system_admin' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function organizationUnit(): BelongsTo
    {
        return $this->belongsTo(OrganizationUnit::class, 'organization_unit_id');
    }
}
```

- [ ] **Step 5: Update the User factory**

Replace `database/factories/UserFactory.php` with:
```php
<?php

namespace Database\Factories;

use App\Models\OrganizationUnit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_unit_id' => OrganizationUnit::factory(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'is_system_admin' => false,
            'is_active' => true,
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
```

- [ ] **Step 6: Fix the pre-existing Breeze test for soft-delete semantics**

`User` now uses `SoftDeletes`, so `$user->delete()` (called by `ProfileController::destroy`) no longer removes the row — it sets `deleted_at`. `Model::fresh()` bypasses soft-delete scopes (it uses `newQueryForRestoration`), so the old assertion `assertNull($user->fresh())` would now fail because `fresh()` still finds the trashed row.

In `tests/Feature/ProfileTest.php`, find:
```php
test('user can delete their account', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->delete('/profile', [
            'password' => 'password',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/');

    $this->assertGuest();
    $this->assertNull($user->fresh());
});
```
Replace the last two lines with:
```php
    $this->assertGuest();
    $this->assertSoftDeleted($user);
```

- [ ] **Step 7: Migrate and run tests**

Run:
```bash
cd /c/laragon/www/dormida-work-ai-kit
"/c/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe" artisan migrate
"/c/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe" vendor/bin/pest tests/Feature/User/UserModelTest.php tests/Feature/ProfileTest.php
```
Expected: PASS (all).

- [ ] **Step 8: Run the full suite to catch any other regression from `SoftDeletes`**

Run: `"/c/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe" vendor/bin/pest`
Expected: PASS. If any other existing test breaks because of the new `User` columns/casts, fix that test's assertions the same way (don't change the model/migration to avoid the failure).

- [ ] **Step 9: Commit**

```bash
git add database/migrations app/Models/User.php database/factories/UserFactory.php tests/Feature/ProfileTest.php tests/Feature/User/UserModelTest.php
git commit -m "feat(user): add organization, admin flag and profile fields to User"
```

---

### Task 3: Organization unit business rules (`UpdateOrganizationUnitAction`, `DeleteOrganizationUnitAction`)

**Files:**
- Create: `app/Actions/OrganizationUnit/UpdateOrganizationUnitAction.php`
- Create: `app/Actions/OrganizationUnit/DeleteOrganizationUnitAction.php`
- Test: `tests/Feature/OrganizationUnit/OrganizationUnitBusinessRulesTest.php`

**Interfaces:**
- Consumes: `App\Models\OrganizationUnit`, `App\Models\User` (Tasks 1-2).
- Produces: `UpdateOrganizationUnitAction::execute(OrganizationUnit $unit, array $data): OrganizationUnit` (throws `Illuminate\Validation\ValidationException` on circular parent). `DeleteOrganizationUnitAction::execute(OrganizationUnit $unit): void` (throws `ValidationException` if the unit has children or users; soft-deletes otherwise). Task 5's controller calls both by type-hinting them as method parameters (Laravel resolves them via the container).

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/OrganizationUnit/OrganizationUnitBusinessRulesTest.php`:
```php
<?php

use App\Actions\OrganizationUnit\DeleteOrganizationUnitAction;
use App\Actions\OrganizationUnit\UpdateOrganizationUnitAction;
use App\Models\OrganizationUnit;
use App\Models\User;
use Illuminate\Validation\ValidationException;

test('an organization unit cannot be updated to be its own parent', function () {
    $unit = OrganizationUnit::factory()->create();

    expect(fn () => (new UpdateOrganizationUnitAction())->execute($unit, ['parent_id' => $unit->id]))
        ->toThrow(ValidationException::class);
});

test('an organization unit cannot be updated to have one of its descendants as its parent', function () {
    $grandparent = OrganizationUnit::factory()->create();
    $parent = OrganizationUnit::factory()->create(['parent_id' => $grandparent->id]);
    $child = OrganizationUnit::factory()->create(['parent_id' => $parent->id]);

    expect(fn () => (new UpdateOrganizationUnitAction())->execute($grandparent, ['parent_id' => $child->id]))
        ->toThrow(ValidationException::class);
});

test('an organization unit can be updated to a valid new parent', function () {
    $oldParent = OrganizationUnit::factory()->create();
    $newParent = OrganizationUnit::factory()->create();
    $unit = OrganizationUnit::factory()->create(['parent_id' => $oldParent->id]);

    (new UpdateOrganizationUnitAction())->execute($unit, ['parent_id' => $newParent->id, 'name' => $unit->name, 'code' => $unit->code, 'is_active' => true]);

    expect($unit->fresh()->parent_id)->toBe($newParent->id);
});

test('an organization unit cannot be deleted while it has children', function () {
    $parent = OrganizationUnit::factory()->create();
    OrganizationUnit::factory()->create(['parent_id' => $parent->id]);

    expect(fn () => (new DeleteOrganizationUnitAction())->execute($parent))
        ->toThrow(ValidationException::class);

    $this->assertNotSoftDeleted($parent);
});

test('an organization unit cannot be deleted while it has users assigned', function () {
    $unit = OrganizationUnit::factory()->create();
    User::factory()->create(['organization_unit_id' => $unit->id]);

    expect(fn () => (new DeleteOrganizationUnitAction())->execute($unit))
        ->toThrow(ValidationException::class);

    $this->assertNotSoftDeleted($unit);
});

test('an organization unit with no children or users can be deleted', function () {
    $unit = OrganizationUnit::factory()->create();

    (new DeleteOrganizationUnitAction())->execute($unit);

    $this->assertSoftDeleted($unit);
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `"/c/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe" vendor/bin/pest tests/Feature/OrganizationUnit/OrganizationUnitBusinessRulesTest.php`
Expected: FAIL — `Class "App\Actions\OrganizationUnit\UpdateOrganizationUnitAction" not found`.

- [ ] **Step 3: Implement `UpdateOrganizationUnitAction`**

Create `app/Actions/OrganizationUnit/UpdateOrganizationUnitAction.php`:
```php
<?php

namespace App\Actions\OrganizationUnit;

use App\Models\OrganizationUnit;
use Illuminate\Validation\ValidationException;

class UpdateOrganizationUnitAction
{
    public function execute(OrganizationUnit $unit, array $data): OrganizationUnit
    {
        if (array_key_exists('parent_id', $data) && $data['parent_id'] !== null) {
            $this->guardAgainstCircularReference($unit, (int) $data['parent_id']);
        }

        $unit->update($data);

        return $unit;
    }

    private function guardAgainstCircularReference(OrganizationUnit $unit, int $newParentId): void
    {
        if ($newParentId === $unit->id) {
            throw ValidationException::withMessages([
                'parent_id' => 'Đơn vị không thể là cha của chính nó.',
            ]);
        }

        $ancestor = OrganizationUnit::find($newParentId);

        while ($ancestor !== null) {
            if ($ancestor->id === $unit->id) {
                throw ValidationException::withMessages([
                    'parent_id' => 'Không thể chọn một đơn vị con làm đơn vị cha.',
                ]);
            }

            $ancestor = $ancestor->parent;
        }
    }
}
```

- [ ] **Step 4: Implement `DeleteOrganizationUnitAction`**

Create `app/Actions/OrganizationUnit/DeleteOrganizationUnitAction.php`:
```php
<?php

namespace App\Actions\OrganizationUnit;

use App\Models\OrganizationUnit;
use Illuminate\Validation\ValidationException;

class DeleteOrganizationUnitAction
{
    public function execute(OrganizationUnit $unit): void
    {
        if ($unit->children()->exists()) {
            throw ValidationException::withMessages([
                'organization_unit' => 'Không thể xoá đơn vị còn đơn vị con. Vui lòng chuyển đơn vị con trước.',
            ]);
        }

        if ($unit->users()->exists()) {
            throw ValidationException::withMessages([
                'organization_unit' => 'Không thể xoá đơn vị còn nhân sự. Vui lòng chuyển nhân sự trước.',
            ]);
        }

        $unit->delete();
    }
}
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `"/c/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe" vendor/bin/pest tests/Feature/OrganizationUnit/OrganizationUnitBusinessRulesTest.php`
Expected: PASS (6/6).

- [ ] **Step 6: Commit**

```bash
git add app/Actions/OrganizationUnit tests/Feature/OrganizationUnit/OrganizationUnitBusinessRulesTest.php
git commit -m "feat(organization): add update/delete business-rule actions"
```

---

### Task 4: `OrganizationUnitPolicy`

**Files:**
- Create: `app/Policies/OrganizationUnitPolicy.php`
- Test: `tests/Feature/OrganizationUnit/OrganizationUnitPolicyTest.php`

**Interfaces:**
- Consumes: `App\Models\User::$is_system_admin` (Task 2).
- Produces: `OrganizationUnitPolicy` with `viewAny`, `view`, `create`, `update`, `delete` — auto-discovered by Laravel's `App\Models\{X}` → `App\Policies\{X}Policy` convention, no manual registration needed.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/OrganizationUnit/OrganizationUnitPolicyTest.php`:
```php
<?php

use App\Models\OrganizationUnit;
use App\Models\User;

test('any authenticated user can view organization units', function () {
    $user = User::factory()->create(['is_system_admin' => false]);
    $unit = OrganizationUnit::factory()->create();

    expect($user->can('viewAny', OrganizationUnit::class))->toBeTrue();
    expect($user->can('view', $unit))->toBeTrue();
});

test('only a system admin can create, update or delete organization units', function () {
    $admin = User::factory()->create(['is_system_admin' => true]);
    $regular = User::factory()->create(['is_system_admin' => false]);
    $unit = OrganizationUnit::factory()->create();

    expect($admin->can('create', OrganizationUnit::class))->toBeTrue();
    expect($admin->can('update', $unit))->toBeTrue();
    expect($admin->can('delete', $unit))->toBeTrue();

    expect($regular->can('create', OrganizationUnit::class))->toBeFalse();
    expect($regular->can('update', $unit))->toBeFalse();
    expect($regular->can('delete', $unit))->toBeFalse();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `"/c/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe" vendor/bin/pest tests/Feature/OrganizationUnit/OrganizationUnitPolicyTest.php`
Expected: FAIL — policy not found, `can()` returns false for the admin checks.

- [ ] **Step 3: Implement the policy**

Create `app/Policies/OrganizationUnitPolicy.php`:
```php
<?php

namespace App\Policies;

use App\Models\OrganizationUnit;
use App\Models\User;

class OrganizationUnitPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, OrganizationUnit $organizationUnit): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->is_system_admin;
    }

    public function update(User $user, OrganizationUnit $organizationUnit): bool
    {
        return $user->is_system_admin;
    }

    public function delete(User $user, OrganizationUnit $organizationUnit): bool
    {
        return $user->is_system_admin;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `"/c/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe" vendor/bin/pest tests/Feature/OrganizationUnit/OrganizationUnitPolicyTest.php`
Expected: PASS (2/2).

- [ ] **Step 5: Commit**

```bash
git add app/Policies/OrganizationUnitPolicy.php tests/Feature/OrganizationUnit/OrganizationUnitPolicyTest.php
git commit -m "feat(organization): add OrganizationUnitPolicy"
```

---

### Task 5: `OrganizationUnitController`, FormRequests, routes

**Files:**
- Modify: `app/Http/Controllers/Controller.php` (add `AuthorizesRequests` trait — needed by every controller from here on for `$this->authorize()`)
- Create: `app/Http/Requests/StoreOrganizationUnitRequest.php`
- Create: `app/Http/Requests/UpdateOrganizationUnitRequest.php`
- Create: `app/Http/Controllers/OrganizationUnitController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/OrganizationUnit/OrganizationUnitControllerTest.php`

**Interfaces:**
- Consumes: `OrganizationUnit`, `UpdateOrganizationUnitAction`, `DeleteOrganizationUnitAction`, `OrganizationUnitPolicy` (Tasks 1, 3, 4).
- Produces: routes `organization-units.index|create|store|edit|update|destroy` (parameter name `organizationUnit`). Task 10's Vue pages call these route names and consume the exact prop shapes below.

- [ ] **Step 1: Add `AuthorizesRequests` to the base Controller**

Replace `app/Http/Controllers/Controller.php` with:
```php
<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class Controller
{
    use AuthorizesRequests;
}
```

- [ ] **Step 2: Write the failing controller test**

Create `tests/Feature/OrganizationUnit/OrganizationUnitControllerTest.php`:
```php
<?php

use App\Models\OrganizationUnit;
use App\Models\User;

test('a system admin can view the organization units index', function () {
    $admin = User::factory()->create(['is_system_admin' => true]);

    $response = $this->actingAs($admin)->get(route('organization-units.index'));

    $response->assertOk();
});

test('a guest is redirected to login when viewing organization units', function () {
    $response = $this->get(route('organization-units.index'));

    $response->assertRedirect(route('login'));
});

test('a system admin can create an organization unit', function () {
    $admin = User::factory()->create(['is_system_admin' => true]);

    $response = $this->actingAs($admin)->post(route('organization-units.store'), [
        'parent_id' => null,
        'name' => 'Phòng Kỹ thuật',
        'code' => 'ENG',
        'is_active' => true,
    ]);

    $response->assertRedirect(route('organization-units.index'));
    $this->assertDatabaseHas('organization_units', ['code' => 'ENG', 'name' => 'Phòng Kỹ thuật']);
});

test('a regular user cannot create an organization unit', function () {
    $user = User::factory()->create(['is_system_admin' => false]);

    $response = $this->actingAs($user)->post(route('organization-units.store'), [
        'parent_id' => null,
        'name' => 'Phòng Kỹ thuật',
        'code' => 'ENG',
        'is_active' => true,
    ]);

    $response->assertForbidden();
    $this->assertDatabaseMissing('organization_units', ['code' => 'ENG']);
});

test('creating an organization unit requires a unique code', function () {
    $admin = User::factory()->create(['is_system_admin' => true]);
    OrganizationUnit::factory()->create(['code' => 'ENG']);

    $response = $this->actingAs($admin)->post(route('organization-units.store'), [
        'parent_id' => null,
        'name' => 'Another Unit',
        'code' => 'ENG',
        'is_active' => true,
    ]);

    $response->assertSessionHasErrors('code');
});

test('a system admin can update an organization unit', function () {
    $admin = User::factory()->create(['is_system_admin' => true]);
    $unit = OrganizationUnit::factory()->create();

    $response = $this->actingAs($admin)->put(route('organization-units.update', $unit), [
        'parent_id' => null,
        'name' => 'Renamed Unit',
        'code' => $unit->code,
        'is_active' => true,
    ]);

    $response->assertRedirect(route('organization-units.index'));
    expect($unit->fresh()->name)->toBe('Renamed Unit');
});

test('updating an organization unit rejects a circular parent', function () {
    $admin = User::factory()->create(['is_system_admin' => true]);
    $unit = OrganizationUnit::factory()->create();

    $response = $this->actingAs($admin)->put(route('organization-units.update', $unit), [
        'parent_id' => $unit->id,
        'name' => $unit->name,
        'code' => $unit->code,
        'is_active' => true,
    ]);

    $response->assertSessionHasErrors('parent_id');
});

test('a system admin can delete an organization unit with no dependents', function () {
    $admin = User::factory()->create(['is_system_admin' => true]);
    $unit = OrganizationUnit::factory()->create();

    $response = $this->actingAs($admin)->delete(route('organization-units.destroy', $unit));

    $response->assertRedirect(route('organization-units.index'));
    $this->assertSoftDeleted($unit);
});

test('deleting an organization unit with children fails', function () {
    $admin = User::factory()->create(['is_system_admin' => true]);
    $parent = OrganizationUnit::factory()->create();
    OrganizationUnit::factory()->create(['parent_id' => $parent->id]);

    $response = $this->actingAs($admin)->delete(route('organization-units.destroy', $parent));

    $response->assertSessionHasErrors('organization_unit');
    $this->assertNotSoftDeleted($parent);
});
```

- [ ] **Step 3: Run tests to verify they fail**

Run: `"/c/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe" vendor/bin/pest tests/Feature/OrganizationUnit/OrganizationUnitControllerTest.php`
Expected: FAIL — routes don't exist yet.

- [ ] **Step 4: Create the FormRequests**

Create `app/Http/Requests/StoreOrganizationUnitRequest.php`:
```php
<?php

namespace App\Http\Requests;

use App\Models\OrganizationUnit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrganizationUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', OrganizationUnit::class);
    }

    public function rules(): array
    {
        return [
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('organization_units', 'id')->where(fn ($query) => $query->whereNull('deleted_at')),
            ],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', Rule::unique('organization_units', 'code')],
            'is_active' => ['boolean'],
        ];
    }
}
```

Create `app/Http/Requests/UpdateOrganizationUnitRequest.php`:
```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrganizationUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('organizationUnit'));
    }

    public function rules(): array
    {
        return [
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('organization_units', 'id')->where(fn ($query) => $query->whereNull('deleted_at')),
            ],
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('organization_units', 'code')->ignore($this->route('organizationUnit')),
            ],
            'is_active' => ['boolean'],
        ];
    }
}
```

- [ ] **Step 5: Create the controller**

Create `app/Http/Controllers/OrganizationUnitController.php`:
```php
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

        $action->execute($organizationUnit);

        return Redirect::route('organization-units.index')->with('success', 'Xoá đơn vị thành công.');
    }
}
```

- [ ] **Step 6: Register the routes**

In `routes/web.php`, add the import `use App\Http\Controllers\OrganizationUnitController;` near the top, and add this new group after the existing `Route::middleware('auth')->group(...)` block that has the `/profile` routes:
```php
Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('organization-units', OrganizationUnitController::class)
        ->parameters(['organization-units' => 'organizationUnit'])
        ->except('show');
});
```

- [ ] **Step 7: Run tests to verify they pass**

Run: `"/c/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe" vendor/bin/pest tests/Feature/OrganizationUnit`
Expected: PASS (all files in the directory).

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/Controller.php app/Http/Requests/StoreOrganizationUnitRequest.php app/Http/Requests/UpdateOrganizationUnitRequest.php app/Http/Controllers/OrganizationUnitController.php routes/web.php tests/Feature/OrganizationUnit/OrganizationUnitControllerTest.php
git commit -m "feat(organization): add OrganizationUnitController, requests and routes"
```

---

### Task 6: `UserPolicy`

**Files:**
- Create: `app/Policies/UserPolicy.php`
- Test: `tests/Feature/User/UserPolicyTest.php`

**Interfaces:**
- Consumes: `App\Models\User::$is_system_admin` (Task 2).
- Produces: `UserPolicy` with `viewAny`, `view`, `create`, `update`, `disable`, `delete` — auto-discovered.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/User/UserPolicyTest.php`:
```php
<?php

use App\Models\User;

test('any authenticated user can view the user directory', function () {
    $user = User::factory()->create(['is_system_admin' => false]);
    $target = User::factory()->create();

    expect($user->can('viewAny', User::class))->toBeTrue();
    expect($user->can('view', $target))->toBeTrue();
});

test('only a system admin can create, update, disable or delete users', function () {
    $admin = User::factory()->create(['is_system_admin' => true]);
    $regular = User::factory()->create(['is_system_admin' => false]);
    $target = User::factory()->create();

    expect($admin->can('create', User::class))->toBeTrue();
    expect($admin->can('update', $target))->toBeTrue();
    expect($admin->can('disable', $target))->toBeTrue();
    expect($admin->can('delete', $target))->toBeTrue();

    expect($regular->can('create', User::class))->toBeFalse();
    expect($regular->can('update', $target))->toBeFalse();
    expect($regular->can('disable', $target))->toBeFalse();
    expect($regular->can('delete', $target))->toBeFalse();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `"/c/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe" vendor/bin/pest tests/Feature/User/UserPolicyTest.php`
Expected: FAIL.

- [ ] **Step 3: Implement the policy**

Create `app/Policies/UserPolicy.php`:
```php
<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, User $model): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->is_system_admin;
    }

    public function update(User $user, User $model): bool
    {
        return $user->is_system_admin;
    }

    public function disable(User $user, User $model): bool
    {
        return $user->is_system_admin;
    }

    public function delete(User $user, User $model): bool
    {
        return $user->is_system_admin;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `"/c/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe" vendor/bin/pest tests/Feature/User/UserPolicyTest.php`
Expected: PASS (2/2).

- [ ] **Step 5: Commit**

```bash
git add app/Policies/UserPolicy.php tests/Feature/User/UserPolicyTest.php
git commit -m "feat(user): add UserPolicy"
```

---

### Task 7: `UserController`, FormRequests, routes, disable/enable, login gate

**Files:**
- Create: `app/Http/Requests/StoreUserRequest.php`
- Create: `app/Http/Requests/UpdateUserRequest.php`
- Create: `app/Http/Controllers/UserController.php`
- Modify: `routes/web.php`
- Modify: `app/Http/Requests/Auth/LoginRequest.php` (block disabled users at login)
- Test: `tests/Feature/User/UserControllerTest.php`

**Interfaces:**
- Consumes: `User`, `OrganizationUnit`, `UserPolicy` (Tasks 1, 2, 6).
- Produces: routes `users.index|create|store|edit|update|destroy`, `users.disable`, `users.enable`. Task 11's Vue pages call these route names and consume the exact prop shapes below.

- [ ] **Step 1: Write the failing controller test**

Create `tests/Feature/User/UserControllerTest.php`:
```php
<?php

use App\Models\OrganizationUnit;
use App\Models\User;

test('a system admin can view the users index', function () {
    $admin = User::factory()->create(['is_system_admin' => true]);

    $response = $this->actingAs($admin)->get(route('users.index'));

    $response->assertOk();
});

test('a system admin can create a user', function () {
    $admin = User::factory()->create(['is_system_admin' => true]);
    $unit = OrganizationUnit::factory()->create();

    $response = $this->actingAs($admin)->post(route('users.store'), [
        'organization_unit_id' => $unit->id,
        'name' => 'New Employee',
        'email' => 'new.employee@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'is_system_admin' => false,
    ]);

    $response->assertRedirect(route('users.index'));
    $this->assertDatabaseHas('users', ['email' => 'new.employee@example.com']);
});

test('a regular user cannot create a user', function () {
    $user = User::factory()->create(['is_system_admin' => false]);
    $unit = OrganizationUnit::factory()->create();

    $response = $this->actingAs($user)->post(route('users.store'), [
        'organization_unit_id' => $unit->id,
        'name' => 'New Employee',
        'email' => 'new.employee@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertForbidden();
    $this->assertDatabaseMissing('users', ['email' => 'new.employee@example.com']);
});

test('a system admin can update a user', function () {
    $admin = User::factory()->create(['is_system_admin' => true]);
    $target = User::factory()->create();
    $unit = OrganizationUnit::factory()->create();

    $response = $this->actingAs($admin)->put(route('users.update', $target), [
        'organization_unit_id' => $unit->id,
        'name' => 'Renamed User',
        'email' => $target->email,
        'is_system_admin' => false,
    ]);

    $response->assertRedirect(route('users.index'));
    expect($target->fresh()->name)->toBe('Renamed User');
});

test('a system admin can disable and re-enable a user', function () {
    $admin = User::factory()->create(['is_system_admin' => true]);
    $target = User::factory()->create(['is_active' => true]);

    $this->actingAs($admin)->patch(route('users.disable', $target))
        ->assertRedirect(route('users.index'));
    expect($target->fresh()->is_active)->toBeFalse();

    $this->actingAs($admin)->patch(route('users.enable', $target))
        ->assertRedirect(route('users.index'));
    expect($target->fresh()->is_active)->toBeTrue();
});

test('a disabled user cannot log in', function () {
    $user = User::factory()->create(['is_active' => false]);

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertGuest();
    $response->assertSessionHasErrors('email');
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `"/c/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe" vendor/bin/pest tests/Feature/User/UserControllerTest.php`
Expected: FAIL — routes don't exist yet.

- [ ] **Step 3: Create the FormRequests**

Create `app/Http/Requests/StoreUserRequest.php`:
```php
<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', User::class);
    }

    public function rules(): array
    {
        return [
            'organization_unit_id' => [
                'required',
                'integer',
                Rule::exists('organization_units', 'id')->where(fn ($query) => $query->whereNull('deleted_at')),
            ],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'employee_code' => ['nullable', 'string', 'max:50', Rule::unique('users', 'employee_code')],
            'phone' => ['nullable', 'string', 'max:30'],
            'job_title' => ['nullable', 'string', 'max:255'],
            'is_system_admin' => ['boolean'],
        ];
    }
}
```

Create `app/Http/Requests/UpdateUserRequest.php`:
```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('user'));
    }

    public function rules(): array
    {
        return [
            'organization_unit_id' => [
                'required',
                'integer',
                Rule::exists('organization_units', 'id')->where(fn ($query) => $query->whereNull('deleted_at')),
            ],
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($this->route('user')),
            ],
            'employee_code' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('users', 'employee_code')->ignore($this->route('user')),
            ],
            'phone' => ['nullable', 'string', 'max:30'],
            'job_title' => ['nullable', 'string', 'max:255'],
            'is_system_admin' => ['boolean'],
        ];
    }
}
```

- [ ] **Step 4: Create the controller**

Create `app/Http/Controllers/UserController.php`:
```php
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

        $user->update(['is_active' => false]);

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
```

- [ ] **Step 5: Register the routes**

In `routes/web.php`, add `use App\Http\Controllers\UserController;`, and add the `users` resource **into the same** `Route::middleware(['auth', 'verified'])->group(...)` block Task 5 created (do not create a second group):
```php
Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('organization-units', OrganizationUnitController::class)
        ->parameters(['organization-units' => 'organizationUnit'])
        ->except('show');

    Route::resource('users', UserController::class)->except('show');
    Route::patch('users/{user}/disable', [UserController::class, 'disable'])->name('users.disable');
    Route::patch('users/{user}/enable', [UserController::class, 'enable'])->name('users.enable');
});
```

- [ ] **Step 6: Block disabled users at login**

In `app/Http/Requests/Auth/LoginRequest.php`, find:
```php
        if (! Auth::attempt($this->only('email', 'password'), $this->boolean('remember'))) {
```
Replace with:
```php
        $credentials = array_merge($this->only('email', 'password'), ['is_active' => true]);

        if (! Auth::attempt($credentials, $this->boolean('remember'))) {
```

- [ ] **Step 7: Run tests to verify they pass**

Run:
```bash
"/c/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe" vendor/bin/pest tests/Feature/User tests/Feature/Auth
```
Expected: PASS (all — including the pre-existing `AuthenticationTest.php`, unaffected since active users still log in normally).

- [ ] **Step 8: Commit**

```bash
git add app/Http/Requests/StoreUserRequest.php app/Http/Requests/UpdateUserRequest.php app/Http/Controllers/UserController.php routes/web.php app/Http/Requests/Auth/LoginRequest.php tests/Feature/User/UserControllerTest.php
git commit -m "feat(user): add UserController, requests, routes and disable/enable"
```

---

### Task 8: Remove public registration

**Files:**
- Delete: `app/Http/Controllers/Auth/RegisteredUserController.php`
- Delete: `resources/js/Pages/Auth/Register.vue`
- Delete: `tests/Feature/Auth/RegistrationTest.php`
- Modify: `routes/auth.php`
- Test: `tests/Feature/Auth/RegistrationRemovedTest.php`

**Interfaces:**
- Produces: `register` route no longer exists; `Route::has('register')` becomes `false`, so `Welcome.vue`'s existing `v-if="canRegister"` guard (already wired to that route check in `routes/web.php`) automatically stops rendering the register link — no Vue changes needed for that page.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Auth/RegistrationRemovedTest.php`:
```php
<?php

test('the registration routes no longer exist', function () {
    $this->get('/register')->assertNotFound();
    $this->post('/register')->assertNotFound();
});

test('the welcome page does not offer a register link', function () {
    $response = $this->get('/');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->where('canRegister', false));
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `"/c/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe" vendor/bin/pest tests/Feature/Auth/RegistrationRemovedTest.php`
Expected: FAIL — `/register` currently returns 200/302, `canRegister` is currently `true`.

- [ ] **Step 3: Remove the registration route**

In `routes/auth.php`, remove the `use App\Http\Controllers\Auth\RegisteredUserController;` import line, and remove this block from inside `Route::middleware('guest')->group(...)`:
```php
    Route::get('register', [RegisteredUserController::class, 'create'])
        ->name('register');

    Route::post('register', [RegisteredUserController::class, 'store']);

```
(keep the blank line spacing tidy — the `login` routes right after should now be the first entries in the group).

- [ ] **Step 4: Delete the controller, the Vue page, and the old test**

```bash
cd /c/laragon/www/dormida-work-ai-kit
rm app/Http/Controllers/Auth/RegisteredUserController.php
rm resources/js/Pages/Auth/Register.vue
rm tests/Feature/Auth/RegistrationTest.php
```

- [ ] **Step 5: Run test to verify it passes**

Run: `"/c/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe" vendor/bin/pest tests/Feature/Auth`
Expected: PASS (all files in the directory, including the new one; the old `RegistrationTest.php` is gone).

- [ ] **Step 6: Rebuild the frontend to confirm nothing references the deleted page**

Run: `npm run build`
Expected: succeeds (no import errors for the deleted `Register.vue`).

- [ ] **Step 7: Commit**

```bash
git add routes/auth.php tests/Feature/Auth/RegistrationRemovedTest.php
git rm app/Http/Controllers/Auth/RegisteredUserController.php resources/js/Pages/Auth/Register.vue tests/Feature/Auth/RegistrationTest.php
git commit -m "feat(auth): remove public registration"
```

---

### Task 9: Bootstrap admin seeder

**Files:**
- Create: `config/dormida.php`
- Modify: `.env.example`
- Modify: `database/seeders/DatabaseSeeder.php`
- Test: `tests/Feature/DatabaseSeederTest.php`

**Interfaces:**
- Consumes: `OrganizationUnit`, `User` (Tasks 1, 2).
- Produces: `config('dormida.admin_email')` / `config('dormida.admin_password')`; `php artisan db:seed` creates one root `OrganizationUnit` (`code = 'ROOT'`) and one `is_system_admin = true` `User`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/DatabaseSeederTest.php`:
```php
<?php

use App\Models\OrganizationUnit;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;

test('seeding creates a root organization unit and a system admin user', function () {
    Artisan::call('db:seed');

    $rootUnit = OrganizationUnit::where('code', 'ROOT')->first();
    $admin = User::where('email', config('dormida.admin_email'))->first();

    expect($rootUnit)->not->toBeNull();
    expect($admin)->not->toBeNull();
    expect($admin->is_system_admin)->toBeTrue();
    expect($admin->organization_unit_id)->toBe($rootUnit->id);
});

test('the seeded admin can log in', function () {
    Artisan::call('db:seed');

    $response = $this->post('/login', [
        'email' => config('dormida.admin_email'),
        'password' => config('dormida.admin_password'),
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `"/c/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe" vendor/bin/pest tests/Feature/DatabaseSeederTest.php`
Expected: FAIL — `config('dormida.admin_email')` is `null`, no matching user/unit created.

- [ ] **Step 3: Add the config file**

Create `config/dormida.php`:
```php
<?php

return [
    'admin_email' => env('DORMIDA_ADMIN_EMAIL', 'admin@dormida.test'),
    'admin_password' => env('DORMIDA_ADMIN_PASSWORD', 'password'),
];
```

- [ ] **Step 4: Add the env keys to the example file**

In `.env.example`, append (near the end of the file):
```env
DORMIDA_ADMIN_EMAIL=admin@dormida.test
DORMIDA_ADMIN_PASSWORD=password
```

- [ ] **Step 5: Rewrite the seeder**

Replace `database/seeders/DatabaseSeeder.php` with:
```php
<?php

namespace Database\Seeders;

use App\Models\OrganizationUnit;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $rootUnit = OrganizationUnit::firstOrCreate(
            ['code' => 'ROOT'],
            ['name' => 'DORMIDA WORK', 'is_active' => true]
        );

        User::firstOrCreate(
            ['email' => config('dormida.admin_email')],
            [
                'organization_unit_id' => $rootUnit->id,
                'name' => 'System Admin',
                'password' => bcrypt(config('dormida.admin_password')),
                'is_system_admin' => true,
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );
    }
}
```

- [ ] **Step 6: Run test to verify it passes**

Run: `"/c/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe" vendor/bin/pest tests/Feature/DatabaseSeederTest.php`
Expected: PASS (2/2).

- [ ] **Step 7: Seed the local dev database**

Run: `"/c/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe" artisan db:seed`
Expected: no errors. This is what makes `http://dormida-work-ai-kit.test/login` usable locally with `admin@dormida.test` / `password` (or the `.env` overrides).

- [ ] **Step 8: Commit**

```bash
git add config/dormida.php .env.example database/seeders/DatabaseSeeder.php tests/Feature/DatabaseSeederTest.php
git commit -m "feat: seed a root organization unit and bootstrap admin user"
```

---

### Task 10: Frontend — Organization Units pages

**Files:**
- Modify: `resources/js/types/index.d.ts` (add `OrganizationUnit` interface)
- Create: `resources/js/Pages/OrganizationUnits/Index.vue`
- Create: `resources/js/Pages/OrganizationUnits/Create.vue`
- Create: `resources/js/Pages/OrganizationUnits/Edit.vue`

**Interfaces:**
- Consumes: routes and props produced by Task 5 (`organizationUnits: {id, parent_id, name, code, is_active}[]` on Index; `{id, name}[]` on Create/Edit; `organizationUnit: {id, parent_id, name, code, is_active}` on Edit).
- Produces: `OrganizationUnit` TypeScript interface in `@/types`, reused by Task 11.

- [ ] **Step 1: Add the TypeScript interface**

In `resources/js/types/index.d.ts`, add above the existing `export interface User`:
```ts
export interface OrganizationUnit {
    id: number;
    parent_id: number | null;
    name: string;
    code: string;
    is_active: boolean;
}

```

- [ ] **Step 2: Create the Index page (tree view)**

Create `resources/js/Pages/OrganizationUnits/Index.vue`:
```vue
<script setup lang="ts">
import { computed } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import TreeTable from 'primevue/treetable';
import Column from 'primevue/column';
import Button from 'primevue/button';
import type { OrganizationUnit, PageProps } from '@/types';

interface OrganizationUnitNode {
    key: string;
    data: OrganizationUnit;
    children: OrganizationUnitNode[];
}

const props = defineProps<{
    organizationUnits: OrganizationUnit[];
}>();

const page = usePage<PageProps>();
const canManage = computed(() => page.props.auth.user.is_system_admin);

const treeNodes = computed<OrganizationUnitNode[]>(() => {
    const byId = new Map<number, OrganizationUnitNode>();

    for (const unit of props.organizationUnits) {
        byId.set(unit.id, { key: String(unit.id), data: unit, children: [] });
    }

    const roots: OrganizationUnitNode[] = [];

    for (const unit of props.organizationUnits) {
        const node = byId.get(unit.id)!;

        if (unit.parent_id !== null && byId.has(unit.parent_id)) {
            byId.get(unit.parent_id)!.children.push(node);
        } else {
            roots.push(node);
        }
    }

    return roots;
});

const destroy = (unit: OrganizationUnit) => {
    if (confirm(`Xoá đơn vị "${unit.name}"?`)) {
        router.delete(route('organization-units.destroy', unit.id));
    }
};
</script>

<template>
    <Head title="Cơ cấu tổ chức" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Cơ cấu tổ chức</h2>
                <Link v-if="canManage" :href="route('organization-units.create')">
                    <Button label="Thêm đơn vị" icon="pi pi-plus" size="small" />
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="overflow-hidden bg-white p-6 shadow-sm sm:rounded-lg">
                    <p v-if="organizationUnits.length === 0" class="text-sm text-gray-500">
                        Chưa có đơn vị nào. Tạo đơn vị gốc để bắt đầu.
                    </p>

                    <TreeTable v-else :value="treeNodes">
                        <Column field="name" header="Tên đơn vị" expander />
                        <Column field="code" header="Mã" />
                        <Column header="Trạng thái">
                            <template #body="{ node }">
                                <span
                                    class="rounded-full px-2 py-1 text-xs font-medium"
                                    :class="node.data.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600'"
                                >
                                    {{ node.data.is_active ? 'Hoạt động' : 'Ngừng hoạt động' }}
                                </span>
                            </template>
                        </Column>
                        <Column v-if="canManage" header="Hành động">
                            <template #body="{ node }">
                                <div class="flex gap-2">
                                    <Link
                                        :href="route('organization-units.edit', node.data.id)"
                                        class="text-sm text-indigo-600 hover:underline"
                                    >
                                        Sửa
                                    </Link>
                                    <button type="button" class="text-sm text-red-600 hover:underline" @click="destroy(node.data)">
                                        Xoá
                                    </button>
                                </div>
                            </template>
                        </Column>
                    </TreeTable>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
```

- [ ] **Step 3: Create the Create page**

Create `resources/js/Pages/OrganizationUnits/Create.vue`:
```vue
<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import Checkbox from '@/Components/Checkbox.vue';
import { Head, useForm } from '@inertiajs/vue3';
import type { OrganizationUnit } from '@/types';

defineProps<{
    organizationUnits: Pick<OrganizationUnit, 'id' | 'name'>[];
}>();

const form = useForm({
    parent_id: null as number | null,
    name: '',
    code: '',
    is_active: true,
});

const submit = () => {
    form.post(route('organization-units.store'));
};
</script>

<template>
    <Head title="Thêm đơn vị" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Thêm đơn vị</h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-2xl sm:px-6 lg:px-8">
                <div class="overflow-hidden bg-white p-6 shadow-sm sm:rounded-lg">
                    <form class="space-y-4" @submit.prevent="submit">
                        <div>
                            <InputLabel for="parent_id" value="Đơn vị cha" />
                            <select
                                id="parent_id"
                                v-model="form.parent_id"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            >
                                <option :value="null">-- Không có (đơn vị gốc) --</option>
                                <option v-for="unit in organizationUnits" :key="unit.id" :value="unit.id">
                                    {{ unit.name }}
                                </option>
                            </select>
                            <InputError class="mt-2" :message="form.errors.parent_id" />
                        </div>

                        <div>
                            <InputLabel for="name" value="Tên đơn vị" />
                            <TextInput id="name" v-model="form.name" type="text" class="mt-1 block w-full" required />
                            <InputError class="mt-2" :message="form.errors.name" />
                        </div>

                        <div>
                            <InputLabel for="code" value="Mã đơn vị" />
                            <TextInput id="code" v-model="form.code" type="text" class="mt-1 block w-full" required />
                            <InputError class="mt-2" :message="form.errors.code" />
                        </div>

                        <div class="flex items-center">
                            <Checkbox id="is_active" v-model:checked="form.is_active" />
                            <InputLabel for="is_active" value="Hoạt động" class="ms-2" />
                        </div>

                        <div class="flex items-center justify-end">
                            <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                                Lưu
                            </PrimaryButton>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
```

- [ ] **Step 4: Create the Edit page**

Create `resources/js/Pages/OrganizationUnits/Edit.vue`:
```vue
<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import TextInput from '@/Components/TextInput.vue';
import Checkbox from '@/Components/Checkbox.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import type { OrganizationUnit } from '@/types';

const props = defineProps<{
    organizationUnit: OrganizationUnit;
    organizationUnits: Pick<OrganizationUnit, 'id' | 'name'>[];
}>();

const form = useForm({
    parent_id: props.organizationUnit.parent_id,
    name: props.organizationUnit.name,
    code: props.organizationUnit.code,
    is_active: props.organizationUnit.is_active,
});

const submit = () => {
    form.put(route('organization-units.update', props.organizationUnit.id));
};

const destroy = () => {
    if (confirm(`Xoá đơn vị "${props.organizationUnit.name}"?`)) {
        router.delete(route('organization-units.destroy', props.organizationUnit.id));
    }
};
</script>

<template>
    <Head title="Sửa đơn vị" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Sửa đơn vị</h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-2xl sm:px-6 lg:px-8">
                <div class="overflow-hidden bg-white p-6 shadow-sm sm:rounded-lg">
                    <form class="space-y-4" @submit.prevent="submit">
                        <div>
                            <InputLabel for="parent_id" value="Đơn vị cha" />
                            <select
                                id="parent_id"
                                v-model="form.parent_id"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            >
                                <option :value="null">-- Không có (đơn vị gốc) --</option>
                                <option v-for="unit in organizationUnits" :key="unit.id" :value="unit.id">
                                    {{ unit.name }}
                                </option>
                            </select>
                            <InputError class="mt-2" :message="form.errors.parent_id" />
                        </div>

                        <div>
                            <InputLabel for="name" value="Tên đơn vị" />
                            <TextInput id="name" v-model="form.name" type="text" class="mt-1 block w-full" required />
                            <InputError class="mt-2" :message="form.errors.name" />
                        </div>

                        <div>
                            <InputLabel for="code" value="Mã đơn vị" />
                            <TextInput id="code" v-model="form.code" type="text" class="mt-1 block w-full" required />
                            <InputError class="mt-2" :message="form.errors.code" />
                        </div>

                        <div class="flex items-center">
                            <Checkbox id="is_active" v-model:checked="form.is_active" />
                            <InputLabel for="is_active" value="Hoạt động" class="ms-2" />
                        </div>
                        <InputError class="mt-2" :message="form.errors.organization_unit" />

                        <div class="flex items-center justify-between">
                            <DangerButton type="button" @click="destroy">Xoá</DangerButton>
                            <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                                Lưu
                            </PrimaryButton>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
```

- [ ] **Step 5: Build and manually smoke-test**

Run: `npm run build`
Expected: succeeds with no TypeScript/Vite errors.

Then, with the dev server running (`php artisan serve` + `npm run dev`, or the Laragon vhost) and logged in as the seeded admin (Task 9), visit `/organization-units`, create a unit, edit it, and delete it — confirm the page renders and each action redirects with a success flash.

- [ ] **Step 6: Commit**

```bash
git add resources/js/types/index.d.ts resources/js/Pages/OrganizationUnits
git commit -m "feat(organization): add OrganizationUnits Inertia pages"
```

---

### Task 11: Frontend — Users pages + navigation

**Files:**
- Modify: `resources/js/types/index.d.ts` (extend `User` interface)
- Create: `resources/js/Pages/Users/Index.vue`
- Create: `resources/js/Pages/Users/Create.vue`
- Create: `resources/js/Pages/Users/Edit.vue`
- Modify: `resources/js/Layouts/AuthenticatedLayout.vue` (add nav links, admin-only)

**Interfaces:**
- Consumes: routes and props produced by Task 7 (`users: {id, name, email, organization_unit_id, is_active, is_system_admin, organization_unit?: {id, name}}[]` on Index; `organizationUnits: {id, name}[]` on Create/Edit; `user: {id, name, email, organization_unit_id, employee_code, phone, job_title, is_system_admin}` on Edit). Consumes `OrganizationUnit` type from Task 10.

- [ ] **Step 1: Extend the User TypeScript interface**

In `resources/js/types/index.d.ts`, replace:
```ts
export interface User {
    id: number;
    name: string;
    email: string;
    email_verified_at?: string;
}
```
with:
```ts
export interface User {
    id: number;
    name: string;
    email: string;
    email_verified_at?: string;
    organization_unit_id: number | null;
    organization_unit?: Pick<OrganizationUnit, 'id' | 'name'> | null;
    is_system_admin: boolean;
    is_active: boolean;
    employee_code?: string | null;
    phone?: string | null;
    job_title?: string | null;
}
```

- [ ] **Step 2: Create the Index page**

Create `resources/js/Pages/Users/Index.vue`:
```vue
<script setup lang="ts">
import { computed } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Button from 'primevue/button';
import type { PageProps, User } from '@/types';

defineProps<{
    users: User[];
}>();

const page = usePage<PageProps>();
const canManage = computed(() => page.props.auth.user.is_system_admin);

const toggleActive = (user: User) => {
    const routeName = user.is_active ? 'users.disable' : 'users.enable';
    router.patch(route(routeName, user.id));
};
</script>

<template>
    <Head title="Người dùng" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Người dùng</h2>
                <Link v-if="canManage" :href="route('users.create')">
                    <Button label="Thêm người dùng" icon="pi pi-plus" size="small" />
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="overflow-hidden bg-white p-6 shadow-sm sm:rounded-lg">
                    <p v-if="users.length === 0" class="text-sm text-gray-500">Chưa có người dùng nào.</p>

                    <DataTable v-else :value="users" data-key="id" paginator :rows="20">
                        <Column field="name" header="Họ tên" />
                        <Column field="email" header="Email" />
                        <Column header="Đơn vị">
                            <template #body="{ data }">
                                {{ data.organization_unit?.name ?? '—' }}
                            </template>
                        </Column>
                        <Column header="Trạng thái">
                            <template #body="{ data }">
                                <span
                                    class="rounded-full px-2 py-1 text-xs font-medium"
                                    :class="data.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600'"
                                >
                                    {{ data.is_active ? 'Hoạt động' : 'Đã vô hiệu hoá' }}
                                </span>
                            </template>
                        </Column>
                        <Column v-if="canManage" header="Hành động">
                            <template #body="{ data }">
                                <div class="flex gap-3">
                                    <Link :href="route('users.edit', data.id)" class="text-sm text-indigo-600 hover:underline">
                                        Sửa
                                    </Link>
                                    <button type="button" class="text-sm text-amber-600 hover:underline" @click="toggleActive(data)">
                                        {{ data.is_active ? 'Vô hiệu hoá' : 'Kích hoạt' }}
                                    </button>
                                </div>
                            </template>
                        </Column>
                    </DataTable>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
```

- [ ] **Step 3: Create the Create page**

Create `resources/js/Pages/Users/Create.vue`:
```vue
<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import Checkbox from '@/Components/Checkbox.vue';
import { Head, useForm } from '@inertiajs/vue3';
import type { OrganizationUnit } from '@/types';

defineProps<{
    organizationUnits: Pick<OrganizationUnit, 'id' | 'name'>[];
}>();

const form = useForm({
    organization_unit_id: null as number | null,
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    employee_code: '',
    phone: '',
    job_title: '',
    is_system_admin: false,
});

const submit = () => {
    form.post(route('users.store'));
};
</script>

<template>
    <Head title="Thêm người dùng" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Thêm người dùng</h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-2xl sm:px-6 lg:px-8">
                <div class="overflow-hidden bg-white p-6 shadow-sm sm:rounded-lg">
                    <form class="space-y-4" @submit.prevent="submit">
                        <div>
                            <InputLabel for="organization_unit_id" value="Đơn vị" />
                            <select
                                id="organization_unit_id"
                                v-model="form.organization_unit_id"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                required
                            >
                                <option :value="null" disabled>-- Chọn đơn vị --</option>
                                <option v-for="unit in organizationUnits" :key="unit.id" :value="unit.id">
                                    {{ unit.name }}
                                </option>
                            </select>
                            <InputError class="mt-2" :message="form.errors.organization_unit_id" />
                        </div>

                        <div>
                            <InputLabel for="name" value="Họ tên" />
                            <TextInput id="name" v-model="form.name" type="text" class="mt-1 block w-full" required />
                            <InputError class="mt-2" :message="form.errors.name" />
                        </div>

                        <div>
                            <InputLabel for="email" value="Email" />
                            <TextInput id="email" v-model="form.email" type="email" class="mt-1 block w-full" required />
                            <InputError class="mt-2" :message="form.errors.email" />
                        </div>

                        <div>
                            <InputLabel for="password" value="Mật khẩu" />
                            <TextInput id="password" v-model="form.password" type="password" class="mt-1 block w-full" required />
                            <InputError class="mt-2" :message="form.errors.password" />
                        </div>

                        <div>
                            <InputLabel for="password_confirmation" value="Xác nhận mật khẩu" />
                            <TextInput
                                id="password_confirmation"
                                v-model="form.password_confirmation"
                                type="password"
                                class="mt-1 block w-full"
                                required
                            />
                            <InputError class="mt-2" :message="form.errors.password_confirmation" />
                        </div>

                        <div>
                            <InputLabel for="employee_code" value="Mã nhân viên" />
                            <TextInput id="employee_code" v-model="form.employee_code" type="text" class="mt-1 block w-full" />
                            <InputError class="mt-2" :message="form.errors.employee_code" />
                        </div>

                        <div>
                            <InputLabel for="phone" value="Điện thoại" />
                            <TextInput id="phone" v-model="form.phone" type="text" class="mt-1 block w-full" />
                            <InputError class="mt-2" :message="form.errors.phone" />
                        </div>

                        <div>
                            <InputLabel for="job_title" value="Chức danh" />
                            <TextInput id="job_title" v-model="form.job_title" type="text" class="mt-1 block w-full" />
                            <InputError class="mt-2" :message="form.errors.job_title" />
                        </div>

                        <div class="flex items-center">
                            <Checkbox id="is_system_admin" v-model:checked="form.is_system_admin" />
                            <InputLabel for="is_system_admin" value="Quản trị hệ thống" class="ms-2" />
                        </div>

                        <div class="flex items-center justify-end">
                            <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                                Lưu
                            </PrimaryButton>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
```

- [ ] **Step 4: Create the Edit page**

Create `resources/js/Pages/Users/Edit.vue`:
```vue
<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import Checkbox from '@/Components/Checkbox.vue';
import { Head, useForm } from '@inertiajs/vue3';
import type { OrganizationUnit, User } from '@/types';

const props = defineProps<{
    user: Pick<User, 'id' | 'name' | 'email' | 'organization_unit_id' | 'employee_code' | 'phone' | 'job_title' | 'is_system_admin'>;
    organizationUnits: Pick<OrganizationUnit, 'id' | 'name'>[];
}>();

const form = useForm({
    organization_unit_id: props.user.organization_unit_id,
    name: props.user.name,
    email: props.user.email,
    employee_code: props.user.employee_code ?? '',
    phone: props.user.phone ?? '',
    job_title: props.user.job_title ?? '',
    is_system_admin: props.user.is_system_admin,
});

const submit = () => {
    form.put(route('users.update', props.user.id));
};
</script>

<template>
    <Head title="Sửa người dùng" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Sửa người dùng</h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-2xl sm:px-6 lg:px-8">
                <div class="overflow-hidden bg-white p-6 shadow-sm sm:rounded-lg">
                    <form class="space-y-4" @submit.prevent="submit">
                        <div>
                            <InputLabel for="organization_unit_id" value="Đơn vị" />
                            <select
                                id="organization_unit_id"
                                v-model="form.organization_unit_id"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                required
                            >
                                <option v-for="unit in organizationUnits" :key="unit.id" :value="unit.id">
                                    {{ unit.name }}
                                </option>
                            </select>
                            <InputError class="mt-2" :message="form.errors.organization_unit_id" />
                        </div>

                        <div>
                            <InputLabel for="name" value="Họ tên" />
                            <TextInput id="name" v-model="form.name" type="text" class="mt-1 block w-full" required />
                            <InputError class="mt-2" :message="form.errors.name" />
                        </div>

                        <div>
                            <InputLabel for="email" value="Email" />
                            <TextInput id="email" v-model="form.email" type="email" class="mt-1 block w-full" required />
                            <InputError class="mt-2" :message="form.errors.email" />
                        </div>

                        <div>
                            <InputLabel for="employee_code" value="Mã nhân viên" />
                            <TextInput id="employee_code" v-model="form.employee_code" type="text" class="mt-1 block w-full" />
                            <InputError class="mt-2" :message="form.errors.employee_code" />
                        </div>

                        <div>
                            <InputLabel for="phone" value="Điện thoại" />
                            <TextInput id="phone" v-model="form.phone" type="text" class="mt-1 block w-full" />
                            <InputError class="mt-2" :message="form.errors.phone" />
                        </div>

                        <div>
                            <InputLabel for="job_title" value="Chức danh" />
                            <TextInput id="job_title" v-model="form.job_title" type="text" class="mt-1 block w-full" />
                            <InputError class="mt-2" :message="form.errors.job_title" />
                        </div>

                        <div class="flex items-center">
                            <Checkbox id="is_system_admin" v-model:checked="form.is_system_admin" />
                            <InputLabel for="is_system_admin" value="Quản trị hệ thống" class="ms-2" />
                        </div>

                        <div class="flex items-center justify-end">
                            <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                                Lưu
                            </PrimaryButton>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
```

- [ ] **Step 5: Add navigation links (admin-only)**

In `resources/js/Layouts/AuthenticatedLayout.vue`, find the desktop nav block:
```vue
                            <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                                <NavLink :href="route('dashboard')" :active="route().current('dashboard')">
                                    Dashboard
                                </NavLink>
                            </div>
```
Replace with:
```vue
                            <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                                <NavLink :href="route('dashboard')" :active="route().current('dashboard')">
                                    Dashboard
                                </NavLink>
                                <NavLink
                                    v-if="$page.props.auth.user.is_system_admin"
                                    :href="route('organization-units.index')"
                                    :active="route().current('organization-units.*')"
                                >
                                    Tổ chức
                                </NavLink>
                                <NavLink
                                    v-if="$page.props.auth.user.is_system_admin"
                                    :href="route('users.index')"
                                    :active="route().current('users.*')"
                                >
                                    Người dùng
                                </NavLink>
                            </div>
```

Then find the mobile nav block:
```vue
                    <div class="space-y-1 pb-3 pt-2">
                        <ResponsiveNavLink :href="route('dashboard')" :active="route().current('dashboard')">
                            Dashboard
                        </ResponsiveNavLink>
                    </div>
```
Replace with:
```vue
                    <div class="space-y-1 pb-3 pt-2">
                        <ResponsiveNavLink :href="route('dashboard')" :active="route().current('dashboard')">
                            Dashboard
                        </ResponsiveNavLink>
                        <ResponsiveNavLink
                            v-if="$page.props.auth.user.is_system_admin"
                            :href="route('organization-units.index')"
                            :active="route().current('organization-units.*')"
                        >
                            Tổ chức
                        </ResponsiveNavLink>
                        <ResponsiveNavLink
                            v-if="$page.props.auth.user.is_system_admin"
                            :href="route('users.index')"
                            :active="route().current('users.*')"
                        >
                            Người dùng
                        </ResponsiveNavLink>
                    </div>
```

- [ ] **Step 6: Build and manually smoke-test**

Run: `npm run build`
Expected: succeeds with no TypeScript/Vite errors.

Then, logged in as the seeded admin, confirm the "Tổ chức" and "Người dùng" nav links appear, `/users` lists the admin, creating/editing/disabling a user works end to end, and a disabled user is rejected at `/login`.

- [ ] **Step 7: Commit**

```bash
git add resources/js/types/index.d.ts resources/js/Pages/Users resources/js/Layouts/AuthenticatedLayout.vue
git commit -m "feat(user): add Users Inertia pages and admin navigation"
```

---

### Task 12: Final verification pass

**Files:** None (verification only).

- [ ] **Step 1: Run the full backend and frontend check suite**

Run each in `/c/laragon/www/dormida-work-ai-kit`:
```bash
"/c/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe" vendor/bin/pint --test
"/c/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe" vendor/bin/pest
npm run lint
npm run format:check
npm run build
```
Expected: every command exits 0.

- [ ] **Step 2: Confirm the route list matches expectations**

Run: `"/c/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe" artisan route:list --name=organization-units` and `--name=users`
Expected: `organization-units.index/create/store/edit/update/destroy` present (parameter `organizationUnit`), no `organization-units.show`; `users.index/create/store/edit/update/destroy/disable/enable` present, no `users.show`; no `register`/`register` routes remain (`--name=register` returns nothing).

- [ ] **Step 3: Confirm working tree is clean**

Run: `git status`
Expected: no unstaged/untracked changes.

- [ ] **Step 4: Report to the user**

Summarize: commits made, how to log in locally (seeded admin credentials from `.env`/`config/dormida.php` defaults), that Đợt 1 (User + Organization) is complete, and that Đợt 2 (Role & Permission) is next — it will replace the `is_system_admin` checks inside `OrganizationUnitPolicy`/`UserPolicy` with real permission checks without changing their method signatures.

No commit — reporting step only.
