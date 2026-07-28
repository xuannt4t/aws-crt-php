# Sprint 0 — Foundation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Scaffold the DORMIDA WORK app (Laravel 11 + Breeze/Inertia/Vue 3/TypeScript + Tailwind + PrimeVue + MySQL + Redis) directly inside the `dormida-work-ai-kit` repo, with CI and coding-convention tooling wired up, producing a working, empty skeleton ready for Sprint 1 (Identity).

**Architecture:** Standard Laravel 11 app skeleton merged into the existing repo root (alongside `README.md`, `prompts/`, `context/`, `templates/`, `docs/`). Laravel Breeze provides the Inertia + Vue 3 + TypeScript baseline (with a basic auth scaffold reused later by the Identity module). No business logic in this sprint — only tooling, configuration, and CI.

**Tech Stack:** PHP 8.3, Laravel 11, MySQL 8, Redis (via `predis/predis`), Vue 3 + Inertia.js + TypeScript, Tailwind CSS, PrimeVue, Pest, Laravel Pint, ESLint + Prettier, GitHub Actions.

## Global Constraints

- Repo root: `c:/laragon/www/dormida-work-ai-kit` (Git Bash path: `/c/laragon/www/dormida-work-ai-kit`). Do not modify or delete `README.md`, `prompts/`, `context/`, `templates/`, `docs/`, `.git/`, `.claude/`.
- PHP binary (do not add to system PATH — use full path per command): `/c/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe`
- Composer: `/c/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe /c/laragon/bin/composer/composer.phar`
- MySQL client: `/c/laragon/bin/mysql/mysql-8.4.3-winx64/bin/mysql.exe` — connects as `root` with no password at `127.0.0.1:3306`.
- Redis binaries: `/c/laragon/bin/redis/redis-x64-5.0.14.1/redis-server.exe` and `redis-cli.exe` in the same folder. Redis is not running by default — must be started.
- Node v22.22.0 / npm 10.9.4 already on PATH — use plain `node`/`npm`.
- Database name: `dormida_work`.
- Test framework: Pest (per spec). Backend formatter: Laravel Pint (PSR-12). Frontend lint: ESLint + Prettier.
- No Reverb/broadcast config, no PWA, no Spatie Permission/Excel/DomPDF/object storage in this sprint — out of scope (later sprints).
- Every task that changes tracked files ends with a commit. Tasks that only touch local infra (DB/Redis) do not commit.

---

### Task 1: Start Redis and create the MySQL database

**Files:** None (local infra only, no repo changes).

**Interfaces:**
- Produces: a running Redis server on `127.0.0.1:6379` and an empty MySQL database `dormida_work` reachable at `127.0.0.1:3306` as `root`/no password — both required by Task 7 (`.env` config) and Task 8 (`migrate`).

- [ ] **Step 1: Start Redis in the background**

Run (background):
```bash
/c/laragon/bin/redis/redis-x64-5.0.14.1/redis-server.exe /c/laragon/bin/redis/redis-x64-5.0.14.1/redis.windows.conf
```
Use a background-capable run so it keeps listening.

- [ ] **Step 2: Verify Redis responds**

Run: `/c/laragon/bin/redis/redis-x64-5.0.14.1/redis-cli.exe -h 127.0.0.1 -p 6379 ping`
Expected: `PONG`

- [ ] **Step 3: Create the database**

