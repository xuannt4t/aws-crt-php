<?php

it('defines one free render docker web service without paid resources', function () {
    $blueprint = file_get_contents(base_path('render.yaml'));

    expect(substr_count($blueprint, '- type: web'))->toBe(1)
        ->and($blueprint)->toContain('runtime: docker')
        ->and($blueprint)->toContain('plan: free')
        ->and($blueprint)->toContain('region: singapore')
        ->and($blueprint)->toContain('healthCheckPath: /up')
        ->and($blueprint)->not->toContain("\ndatabases:")
        ->and($blueprint)->not->toContain('- type: worker')
        ->and($blueprint)->not->toContain('- type: cron');
});

it('runs laravel and reverb behind one nginx port', function () {
    $dockerfile = file_get_contents(base_path('Dockerfile'));
    $supervisor = file_get_contents(base_path('deploy/render/supervisord.conf'));
    $nginx = file_get_contents(base_path('deploy/render/nginx.conf.template'));
    $entrypoint = file_get_contents(base_path('deploy/render/entrypoint.sh'));

    expect($dockerfile)->toContain('php:8.3-fpm-bookworm')
        ->and($dockerfile)->toContain('ENTRYPOINT ["/usr/local/bin/render-entrypoint"]')
        ->and($supervisor)->toContain('artisan queue:work database')
        ->and($supervisor)->toContain('artisan schedule:work')
        ->and($supervisor)->toContain('artisan reverb:start --host=127.0.0.1 --port=8080')
        ->and($nginx)->toContain('listen ${PORT}')
        ->and($nginx)->toContain('proxy_pass http://127.0.0.1:8080')
        ->and($entrypoint)->toContain('artisan migrate --force')
        ->and($entrypoint)->toContain('exec /usr/bin/supervisord');
});

it('does not copy secrets or mutable development artifacts into the image', function () {
    $ignore = file_get_contents(base_path('.dockerignore'));

    expect($ignore)->toContain('.env')
        ->and($ignore)->toContain('node_modules')
        ->and($ignore)->toContain('vendor')
        ->and($ignore)->toContain('storage/logs/*');
});

it('can seed idempotent demo data during a free render deploy', function () {
    $blueprint = file_get_contents(base_path('render.yaml'));
    $entrypoint = file_get_contents(base_path('deploy/render/entrypoint.sh'));

    expect($blueprint)->toContain("- key: DORMIDA_SEED_DEMO\n        value: \"true\"")
        ->and($entrypoint)->toContain('[ "${DORMIDA_SEED_DEMO:-false}" = "true" ]')
        ->and($entrypoint)->toContain("db:seed --class='Database\\Seeders\\DemoDataSeeder' --force");
});
