# Development

## Prerequisites

- PHP 8.2+ (ext: `mbstring`, `openssl`, `pdo_sqlite`, `gd`, `curl`)
- Composer 2 · Node 20+ / npm
- No external services needed — SQLite + `sync` queue + local disk by default.

## Setup

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan voxelion:keys     # dev RS256 keypair → storage/app/keys/
```

Or run everything at once: `composer setup`.

## Running

```bash
composer dev      # serve + queue:listen + pail logs + vite (concurrently)
# or individually:
php artisan serve
npm run dev
```

### Local demo flow

1. `http://127.0.0.1:8000/dev/handoff` — local-only OpinionPadi simulator
   (`?volunteer=vol-42&task=OP-2026-1234` to override defaults). Registered
   only when `APP_ENV=local`; dead everywhere else.
2. Walk the journey: consent → intake → photo → live check → result.
3. The completion code is displayed once on `/result`.
4. Validate it like OpinionPadi would (HMAC-signed — see
   `VerifyWebhookSignature`):

   ```bash
   TS=$(date +%s)
   SIG=$(printf '%s' "$TS." | openssl dgst -sha256 -hmac "op-dev-secret" | awk '{print $NF}')
   curl -H "X-OpinionPadi-Timestamp: $TS" -H "X-OpinionPadi-Signature: $SIG" \
     http://127.0.0.1:8000/api/v1/completion-codes/VX-XXXX-XXXX-XXXX
   ```

5. Reviewer console at `/admin/review` (set `ADMIN_USERNAME`/`ADMIN_PASSWORD`
   in `.env` first — access fails closed without them).

## Testing

| Suite | Command |
|---|---|
| PHP feature tests (SQLite `:memory:`) | `php artisan test` or `composer test` |
| Frontend unit tests (Vitest + jsdom) | `npm test` |
| Formatting check (Pint) | `./vendor/bin/pint --test` |
| Fix formatting | `./vendor/bin/pint` |
| Static analysis (Larastan, level 5) | `./vendor/bin/phpstan analyse` |

CI (`.github/workflows/ci.yml`) runs all of the above plus `composer validate`
and a production `npm run build` on every push/PR.

## Conventions

- **PHP:** PSR-12 via Laravel Pint — run it before committing. PHPStan must
  report 0 errors on `app/` and `routes/`.
- **Journey state:** never mutate `verification_sessions.state` outside the
  state machine; each route declares its accepted states via
  `journey.state:<states>` and the middleware redirects accordingly.
- **Auditability:** any state transition, consent event, or admin action must
  write an `AuditEvent` — with **no PII** in `metadata_json`.
- **PII:** never log, put in audit metadata, or return in API list responses.
  Media goes only to the private `media` disk.
- **Frontend:** Inertia pages in `resources/js/Pages`, shared components in
  `resources/js/Components`. Components get a Vitest test alongside
  (`*.test.jsx`) when they contain logic worth asserting.
- **Jobs:** anything with external I/O (webhooks, erasure calls) is a
  `ShouldQueue` job — dispatch, don't call inline.

## Useful commands

```bash
php artisan voxelion:prune        # retention sweep — inert until RETENTION_* set
php artisan route:list            # 30 routes: journey, /api/v1, /admin, /dev
php artisan migrate:fresh         # rebuild local DB
```