Run:
```bash
/c/laragon/bin/mysql/mysql-8.4.3-winx64/bin/mysql.exe -u root -h 127.0.0.1 -P 3306 -e "CREATE DATABASE IF NOT EXISTS dormida_work CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

- [ ] **Step 4: Verify the database exists**

Run:
```bash
/c/laragon/bin/mysql/mysql-8.4.3-winx64/bin/mysql.exe -u root -h 127.0.0.1 -P 3306 -e "SHOW DATABASES LIKE 'dormida_work';"
```
Expected: one row `dormida_work`.

No commit — no repository files changed.

---

### Task 2: Scaffold a fresh Laravel 11 skeleton in a scratch directory

**Files:**
- Create: temporary directory (via `mktemp -d`), not part of the repo.

**Interfaces:**
- Produces: a complete Laravel 11 app skeleton (`artisan`, `composer.json`, `app/`, `bootstrap/`, `config/`, `database/`, `public/`, `resources/`, `routes/`, `tests/`, etc.) at `$SCRATCH_DIR/app`, which Task 5 merges into the repo.

- [ ] **Step 1: Create the scratch directory and scaffold Laravel**

Run:
```bash
SCRATCH_DIR=$(mktemp -d)
echo "$SCRATCH_DIR" > /tmp/dormida-scratch-dir.txt
/c/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe /c/laragon/bin/composer/composer.phar create-project laravel/laravel:^11.0 "$SCRATCH_DIR/app" --no-interaction
```

- [ ] **Step 2: Verify the skeleton boots**

Run:
```bash
SCRATCH_DIR=$(cat /tmp/dormida-scratch-dir.txt)
cd "$SCRATCH_DIR/app" && /c/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe artisan --version
```
Expected: output starting with `Laravel Framework 11.`

No commit — files live outside the repo.

---

### Task 3: Convert the default test suite to Pest

**Files:**
- Modify (in scratch dir): `$SCRATCH_DIR/app/composer.json`, `$SCRATCH_DIR/app/tests/Pest.php` (created by installer).

**Interfaces:**
- Consumes: Laravel skeleton from Task 2.
- Produces: `./vendor/bin/pest` runnable in the scratch app, reused as-is after the Task 5 merge (relied on by Task 8's baseline test run).

- [ ] **Step 1: Require Pest**

Run:
```bash
SCRATCH_DIR=$(cat /tmp/dormida-scratch-dir.txt)
cd "$SCRATCH_DIR/app"
/c/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe /c/laragon/bin/composer/composer.phar require pestphp/pest pestphp/pest-plugin-laravel --dev --with-all-dependencies --no-interaction
```

- [ ] **Step 2: Run the Pest installer**

Run:
```bash
SCRATCH_DIR=$(cat /tmp/dormida-scratch-dir.txt)
cd "$SCRATCH_DIR/app"
/c/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe artisan pest:install --no-interaction
```

- [ ] **Step 3: Verify Pest runs the default tests**

Run:
```bash
SCRATCH_DIR=$(cat /tmp/dormida-scratch-dir.txt)
cd "$SCRATCH_DIR/app"
./vendor/bin/pest
```
Expected: all tests pass (default `ExampleTest` in Feature and Unit).

No commit — files live outside the repo.

---

### Task 4: Install Breeze (Vue + Inertia + TypeScript + Pest)

**Files (in scratch dir):**
- Create: `$SCRATCH_DIR/app/resources/js/Pages/Auth/*`, `Layouts/*`, `Components/*`, `resources/js/app.ts`, `routes/auth.php`, `tests/Feature/Auth/*`.
- Modify: `$SCRATCH_DIR/app/composer.json`, `package.json`, `routes/web.php`, `resources/views/app.blade.php`.

**Interfaces:**
- Consumes: Pest-enabled skeleton from Task 3.
- Produces: `resources/js/app.ts` (Inertia entrypoint used by Task 9 to register PrimeVue), a working `npm run build`, and passing auth feature tests — all merged into the repo by Task 5.

- [ ] **Step 1: Require Breeze**

Run:
```bash
SCRATCH_DIR=$(cat /tmp/dormida-scratch-dir.txt)
cd "$SCRATCH_DIR/app"
/c/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe /c/laragon/bin/composer/composer.phar require laravel/breeze --dev --no-interaction
```

- [ ] **Step 2: Install the Vue + TypeScript + Pest stack**

Run:
```bash
SCRATCH_DIR=$(cat /tmp/dormida-scratch-dir.txt)
cd "$SCRATCH_DIR/app"
/c/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe artisan breeze:install vue --typescript --pest --no-interaction
```
This installs npm dependencies and runs `npm run build` automatically as its last step.

- [ ] **Step 3: Verify the frontend build and auth tests**

Run:
```bash
SCRATCH_DIR=$(cat /tmp/dormida-scratch-dir.txt)
cd "$SCRATCH_DIR/app"
npm run build
./vendor/bin/pest tests/Feature/Auth
```
Expected: build succeeds (Vite output in `public/build`), auth tests pass.

No commit — files live outside the repo.

---

### Task 5: Merge the scaffold into the repo

**Files:**
- Create in repo root: all top-level entries from `$SCRATCH_DIR/app` except `README.md`, `vendor/`, `node_modules/` (e.g. `app/`, `artisan`, `bootstrap/`, `config/`, `database/`, `public/`, `resources/`, `routes/`, `tests/`, `composer.json`, `composer.lock`, `package.json`, `package-lock.json`, `vite.config.ts`, `tsconfig.json`, `.env.example`, `.gitignore`, `.editorconfig` if present, `phpunit.xml`, `tests/Pest.php`).
- Untouched: repo's existing `README.md`, `prompts/`, `context/`, `templates/`, `docs/`, `.git/`, `.claude/`.

**Interfaces:**
- Consumes: complete scaffold from Task 4.
- Produces: repo root containing a full Laravel app skeleton, ready for `composer install`/`npm install` in Task 6.

- [ ] **Step 1: Copy scaffold files into the repo, excluding README.md/vendor/node_modules**

Run:
```bash
SCRATCH_DIR=$(cat /tmp/dormida-scratch-dir.txt)
cd "$SCRATCH_DIR/app"
find . -mindepth 1 -maxdepth 1 \
  \( -name 'README.md' -o -name 'vendor' -o -name 'node_modules' \) -prune -o \
  -mindepth 1 -maxdepth 1 -exec cp -r {} "/c/laragon/www/dormida-work-ai-kit/" \;
```

- [ ] **Step 2: Verify the repo root now has both the docs and the app skeleton**

Run:
```bash
ls "/c/laragon/www/dormida-work-ai-kit"
```
Expected: both `README.md`, `prompts/`, `context/`, `templates/`, `docs/` AND `artisan`, `app/`, `composer.json`, `package.json`, `resources/`, `routes/` are present.

- [ ] **Step 3: Clean up the scratch directory**

Run:
```bash
SCRATCH_DIR=$(cat /tmp/dormida-scratch-dir.txt)
rm -rf "$SCRATCH_DIR"
rm -f /tmp/dormida-scratch-dir.txt
```

No commit yet — dependencies aren't installed and `.env` isn't configured; commit happens after Task 8's green baseline.

---

### Task 6: Install dependencies in the repo

**Files:**
- Create: `/c/laragon/www/dormida-work-ai-kit/vendor/` (gitignored), `/c/laragon/www/dormida-work-ai-kit/node_modules/` (gitignored).

**Interfaces:**
- Consumes: merged skeleton from Task 5.
- Produces: installed PHP and JS dependencies, required by every later task's verification commands.

- [ ] **Step 1: Install PHP dependencies**

Run:
```bash
cd "/c/laragon/www/dormida-work-ai-kit"
/c/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe /c/laragon/bin/composer/composer.phar install --no-interaction
```
Expected: completes without errors.

- [ ] **Step 2: Install JS dependencies**

Run:
```bash
cd "/c/laragon/www/dormida-work-ai-kit"
npm install
```
Expected: completes without errors.

No commit — `vendor/` and `node_modules/` are gitignored by the copied `.gitignore`.

---

### Task 7: Configure `.env` for MySQL and Redis

**Files:**
- Create: `/c/laragon/www/dormida-work-ai-kit/.env` (gitignored, from `.env.example`).
- Modify: `/c/laragon/www/dormida-work-ai-kit/composer.json`, `composer.lock` (add `predis/predis`).

**Interfaces:**
- Consumes: installed dependencies from Task 6, running Redis/MySQL from Task 1.
- Produces: a working local environment for Task 8's `migrate` and test runs.

- [ ] **Step 1: Add the Predis client** (no `redis` PHP extension is installed, so Laravel needs the userland client)

Run:
```bash
cd "/c/laragon/www/dormida-work-ai-kit"
/c/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe /c/laragon/bin/composer/composer.phar require predis/predis --no-interaction
```

- [ ] **Step 2: Create `.env` from the example**

Run:
```bash
cd "/c/laragon/www/dormida-work-ai-kit"
cp .env.example .env
```

- [ ] **Step 3: Edit `.env` values**

Set these keys (edit the file directly, keep every other Breeze-generated key as-is):
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=dormida_work
DB_USERNAME=root
DB_PASSWORD=

CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis

REDIS_CLIENT=predis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

- [ ] **Step 4: Generate the app key**

Run:
```bash
cd "/c/laragon/www/dormida-work-ai-kit"
/c/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe artisan key:generate
```
Expected: `Application key set successfully.`

- [ ] **Step 5: Run migrations against `dormida_work`**

Run:
```bash
cd "/c/laragon/www/dormida-work-ai-kit"
/c/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe artisan migrate --no-interaction
```
Expected: default Laravel/Breeze migrations (users, cache, jobs, sessions, etc.) run successfully.

- [ ] **Step 6: Verify cache/queue/session hit Redis**

Run:
```bash
cd "/c/laragon/www/dormida-work-ai-kit"
/c/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe artisan tinker --execute="Illuminate\Support\Facades\Cache::put('sprint0-check','ok',10); echo Illuminate\Support\Facades\Cache::get('sprint0-check');"
```
Expected: prints `ok`.

No commit yet — bundled into Task 8's commit once the baseline is verified green.

---

### Task 8: Verify the baseline (Pest, Pint) and make the foundation commit

**Files:**
- All files copied/created by Tasks 5–7 (excluding `.env`, `vendor/`, `node_modules/`, which stay gitignored).

**Interfaces:**
- Consumes: fully configured app from Tasks 5–7.
- Produces: the first tracked commit containing the Laravel 11 + Breeze skeleton, the baseline every later task builds on.

- [ ] **Step 1: Run the full test suite**

Run:
```bash
cd "/c/laragon/www/dormida-work-ai-kit"
./vendor/bin/pest
```
Expected: all tests pass (default example tests + Breeze auth tests).

- [ ] **Step 2: Run Pint in check mode**

Run:
```bash
cd "/c/laragon/www/dormida-work-ai-kit"
./vendor/bin/pint --test
```
Expected: no style violations (Breeze-generated code should already be PSR-12 compliant). If it reports fixable issues, run `./vendor/bin/pint` (without `--test`) to auto-fix, then re-run `--test`.

- [ ] **Step 3: Rebuild the frontend**

Run:
```bash
cd "/c/laragon/www/dormida-work-ai-kit"
npm run build
```
Expected: Vite build succeeds.

- [ ] **Step 4: Stage and commit**

Run:
```bash
cd "/c/laragon/www/dormida-work-ai-kit"
git add -A
git status
```
Verify the staged list contains the Laravel skeleton (`app/`, `artisan`, `composer.json`, `resources/js/...`, etc.) and does **not** contain `.env`, `vendor/`, or `node_modules/`. Then:
```bash
git commit -m "chore: scaffold Laravel 11 + Breeze (Inertia/Vue/TS) foundation"
```

---

### Task 9: Install and wire up PrimeVue

**Files:**
- Modify: `/c/laragon/www/dormida-work-ai-kit/resources/js/app.ts`, `package.json`, `package-lock.json`.

**Interfaces:**
- Consumes: `resources/js/app.ts` Inertia entrypoint from Task 4/5 (uses `createInertiaApp` with a `setup({ el, App, props, plugin })` callback that currently does `createApp({ render: () => h(App, props) }).use(plugin).use(ZiggyVue).mount(el)`).
- Produces: `PrimeVue` registered globally with the Aura theme preset, available to every Page component from Sprint 1 onward.

- [ ] **Step 1: Install PrimeVue**

Run:
```bash
cd "/c/laragon/www/dormida-work-ai-kit"
npm install primevue @primevue/themes
```

- [ ] **Step 2: Register PrimeVue in the Inertia entrypoint**

Open `resources/js/app.ts`. Add these imports near the top:
```ts
import PrimeVue from 'primevue/config';
import Aura from '@primevue/themes/aura';
```
Then, inside the `setup()` callback, change:
```ts
.use(plugin)
.use(ZiggyVue)
.mount(el);
```
to:
```ts
.use(plugin)
.use(ZiggyVue)
.use(PrimeVue, { theme: { preset: Aura } })
.mount(el);
```

- [ ] **Step 3: Verify the build still succeeds**

Run:
```bash
cd "/c/laragon/www/dormida-work-ai-kit"
npm run build
```
Expected: no TypeScript/Vite errors.

- [ ] **Step 4: Commit**

Run:
```bash
cd "/c/laragon/www/dormida-work-ai-kit"
git add resources/js/app.ts package.json package-lock.json
git commit -m "feat: register PrimeVue with Aura theme"
```

---

### Task 10: Add ESLint + Prettier for Vue/TypeScript

**Files:**
- Create: `/c/laragon/www/dormida-work-ai-kit/eslint.config.js`, `.prettierrc.json`, `.prettierignore`.
- Modify: `/c/laragon/www/dormida-work-ai-kit/package.json` (add `lint` script and devDependencies).

**Interfaces:**
- Consumes: `resources/js/**/*.{ts,vue}` from the merged skeleton.
- Produces: `npm run lint` command, consumed by Task 11's CI workflow.

- [ ] **Step 1: Install lint tooling**

Run:
```bash
cd "/c/laragon/www/dormida-work-ai-kit"
npm install -D eslint @eslint/js typescript-eslint eslint-plugin-vue vue-eslint-parser prettier eslint-config-prettier
```

- [ ] **Step 2: Create `eslint.config.js`**

Create `/c/laragon/www/dormida-work-ai-kit/eslint.config.js`:
```js
import js from '@eslint/js';
import tseslint from 'typescript-eslint';
import pluginVue from 'eslint-plugin-vue';
import vueParser from 'vue-eslint-parser';
import eslintConfigPrettier from 'eslint-config-prettier';

