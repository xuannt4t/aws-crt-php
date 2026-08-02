# Render Free + TiDB Deployment Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Deploy DORMIDA WORK from `main` to one zero-cost Render Docker Web Service backed by the existing TiDB Cloud Starter database, with HTTP, database queue, scheduler, and Reverb realtime all operational.

**Architecture:** A multi-stage Docker image builds Composer dependencies and Vue/Vite assets, then runs PHP-FPM, Nginx, the database queue worker, Laravel scheduler, and Reverb under Supervisor. Nginx exposes Render's single `$PORT`, routes Laravel requests to PHP-FPM, and proxies `/app` plus `/apps` to Reverb on port 8080; TiDB remains the only persistent state.

**Tech Stack:** Laravel 11, PHP 8.3-FPM, Vue 3/Vite, Nginx, Supervisor, Laravel Reverb, Docker, Render Blueprint, TiDB Cloud Starter via MySQL TLS.

## Global Constraints

- Render resource type is one `web` service with `plan: free` and `region: singapore`.
- TiDB Starter spending limit remains exactly 0 USD/month.
- Laravel keeps `DB_CONNECTION=mysql` and verifies TiDB TLS with the system CA bundle.
- Queue, session, and cache use the existing database tables; no Redis service is created.
- No persistent disk, Render Postgres, Render Key Value, worker, or cron service is created.
- `APP_KEY`, `DB_PASSWORD`, and `REVERB_APP_SECRET` never enter Git.
- Local uploads are explicitly documented as ephemeral demo data.
- Browser deployment stops before confirmation if Render displays a non-zero price or requests payment.

---

### Task 1: Lock the Render deployment contract with a failing test

**Files:**
- Create: `tests/Feature/Deployment/RenderDeploymentConfigTest.php`

**Interfaces:**
- Consumes: deployment requirements from `docs/superpowers/specs/2026-08-02-render-tidb-deployment-design.md`.
- Produces: an executable contract for `Dockerfile`, `.dockerignore`, `render.yaml`, and `deploy/render/*`.

- [ ] **Step 1: Write the failing deployment contract test**

```php
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
```

- [ ] **Step 2: Run the test and verify RED**

Run:

```powershell
& 'C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe' vendor\bin\pest tests\Feature\Deployment\RenderDeploymentConfigTest.php
```

Expected: FAIL because `render.yaml`, `Dockerfile`, `.dockerignore`, and `deploy/render` do not exist.

- [ ] **Step 3: Commit the red test**

```powershell
git add tests/Feature/Deployment/RenderDeploymentConfigTest.php
git commit -m "test(deploy): define Render Free container contract"
```

---

### Task 2: Build the production Docker runtime

**Files:**
- Create: `.dockerignore`
- Create: `Dockerfile`
- Create: `deploy/render/nginx.conf.template`
- Create: `deploy/render/supervisord.conf`
- Create: `deploy/render/entrypoint.sh`
- Test: `tests/Feature/Deployment/RenderDeploymentConfigTest.php`

**Interfaces:**
- Consumes: `$PORT`, Laravel runtime environment variables, system CA bundle, and the public Vite Reverb key.
- Produces: a container whose PID 1 is Supervisor and whose public listener is Nginx.

- [ ] **Step 1: Add a minimal Docker build context**

Create `.dockerignore` with:

```text
.git
.github
.env
.env.*
!.env.example
!.env.production.example
node_modules
vendor
public/build
storage/logs/*
storage/framework/cache/data/*
storage/framework/sessions/*
storage/framework/views/*
tests
docs
```

- [ ] **Step 2: Create the multi-stage `Dockerfile`**

Use this complete multi-stage image:

