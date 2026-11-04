---
phase: 03-application-foundation-and-deployment-baseline
plan: 01
subsystem: foundation
tags:
  - laravel
  - livewire
  - spatie
  - horizon
  - mysql
  - redis
  - ci
  - deployment
requires: []
provides:
  - Laravel 12 application runtime skeleton in repository root
  - Health check probe endpoints (/up, /healthz) per FOUN-05
  - MySQL 8.4 database configuration with InnoDB utf8mb4 collation
  - Redis queue infrastructure with Horizon supervisors (default, notifications, documents)
  - Daily rotating log channel and structured JSON audit log channel per DEC-11
  - GitHub Actions CI workflow with MySQL 8.4 and Redis service containers
  - Production deployment and disaster recovery runbook
affects:
  - 03-02-PLAN.md
  - 03-03-PLAN.md
  - 03-04-PLAN.md
tech-stack:
  added:
    - laravel/framework: ^12.0 (v12.71.0)
    - livewire/livewire: ^3.5 (v3.8.11)
    - spatie/laravel-permission: ^6.0 (v6.25.0)
    - laravel/horizon: ^5.30 (v5.50.1)
    - pestphp/pest: ^3.0 (v3.8.7)
    - pestphp/pest-plugin-laravel: ^3.0
    - pestphp/pest-plugin-arch: ^3.0
    - tailwindcss: ^4.0.0
    - alpinejs: ^3.14.8
  patterns:
    - Multi-guard session isolation (emk_web_session vs emk_member_session per DEC-06)
    - Dedicated daily rotating and structured JSON audit logging per DEC-11
    - Decoupled Horizon supervisor profiles (default, notifications, documents)
    - MySQL 8.4 containerized CI with cancel-in-progress concurrency
key-files:
  created:
    - routes/web.php
    - routes/console.php
    - config/database.php
    - config/queue.php
    - config/horizon.php
    - config/logging.php
    - config/session.php
    - .env.example
    - .github/workflows/ci.yml
    - Docs/DEPLOYMENT-RUNBOOK.md
    - tests/Feature/HealthCheckTest.php
  modified:
    - composer.json
    - composer.lock
    - package.json
    - bootstrap/app.php
    - bootstrap/providers.php
decisions:
  - "[Plan 03-01]: Initialize Laravel 12 directly in repository root with Livewire, Spatie Permission, and Horizon per FOUN-01"
  - "[Plan 03-01]: Configure multi-guard session isolation naming web staff cookie emk_web_session and member portal cookie emk_member_session per DEC-06"
  - "[Plan 03-01]: Configure MySQL 8.4 InnoDB engine with utf8mb4_unicode_ci collation and Redis Horizon supervisors per FOUN-02"
  - "[Plan 03-01]: Install containerized GitHub Actions CI pipeline running PHP 8.3, MySQL 8.4, and Redis with Pest testing per FOUN-05"
status: complete
actuals:
  tokens: 138845
  tasks: 3
  commits: 3
  plan_head_before: 7ca77ee825fd9df64d7bb786192a13fb6bd2ec31
  plan_head_after: 48c4cc79b5064f1f0354db618d878345ae90be38
---

# Phase 3 Plan 01: Laravel 12 Application Skeleton, Horizon Queues, Health Tracer, and CI Pipeline Summary

## Overview

Plan 03-01 initialized the deployable Laravel 12 monolithic application skeleton directly in the repository root (`d:\EMK_Protal`), installed all primary framework and frontend dependencies, established multi-guard session isolation, implemented an end-to-end health check tracer route, configured runtime infrastructure (MySQL 8.4, Redis, Horizon queue supervisors, logging, scheduled tasks), and created an automated GitHub Actions CI pipeline and comprehensive production deployment runbook.

## Key Changes by Task

### Task 1 (tracer): Bootstrap Laravel 12 Monolith Skeleton and End-to-End Health Probe Tracer
- Scaffolded Laravel 12 application skeleton with PHP 8.3 in repository root per **FOUN-01**.
- Installed core framework packages via Composer:
  - `laravel/framework` (^12.0)
  - `livewire/livewire` (^3.5)
  - `spatie/laravel-permission` (^6.0)
  - `laravel/horizon` (^5.30)
  - `ramsey/uuid` (^4.7)
  - Dev/testing suite: `pestphp/pest` (^3.0), `pestphp/pest-plugin-laravel`, `pestphp/pest-plugin-arch`, and `phpunit/phpunit` (^11.5).
- Configured frontend tooling in `package.json` with Tailwind CSS v4, Vite, `@tailwindcss/vite`, and Alpine.js.
- Implemented `/up` and `/healthz` health check endpoints in `routes/web.php` returning HTTP 200 with JSON payload (`status: ok`, ISO8601 timestamp, and application environment) per **FOUN-05** and **T-03-01**.
- Configured multi-guard session isolation in `config/session.php`:
  - Web staff session cookie: `emk_web_session`
  - Member portal session cookie: `emk_member_session` per **DEC-06 (D-06)**.