export default tseslint.config(
    js.configs.recommended,
    ...tseslint.configs.recommended,
    ...pluginVue.configs['flat/recommended'],
    {
        files: ['**/*.vue'],
        languageOptions: {
            parser: vueParser,
            parserOptions: {
                parser: tseslint.parser,
                extraFileExtensions: ['.vue'],
            },
        },
    },
    {
        rules: {
            'vue/multi-word-component-names': 'off',
        },
    },
    eslintConfigPrettier,
    {
        ignores: ['vendor/**', 'node_modules/**', 'public/build/**'],
    },
);
```

- [ ] **Step 3: Create `.prettierrc.json`**

Create `/c/laragon/www/dormida-work-ai-kit/.prettierrc.json`:
```json
{
    "semi": true,
    "singleQuote": true,
    "tabWidth": 4,
    "printWidth": 120,
    "trailingComma": "all"
}
```

- [ ] **Step 4: Create `.prettierignore`**

Create `/c/laragon/www/dormida-work-ai-kit/.prettierignore`:
```text
vendor
node_modules
public/build
```

- [ ] **Step 5: Add the `lint` script**

In `package.json`, inside `"scripts"`, add:
```json
"lint": "eslint resources/js"
```

- [ ] **Step 6: Run lint and fix any findings**

Run:
```bash
cd "/c/laragon/www/dormida-work-ai-kit"
npm run lint
```
Expected: no errors. If Breeze-generated files trigger findings, fix them directly in `resources/js/` (do not weaken the ruleset to silence them) and re-run until clean.

- [ ] **Step 7: Commit**

Run:
```bash
cd "/c/laragon/www/dormida-work-ai-kit"
git add eslint.config.js .prettierrc.json .prettierignore package.json package-lock.json
git status
```
(Also `git add` any `resources/js/` files touched while fixing lint findings in Step 6.)
```bash
git commit -m "chore: add ESLint + Prettier for Vue/TypeScript"
```

---

### Task 11: Add the GitHub Actions CI workflow

**Files:**
- Create: `/c/laragon/www/dormida-work-ai-kit/.github/workflows/ci.yml`.

**Interfaces:**
- Consumes: `composer.json`, `package.json`, `./vendor/bin/pint`, `npm run lint`, `npm run build`, `php artisan test` — all established by Tasks 6–10.
- Produces: CI gate that runs on every push/PR to `main`.

- [ ] **Step 1: Create the workflow file**

Create `/c/laragon/www/dormida-work-ai-kit/.github/workflows/ci.yml`:
```yaml
name: CI