```dockerfile
FROM composer:2 AS composer
WORKDIR /app
COPY . .
RUN composer install \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader

FROM node:22-bookworm-slim AS frontend
WORKDIR /app
COPY --from=composer /app /app
ARG VITE_REVERB_APP_KEY=dormida-work-key
ARG VITE_REVERB_PORT=443
ARG VITE_REVERB_SCHEME=https
ENV VITE_REVERB_APP_KEY=$VITE_REVERB_APP_KEY \
    VITE_REVERB_PORT=$VITE_REVERB_PORT \
    VITE_REVERB_SCHEME=$VITE_REVERB_SCHEME
RUN npm ci && npm run build

FROM php:8.3-fpm-bookworm

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        ca-certificates \
        gettext-base \
        libicu-dev \
        libzip-dev \
        nginx \
        supervisor \
        unzip \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        intl \
        opcache \
        pcntl \
        pdo_mysql \
        zip \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html
COPY --from=composer /app /var/www/html
COPY --from=frontend /app/public/build /var/www/html/public/build
COPY deploy/render/nginx.conf.template /etc/nginx/templates/default.conf.template
COPY deploy/render/supervisord.conf /etc/supervisor/conf.d/dormida.conf
COPY deploy/render/entrypoint.sh /usr/local/bin/render-entrypoint
RUN chmod +x /usr/local/bin/render-entrypoint \
    && rm -f /etc/nginx/sites-enabled/default

EXPOSE 10000
ENTRYPOINT ["/usr/local/bin/render-entrypoint"]
```

- [ ] **Step 3: Add the Nginx template**

Create `deploy/render/nginx.conf.template` with:

```nginx
map $http_upgrade $connection_upgrade {
    default upgrade;
    '' close;
}

server {
    listen ${PORT};
    server_name _;
    root /var/www/html/public;
    index index.php;
    client_max_body_size 32M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

location ~ ^/(app|apps) {
    proxy_pass http://127.0.0.1:8080;
    proxy_http_version 1.1;
    proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection $connection_upgrade;
    proxy_set_header Host $host;
    proxy_set_header X-Forwarded-Proto $http_x_forwarded_proto;
    proxy_read_timeout 3600s;
}

    location ~ \.php$ {
        try_files $uri =404;
        include fastcgi_params;
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_param HTTPS $http_x_forwarded_proto;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

- [ ] **Step 4: Add Supervisor programs**

Create `deploy/render/supervisord.conf` with:

```ini
[supervisord]
nodaemon=true
user=root
logfile=/dev/null
pidfile=/tmp/supervisord.pid

[program:php-fpm]
command=php-fpm -F
priority=10
autostart=true
autorestart=true
stdout_logfile=/dev/fd/1
stdout_logfile_maxbytes=0
stderr_logfile=/dev/fd/2
stderr_logfile_maxbytes=0

[program:nginx]
command=nginx -g "daemon off;"
priority=20
autostart=true
autorestart=true
stdout_logfile=/dev/fd/1
stdout_logfile_maxbytes=0
stderr_logfile=/dev/fd/2
stderr_logfile_maxbytes=0

[program:queue]
command=php artisan queue:work database --sleep=3 --tries=3 --max-time=3600
directory=/var/www/html
priority=30
autostart=true
autorestart=true
stopwaitsecs=3600
stdout_logfile=/dev/fd/1
stdout_logfile_maxbytes=0
stderr_logfile=/dev/fd/2
stderr_logfile_maxbytes=0

[program:scheduler]
command=php artisan schedule:work
directory=/var/www/html
priority=30
autostart=true
autorestart=true
stdout_logfile=/dev/fd/1
stdout_logfile_maxbytes=0
stderr_logfile=/dev/fd/2
stderr_logfile_maxbytes=0

[program:reverb]
command=php artisan reverb:start --host=127.0.0.1 --port=8080
directory=/var/www/html
priority=30
autostart=true
autorestart=true
stdout_logfile=/dev/fd/1
stdout_logfile_maxbytes=0
stderr_logfile=/dev/fd/2
stderr_logfile_maxbytes=0
```

- [ ] **Step 5: Add the fail-fast entrypoint**

Create `deploy/render/entrypoint.sh` with:

```sh
#!/bin/sh
set -eu

