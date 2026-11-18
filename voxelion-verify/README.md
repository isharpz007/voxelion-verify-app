# Voxelion Verify

Volunteer identity verification gateway — implements the **Voxelion + OpinionPadi
Technical Build Plan** (Laravel + Inertia + React) with the Stitch UI kit.

## Stack

- **Framework**: Laravel 12 (PHP 8.2+)
- **Frontend**: React 19 via Inertia.js + Vite + Tailwind CSS v4
- **DB**: SQLite locally (swap `DB_CONNECTION` for PostgreSQL/MySQL in prod)
- **Queue**: `sync` locally — `ProcessDeletionRequest` and `DispatchWebhook`
  are `ShouldQueue` jobs for `database`/Redis in prod
- **Media**: local `media` disk — private, non-public (S3 private bucket in prod)

## Journey

`/start?t=<RS256 JWT>` → `/consent` → `/intake` → `/photo` → `/live` → `/result`
(exits to `/exit` on decline/withdraw/expiry). The `journey.state` middleware
(`EnforceSessionState`) gates every step server-side against
`verification_sessions.state`: CREATED → CONSENTED → INTAKE_DONE → PHOTO_OK →
LIVE_CHECK → VERIFIED | FAILED | MANUAL_REVIEW (+ WITHDRAWN / DECLINED terminal).

## OpinionPadi server-to-server API (`/api/v1`, bearer-token guarded)

- `GET /api/v1/completion-codes/{code}` — validates a `VX-XXXX-XXXX-XXXX` code
  (hashed at rest, single-use, 72h TTL)
- `POST /api/v1/reward-submissions` — create a reward submission
- `GET /api/v1/reward-submissions[?state=pending]` — list for review
- `POST /api/v1/reward-submissions/{id}/decision` — approve/reject
  (approval re-validates + consumes the code)

Outbound status webhooks (`verified`, `failed`, `manual_review`, `withdrawn`)
are HMAC-signed and dispatched via `DispatchWebhook` with retry/backoff.

## Setup

```bash
composer install
php artisan key:generate
php artisan migrate
php artisan voxelion:keys      # dev RS256 keypair for hand-off tokens
npm install && npm run build   # or: npm run dev
php artisan serve
```

## Local demo

1. Open `http://127.0.0.1:8000/dev/handoff` — the local-only OpinionPadi
   simulator signs a one-time JWT and redirects into `/start`.
   Optional params: `?volunteer=vol-42&task=OP-2026-1234`.
2. Complete the journey (consent → intake → photo → live check → result).
3. The completion code is shown once on `/result` (hashed at rest).
4. Validate it server-to-server. Calls are HMAC-signed —
   `X-OpinionPadi-Signature` is `HMAC_SHA256("<ts>.<body>",
   OPINIONPADI_API_SECRET)`:

   ```bash
   TS=$(date +%s)
   SIG=$(printf '%s' "$TS." | openssl dgst -sha256 -hmac "op-dev-secret" | awk '{print $NF}')
   curl -H "X-OpinionPadi-Timestamp: $TS" -H "X-OpinionPadi-Signature: $SIG" \
     http://127.0.0.1:8000/api/v1/completion-codes/VX-XXXX-XXXX-XXXX
   ```

5. Create + review a reward submission via the API, or the reviewer UI at
   `/admin/review`.

## Docs

- [`docs/RUNNING_LOCALLY.md`](docs/RUNNING_LOCALLY.md) — step-by-step local setup (PowerShell-safe).
- [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md) — modules, state machine, data model, API surface.
- [`docs/DEVELOPMENT.md`](docs/DEVELOPMENT.md) — workflow, conventions, testing.
- [`docs/DEPLOYMENT.md`](docs/DEPLOYMENT.md) — env vars, credentials, production checklist, hosting options.
- [`docs/OPEN_DECISIONS_AND_USER_ACTIONS.md`](docs/OPEN_DECISIONS_AND_USER_ACTIONS.md) — pending decisions and external blockers.

## Notes

- `VerificationProvider` is the vendor seam. The bundled
  `VoxelionProvider` simulates liveness/similarity until the real API lands
  (hard blocker — see `docs/OPEN_DECISIONS_AND_USER_ACTIONS.md`).
- Consent wording is draft pending legal sign-off (`CONSENT_VERSION`).
- Withdrawal dispatches `ProcessDeletionRequest` (media purge + PII scrub +
  processor confirmations) — NDPA traceability via `audit_events`.