on:
  push:
    branches: [main]
  pull_request:
    branches: [main]

jobs:
  build:
    runs-on: ubuntu-latest

    services:
      mysql:
        image: mysql:8
        env:
          MYSQL_ALLOW_EMPTY_PASSWORD: yes
          MYSQL_DATABASE: dormida_work
        ports:
          - 3306:3306
        options: >-
          --health-cmd="mysqladmin ping"
          --health-interval=10s
          --health-timeout=5s
          --health-retries=5
      redis:
        image: redis:7
        ports:
          - 6379:6379
        options: >-
          --health-cmd="redis-cli ping"
          --health-interval=10s
          --health-timeout=5s
          --health-retries=5

    steps:
      - uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'
          extensions: mbstring, pdo_mysql, redis

      - name: Copy .env
        run: cp .env.example .env

      - name: Install PHP dependencies
        run: composer install --no-interaction --prefer-dist

      - name: Check code style (Pint)
        run: ./vendor/bin/pint --test

      - name: Generate app key
        run: php artisan key:generate

      - name: Setup Node
        uses: actions/setup-node@v4
        with:
          node-version: '22'
          cache: 'npm'

      - name: Install JS dependencies
        run: npm ci

      - name: Lint JS/TS
        run: npm run lint

      - name: Build frontend
        run: npm run build

      - name: Run migrations
        run: php artisan migrate --force
        env:
          DB_HOST: 127.0.0.1
          DB_PORT: 3306
          DB_DATABASE: dormida_work
          DB_USERNAME: root
          DB_PASSWORD: ''

      - name: Run tests
        run: ./vendor/bin/pest
        env:
          DB_HOST: 127.0.0.1
          DB_PORT: 3306
          DB_DATABASE: dormida_work
          DB_USERNAME: root
          DB_PASSWORD: ''
          REDIS_HOST: 127.0.0.1
          REDIS_PORT: 6379