: "${PORT:=10000}"
export PORT

mkdir -p \
    storage/app/public \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache

chown -R www-data:www-data storage bootstrap/cache
envsubst '$PORT' \
    < /etc/nginx/templates/default.conf.template \
    > /etc/nginx/conf.d/default.conf

php artisan storage:link || true
php artisan migrate --force
php artisan optimize

exec /usr/bin/supervisord -n -c /etc/supervisor/supervisord.conf
```

- [ ] **Step 6: Run the contract test and verify the runtime half is GREEN**

Run the Task 1 Pest command. Expected: the Docker/runtime assertions pass; the Blueprint assertion still fails until Task 4.

- [ ] **Step 7: Validate shell and Nginx syntax locally**

```powershell
& 'C:\Program Files\Git\bin\bash.exe' -n deploy/render/entrypoint.sh
docker version
docker build --tag dormida-work-render:test .
```

If Docker Desktop is unavailable, record that static checks passed and defer the image build gate to Render's build log; do not claim the image itself has been verified locally.

- [ ] **Step 8: Commit the runtime**

```powershell
git add .dockerignore Dockerfile deploy/render tests/Feature/Deployment/RenderDeploymentConfigTest.php
git commit -m "feat(deploy): add Render Docker runtime"
```

---

### Task 3: Make Echo safe for same-host Render WSS

**Files:**
- Create: `resources/js/Support/realtimeConfig.ts`
- Create: `resources/js/Support/realtimeConfig.test.ts`
- Modify: `resources/js/echo.ts`

**Interfaces:**
- Consumes: optional `VITE_REVERB_HOST`, `VITE_REVERB_PORT`, and `VITE_REVERB_SCHEME`.
- Produces: Echo defaults to the page hostname and protocol without embedding a Render hostname.

- [ ] **Step 1: Add a failing frontend test for HTTPS fallback**

Create `resources/js/Support/realtimeConfig.test.ts`:

```ts
import { describe, expect, it } from 'vitest';
import { resolveRealtimeOptions } from './realtimeConfig';

describe('resolveRealtimeOptions', () => {
    it('uses same-host WSS when production variables omit host and scheme', () => {
        expect(
            resolveRealtimeOptions(
                { VITE_REVERB_APP_KEY: 'public-key' },
                { hostname: 'dormida-work.onrender.com', protocol: 'https:' },
            ),
        ).toEqual({
            key: 'public-key',
            wsHost: 'dormida-work.onrender.com',
            wsPort: 80,
            wssPort: 443,
            forceTLS: true,
            enabledTransports: ['ws', 'wss'],
        });
    });
});
```

- [ ] **Step 2: Run the focused test and verify RED**

```powershell
npm run test:unit -- resources/js/Support/realtimeConfig.test.ts
```

Expected: FAIL because `resolveRealtimeOptions` does not exist.

- [ ] **Step 3: Implement protocol-aware defaults**

Create `resources/js/Support/realtimeConfig.ts`:

```ts
type RealtimeEnvironment = Partial<
    Record<
        | 'VITE_REVERB_APP_KEY'
        | 'VITE_REVERB_HOST'
        | 'VITE_REVERB_PORT'
        | 'VITE_REVERB_SCHEME',
        string
    >
>;

type BrowserLocation = Pick<Location, 'hostname' | 'protocol'>;

