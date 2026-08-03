<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * @param  list<string>  $permissions
 * @param  array<string, mixed>  $attributes
 */
function userWithPermissions(array $permissions = [], array $attributes = []): User
{
    $user = User::factory()->create($attributes);

    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $user->syncPermissions($permissions);

    return $user;
}

/**
 * Gán permission cho một user đã tồn tại (vd. người tạo mẫu định kỳ cần giữ
 * nguyên id để so khớp creator_id), tạo permission nếu chưa có.
 *
 * @param  list<string>  $permissions
 */
function grantPermissions(User $user, array $permissions): User
{
    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $user->syncPermissions($permissions);

    return $user;
}

/**
 * Headers to force Inertia to respond with JSON instead of a full HTML page,
 * so tests do not depend on Vue page components already being built by Vite.
 *
 * @return array<string, string>
 */
function inertiaHeaders(): array
{
    $manifest = public_path('build/manifest.json');

    return [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => file_exists($manifest) ? hash_file('xxh128', $manifest) : '',
    ];
}