```

- [ ] **Step 2: Validate YAML syntax locally**

Run:
```bash
cd "/c/laragon/www/dormida-work-ai-kit"
node -e "console.log(require('yaml').parse(require('fs').readFileSync('.github/workflows/ci.yml','utf8')) ? 'valid' : 'invalid')" 2>/dev/null || python -c "import yaml,sys; yaml.safe_load(open('.github/workflows/ci.yml')); print('valid')"
```
Expected: `valid`. (If neither `yaml` npm package nor `python`/`pyyaml` is available locally, skip this step — the workflow will be validated when it actually runs on the next push.)

- [ ] **Step 3: Commit**

Run:
```bash
cd "/c/laragon/www/dormida-work-ai-kit"
git add .github/workflows/ci.yml
git commit -m "ci: add GitHub Actions workflow for foundation checks"
```

---

### Task 12: Final full verification pass

**Files:** None (verification only).

**Interfaces:**
- Consumes: everything from Tasks 1–11.
- Produces: confidence that Sprint 0 is a complete, working baseline for Sprint 1.

- [ ] **Step 1: Run the complete local check suite**

Run each in `/c/laragon/www/dormida-work-ai-kit`:
```bash
/c/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe /c/laragon/bin/composer/composer.phar install --no-interaction
./vendor/bin/pint --test
npm ci
npm run lint
npm run build
./vendor/bin/pest
```
Expected: every command exits 0.

- [ ] **Step 2: Confirm working tree is clean**

Run:
```bash
cd "/c/laragon/www/dormida-work-ai-kit"
git status
```
Expected: no unstaged/untracked changes other than `.env`, `vendor/`, `node_modules/`, `public/build/` (all gitignored).

- [ ] **Step 3: Report to the user**

Summarize: commits made, how to run the dev server (`php artisan serve` + `npm run dev`), that `.env` was created locally and is not committed, and that Sprint 0 is complete — ready to brainstorm Sprint 1 (Identity: Authentication, User, Organization, Role & Permission, Audit log).

No commit — reporting step only.