export const resolveRealtimeOptions = (
    environment: RealtimeEnvironment,
    location: BrowserLocation,
) => {
    const scheme =
        environment.VITE_REVERB_SCHEME ??
        (location.protocol === 'https:' ? 'https' : 'http');

    return {
        key: environment.VITE_REVERB_APP_KEY,
        wsHost: environment.VITE_REVERB_HOST ?? location.hostname,
        wsPort: Number(environment.VITE_REVERB_PORT ?? 80),
        wssPort: Number(environment.VITE_REVERB_PORT ?? 443),
        forceTLS: scheme === 'https',
        enabledTransports: ['ws', 'wss'] as const,
    };
};
```

Update `echo.ts` to call `resolveRealtimeOptions(import.meta.env, window.location)`, destructure `key`, and pass the resolved object to Echo only when `key` exists.

- [ ] **Step 4: Run focused and full frontend tests**

```powershell
npm run test:unit -- resources/js/Support/realtimeConfig.test.ts
npm run test:unit
npm run build
```

Expected: all commands exit 0.

- [ ] **Step 5: Commit the WSS fallback**

```powershell
git add resources/js/echo.ts resources/js/Support/realtimeConfig.ts resources/js/Support/realtimeConfig.test.ts
git commit -m "fix(realtime): default Echo to same-host WSS"
```

---

### Task 4: Add the zero-cost Render Blueprint and deployment runbook

**Files:**
- Create: `render.yaml`
- Modify: `.env.production.example`
- Modify: `docs/deployment.md`
- Test: `tests/Feature/Deployment/RenderDeploymentConfigTest.php`

**Interfaces:**
- Consumes: TiDB gateway, database name, username, public Reverb identity, and three dashboard-only secrets.
- Produces: one-click Render Blueprint constrained to one Free Docker Web Service.

- [ ] **Step 1: Create `render.yaml`**

Create the complete Blueprint below. `sync: false` forces the dashboard to request secrets without storing them in Git:

```yaml
services:
  - type: web
    name: dormida-work
    runtime: docker
    plan: free
    region: singapore
    dockerfilePath: ./Dockerfile
    healthCheckPath: /up
    autoDeployTrigger: commit
    envVars:
      - key: APP_NAME
        value: DORMIDA WORK
      - key: APP_ENV
        value: production
      - key: APP_DEBUG
        value: "false"
      - key: APP_URL
        value: https://dormida-work.onrender.com
      - key: ASSET_URL
        value: https://dormida-work.onrender.com
      - key: APP_KEY
        sync: false
      - key: LOG_CHANNEL
        value: stderr
      - key: LOG_LEVEL
        value: warning
      - key: DB_CONNECTION
        value: mysql
      - key: DB_HOST
        value: gateway01.ap-southeast-1.prod.aws.tidbcloud.com
      - key: DB_PORT
        value: "4000"
      - key: DB_DATABASE
        value: dormida_work
      - key: DB_USERNAME
        value: T3FYYZVYsjJP5r2.root
      - key: DB_PASSWORD
        sync: false
      - key: MYSQL_ATTR_SSL_CA
        value: /etc/ssl/certs/ca-certificates.crt
      - key: SESSION_DRIVER
        value: database
      - key: SESSION_SECURE_COOKIE
        value: "true"
      - key: CACHE_STORE
        value: database
      - key: QUEUE_CONNECTION
        value: database
      - key: BROADCAST_CONNECTION
        value: reverb
      - key: REVERB_APP_ID
        value: dormida-work
      - key: REVERB_APP_KEY
        value: dormida-work-key
      - key: REVERB_APP_SECRET
        generateValue: true
      - key: REVERB_HOST
        value: 127.0.0.1
      - key: REVERB_PORT
        value: "8080"
      - key: REVERB_SCHEME
        value: http
      - key: VITE_REVERB_APP_KEY
        value: dormida-work-key
      - key: VITE_REVERB_PORT
        value: "443"
      - key: VITE_REVERB_SCHEME
        value: https
      - key: FILESYSTEM_DISK
        value: local
      - key: DORMIDA_ATTACHMENT_DISK
        value: local
      - key: MAIL_MAILER
        value: log
