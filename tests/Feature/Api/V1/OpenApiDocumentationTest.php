<?php

use Illuminate\Support\Facades\Route;

test('openapi specification documents mobile api and bearer authentication', function () {
    $response = $this->getJson('/docs/api.json')->assertOk();

    $response
        ->assertJsonPath('info.title', 'Dormida API')
        ->assertJsonPath('components.securitySchemes.http.type', 'http')
        ->assertJsonPath('components.securitySchemes.http.scheme', 'bearer');

    $paths = array_keys($response->json('paths'));

    expect($paths)
        ->toContain('/auth/login')
        ->toContain('/auth/refresh')
        ->toContain('/tasks')
        ->toContain('/projects')
        ->toContain('/users');

    $document = $response->json();

    collect(Route::getRoutes())
        ->filter(fn ($route) => str_starts_with($route->uri(), 'api/v1/'))
        ->each(function ($route) use ($document): void {
            $path = '/'.substr($route->uri(), strlen('api/v1/'));

            foreach (array_diff($route->methods(), ['HEAD']) as $method) {
                expect($document['paths'][$path][strtolower($method)] ?? null)
                    ->not->toBeNull("Missing {$method} {$path} from OpenAPI document");
            }
        });
});

test('interactive api documentation is available', function () {
    $this->get('/docs/api')->assertOk();
});
