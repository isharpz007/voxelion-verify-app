# Architecture

## Overview

Voxelion Verify is a session-driven identity-verification gateway. OpinionPadi
hands a volunteer off via a signed one-time RS256 JWT; the app walks them
through a server-side state machine (consent → intake → photo → liveness →
result) and issues a one-time completion code OpinionPadi validates over a
bearer-token API. Reviewer staff resolve `manual_review` outcomes through a
basic-auth admin console.

- **Backend:** Laravel 12 (PHP 8.2+), session auth, Inertia.js
- **Frontend:** React 19, Vite, Tailwind CSS v4 — Inertia pages in `resources/js/Pages`
- **DB:** SQLite dev; designed for PostgreSQL in prod
- **Media:** private `media` filesystem disk (`storage/app/media`); S3 in prod
- **Queue:** `sync` dev; `DispatchWebhook` and `ProcessDeletionRequest` are `ShouldQueue`

## Request flows

### Volunteer journey (`routes/web.php`)

```
GET /start?t=<RS256 JWT>  →  verify hand-off (sig, iss, iat, exp≤15min, jti replay)
POST /start               →  create Participant + VerificationSession, regenerate session id
  → /consent → /intake → /photo → /live → /result
  → /exit on decline / withdraw / expiry
```

Every step is gated by the `journey.state` middleware
(`App\Http\Middleware\EnforceSessionState`), which compares the required
state(s) declared on each route against `verification_sessions.state`.
A request in the wrong state is redirected to the correct step; expired
sessions are transitioned to `withdrawn`, audited, and OpinionPadi is
notified by webhook.

States (`App\Enums\SessionState`):
`created → consented → intake_done → photo_ok → live_check` then terminal
`verified | failed | manual_review | withdrawn | declined`.

### OpinionPadi server-to-server API (`routes/api.php`, `op.signature` HMAC guard)

Every call is signed: `X-OpinionPadi-Signature` =
`HMAC_SHA256("<X-OpinionPadi-Timestamp>.<raw body>", OPINIONPADI_API_SECRET)`,
with a 5-minute timestamp tolerance against replay
(`App\Http\Middleware\VerifyWebhookSignature`).

| Endpoint | Purpose |
|---|---|
| `GET /api/v1/completion-codes/{code}` | Validate a `VX-…` code (HMAC-hashed at rest, single-use, TTL-governed) |
| `GET/POST /api/v1/reward-submissions` | List / create reward submissions |
| `POST /api/v1/reward-submissions/{id}/decision` | Approve (re-validates + consumes code) or reject |

Outbound status webhooks (`verified`, `failed`, `manual_review`, `withdrawn`)
are HMAC-SHA256-signed (`X-Voxelion-Signature`) and sent via the
`DispatchWebhook` job with retry/backoff through `OpinionPadiWebhook`.

### Reviewer console (`/admin/*`)

HTTP Basic auth via `admin.basic` (`ADMIN_USERNAME`/`ADMIN_PASSWORD`, fails
closed). Review queue + metrics (`ReviewController`); private media served
through 15-minute signed URLs (`MediaController` + `signed` middleware).

## Application structure (`app/`)

| Path | Role |
|---|---|
| `Enums/SessionState` | State machine: allowed transitions, terminal flags, step routes |
| `Http/Controllers/Journey` | One controller per journey step (Start, Consent, Intake, Photo, Liveness, Result, Exit) |
| `Http/Controllers/Admin` | Reviewer queue/decisions/metrics + signed media |
| `Http/Controllers/Api` | Completion-code + reward-submission API |
| `Http/Controllers/DevHandoffController` | **Local-only** OpinionPadi simulator (`abort_unless(local)`) — mints a dev JWT and redirects to `/start` |
| `Http/Middleware` | `ValidateHandoffToken`, `EnforceSessionState`, `VerifyWebhookSignature`, `AdminBasicAuth`, `HandleInertiaRequests` |
| `Http/Requests` | Form validation per step (`ConsentRequest`, `IntakeRequest`, `PhotoUploadRequest`, `LiveCompleteRequest`) |
| `Services/HandoffTokenService` | RS256 JWT verification + claim/jti rules (+ dev `mint`) |
| `Services/CompletionCodeService` | Code generation, HMAC hashing, single-use semantics |
| `Services/OpinionPadiWebhook` | Signed outbound webhooks |
| `Services/PhotoQualityService` | Server-side photo quality gates |
| `Services/VerificationProvider` | **Vendor seam.** `VerificationProvider` interface bound in `AppServiceProvider`; `VoxelionProvider` is a deterministic local stub until a real biometric vendor is selected |
| `Jobs/DispatchWebhook`, `Jobs/ProcessDeletionRequest`, `Jobs/RunQualityChecks` | Queued webhook delivery; PII/media erasure on withdrawal; photo quality gate (dispatched sync for inline verdicts) |
| `Console/Commands` | `voxelion:keys` (dev RSA keypair), `voxelion:prune` (retention windows — inert until `RETENTION_*` set) |
| `Models` | `Participant`, `VerificationSession`, `Consent`, `MediaAsset`, `VerificationResult`, `CompletionCode`, `RewardSubmission`, `AuditEvent`, `DeletionRequest` |
| `Events/AuditRecorded` + `Listeners/WriteAuditEvent` | All audit trail entries flow through this event → listener pair; the listener is the single `audit_events` writer and strips PII keys from metadata |

## Data model

```
participants ─┬─ verification_sessions ─┬─ verification_results
              │                         ├─ completion_codes
              │                         ├─ media_assets
              │                         └─ consents
reward_submissions  (→ session + code)
deletion_requests   (→ participant; erasure pipeline record)
audit_events        (append-only, PII-free: actor, action, entity, metadata)
```

Plus framework tables: `sessions`, `jobs`, `job_batches`, `failed_jobs`,
`cache`, `cache_locks` (support the `database` session/queue/cache drivers).

## Security boundaries

- **Hand-off:** RS256 signature, issuer check, `iat` not in future, `exp` ≤
  15 min, `jti` single-use (bound to first session that consumes it).
- **Completion codes:** plaintext shown once on `/result`; only an HMAC of
  the code is stored, so the DB cannot be queried by code value.
- **Media:** stored on a private disk; reviewers get short-lived signed URLs.
- **PII minimisation:** audit events and API list responses carry no PII
  (enforced by tests); withdrawal scrubs PII and deletes media.
- **Secrets:** all credentials via env/config — none in code. Dev RSA keys
  live in `storage/app/keys/` (gitignored).