```

- [ ] **Step 2: Document Render/TiDB environment and limitations**

Add a Render section to `.env.production.example` and `docs/deployment.md` describing:

- how to generate `APP_KEY` with `php artisan key:generate --show`;
- how to open TiDB **Connect**, reset the password, and paste it only into Render `DB_PASSWORD`;
- why TiDB spending limit must remain 0 USD;
- why uploads disappear on Free restarts;
- how `/up`, HTTPS login, queue, scheduler, and WSS are verified;
- how to inspect Render logs when migration or a supervised process fails.

- [ ] **Step 3: Run deployment contract and documentation checks**

```powershell
& 'C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe' vendor\bin\pest tests\Feature\Deployment\RenderDeploymentConfigTest.php
git diff --check
```

Expected: PASS with no whitespace errors.

- [ ] **Step 4: Commit the Blueprint and runbook**

```powershell
git add render.yaml .env.production.example docs/deployment.md tests/Feature/Deployment/RenderDeploymentConfigTest.php
git commit -m "feat(deploy): add zero-cost Render Blueprint"
```

---

### Task 5: Verify, integrate, and deploy through the user's Chrome session

**Files:**
- No new product files.
- Verify: entire repository and live Render/TiDB resources.

**Interfaces:**
- Consumes: green feature branch, GitHub remote, authenticated TiDB and Render tabs.
- Produces: pushed `main`, one Free Render Web Service, and a live HTTPS/WSS deployment.

- [ ] **Step 1: Run every local quality gate fresh**

```powershell
& 'C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe' vendor\bin\pest
& 'C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe' vendor\bin\pint --test
npm run test:unit
npm run lint
npm run format:check
npm run build
& 'C:\Program Files\Git\bin\bash.exe' -n deploy/render/entrypoint.sh
git diff --check
git status --short --branch
```

Expected: all tests and checks exit 0; worktree is clean.

- [ ] **Step 2: Merge into `main` and verify the merged result**

```powershell
git checkout main
git pull --ff-only origin main
git merge --no-ff feature/realtime-notifications
& 'C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe' vendor\bin\pest
npm run test:unit
npm run build
```

Expected: merge succeeds and all merged-result checks exit 0.

- [ ] **Step 3: Push `main`**

```powershell
git push origin main
```

Expected: origin advances to the verified merge commit without force push.

- [ ] **Step 4: Prepare secrets without exposing them**

Generate `APP_KEY` locally, reset the TiDB password in its Connect dialog, and enter both directly into Render's secret fields. Generate `REVERB_APP_SECRET` through the Blueprint. Never print or commit any of these values.

- [ ] **Step 5: Create the Render service through Chrome**

Select repository `xuannt4t/dormida-work`, branch `main`, and Blueprint/Docker deployment. Before the final create action, visibly verify:

- instance type says Free;
- estimated monthly cost is 0 USD;
- there is exactly one Web Service;
- there is no disk, database, Key Value, worker, or cron resource;
- region is Singapore.

If any condition differs, stop without creating the service.

- [ ] **Step 6: Monitor build and first boot**

Wait for the Docker build and deploy to finish. Confirm logs show Composer install, Vite build, `artisan migrate --force`, Supervisor startup, Nginx, PHP-FPM, queue, scheduler, and Reverb without restart loops.

- [ ] **Step 7: Verify the live application**

Open the Render URL and verify:

1. `GET /up` returns HTTP 200.
2. Login page title ends in `DORMIDA WORK`.
3. Seeded admin can sign in using the migrated TiDB data.
4. Tasks page loads existing tasks.
5. Browser Network shows a WSS connection with status 101.
6. Creating or changing a task produces a database notification and immediate bell/toast update.

- [ ] **Step 8: Confirm free limits and hand off**

Reopen Render service settings and TiDB capacity. Confirm Render plan is Free and TiDB spending limit remains 0 USD. Leave the Render and TiDB tabs open for the user and report the live URL plus the known Free-tier sleep/ephemeral-storage limitations.