- Organized `bootstrap/providers.php` with deterministic provider ordering.
- Codified `tests/Feature/HealthCheckTest.php` asserting HTTP 200 and JSON status responses.
- Verified tracer end-to-end and committed atomically in `cc568f0`.

### Task 2: Configure MySQL 8.4, Redis Horizon Queues, Scheduled Tasks, and Logging Channels
- Configured `config/database.php` for MySQL 8.4 with `utf8mb4` charset, `utf8mb4_unicode_ci` collation, strict mode, and default `InnoDB` engine per **FOUN-02**.
- Configured Redis connection parameters for cache, default, and queues with retry intervals and decorrelated jitter backoff.
- Configured `config/queue.php` setting Redis as default queue driver with `failed_jobs` table persistence.
- Configured `config/horizon.php` with queue supervisors:
  - `supervisor-default` (queue: `default`, balance: `auto`, maxProcesses: 3)
  - `supervisor-notifications` (queue: `notifications`, balance: `simple`, maxProcesses: 2)
  - `supervisor-documents` (queue: `documents`, balance: `simple`, maxProcesses: 2)
- Configured `config/logging.php`:
  - `daily` rotating file channel (`storage/logs/laravel.log`) with 14-day retention.
  - Dedicated `audit` channel writing structured JSON security events to `storage/logs/audit.log` per **DEC-11 (D-11)**.
- Registered scheduled tasks in `routes/console.php` for `horizon:snapshot` (every 5 minutes) and `queue:prune-failed` (daily).
- Documented complete runtime template in `.env.example`.
- Committed atomically in `5cb699a`.

### Task 3: Automated GitHub Actions CI Pipeline and Environment Deployment Runbook
- Created `.github/workflows/ci.yml` triggered on pull requests and pushes to `main`.
- Configured concurrency control: `concurrency: group: ${{ github.workflow }}-${{ github.ref }}, cancel-in-progress: true` to prevent concurrent build races.
- Provisioned MySQL 8.4 (`services: mysql: image: mysql:8.4`) and Redis 7 (`redis: image: redis:7-alpine`) service containers with automated health checks to test database triggers and queues in CI.
- Configured CI steps for PHP 8.3 setup, Composer dependency caching and validation (`composer validate --strict`, `composer audit`), Node.js 20 asset building (`npm run build`), and Pest test execution (`php artisan test --parallel`).
- Created `Docs/DEPLOYMENT-RUNBOOK.md` covering host prerequisites (PHP 8.3+, Composer 2.x, MySQL 8.4, Redis 7.x, Node.js 20+), environment secret provisioning, zero-downtime deployment sequences, Horizon systemd daemon configuration, scheduled maintenance, and disaster recovery procedures (RPO <= 1h, RTO <= 4h).
- Committed atomically in `48c4cc7`.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking Issue] Configured platform extensions for Windows environment**
- **Found during:** Task 1 dependency installation
- **Issue:** `composer install` failed on Windows host because `laravel/horizon` requires POSIX extensions `ext-pcntl` which are not compiled into standard Windows PHP binaries.
- **Fix:** Added platform emulation configuration in `composer.json` (`platform: { "ext-pcntl": "8.3.36", "ext-posix": "8.3.36" }`). This allows Composer to resolve and install dependencies seamlessly on Windows while running full native extensions in the Linux CI/production container.
- **Files modified:** `composer.json`
- **Commit:** `cc568f0`

**2. [Rule 1 - Bug] Aligned phpunit/phpunit version constraint with pestphp/pest ^3.0**
- **Found during:** Task 1 dependency resolution
- **Issue:** `pestphp/pest` ^3.0 requires `phpunit/phpunit` ^11.5, which conflicted with `phpunit/phpunit` ^12.0 in the default Laravel 13 template skeleton.
- **Fix:** Adjusted `phpunit/phpunit` constraint to `^11.5` in `composer.json`.
- **Files modified:** `composer.json`
- **Commit:** `cc568f0`

## Known Stubs

None. All configuration files, routes, providers, CI workflow, and runbook specifications are fully wired and functional.

## Threat Flags

None. All files adhere strictly to the STRIDE mitigations specified in `03-01-PLAN.md` (T-03-01 health probe info disclosure defense, T-03-02 queue worker isolation, T-03-03 verified CI container dependencies, and T-03-SC package audit verification).

## Self-Check: PASSED

1. **Created files exist:**
   - `composer.json`: FOUND
   - `package.json`: FOUND
   - `config/database.php`: FOUND
   - `config/queue.php`: FOUND
   - `config/horizon.php`: FOUND
   - `config/logging.php`: FOUND
   - `config/session.php`: FOUND
   - `routes/web.php`: FOUND
   - `routes/console.php`: FOUND
   - `bootstrap/app.php`: FOUND
   - `bootstrap/providers.php`: FOUND
   - `.env.example`: FOUND
   - `.github/workflows/ci.yml`: FOUND
   - `Docs/DEPLOYMENT-RUNBOOK.md`: FOUND
   - `tests/Feature/HealthCheckTest.php`: FOUND

2. **Commits exist in Git history:**
   - `cc568f0`: FOUND
   - `5cb699a`: FOUND
   - `48c4cc7`: FOUND
