# Deployment

How to take this app from local dev to production. **Nothing here deploys
itself — several items are gated on external decisions** (see
`OPEN_DECISIONS_AND_USER_ACTIONS.md`); the hard blocker is selecting the
face-verification vendor.

## Hosting options

| Option | Verdict | Notes |
|---|---|---|
| **C — Laravel-native host** (Render/Fly/VPS, always-on PHP) | **Recommended** | Queue workers + `schedule:run` cron + no request-body cap, natively. Fewest moving parts for a biometric/PII workload. |
| **B — Vercel Container runtime** | Viable, more work | Requires Dockerfile + Caddyfile + `vercel.json`, presigned-upload refactor (4.5 MB body cap means media can't transit Laravel), a Daemon or cron-drained queue, and a secret-guarded `/internal/schedule` route to replace host cron. Beta-runtime risk. |
| A — split frontend/host | Rejected | Largest refactor, no benefit. |

## Required external credentials

Everything is already wired to env vars — provide real values to go live.

| # | Credential | Env var(s) | Status |
|---|---|---|---|
| 1 | Face-verification provider account + API key | `VERIFICATION_PROVIDER` + vendor-specific vars (TBD) | **BLOCKED — vendor not selected** |
| 2 | OpinionPadi production RS256 public key | `OPINIONPADI_PUBLIC_KEY_PATH` | Dev keypair at `storage/app/keys/` |
| 3 | OpinionPadi API HMAC shared secret | `OPINIONPADI_API_SECRET` | Dev value `op-dev-secret` |
| 4 | Webhook URL + signing secret | `OPINIONPADI_WEBHOOK_URL`, `OPINIONPADI_WEBHOOK_SECRET` | URL unset (no-op locally) |
| 5 | Reviewer credentials | `ADMIN_USERNAME`, `ADMIN_PASSWORD` | Dev values; prod should use staff SSO |
| 6 | PostgreSQL | `DB_*` | SQLite locally |
| 7 | Redis (or `database` driver) | `REDIS_*`, `QUEUE_CONNECTION`, `CACHE_STORE`, `SESSION_DRIVER` | `sync`/`file` locally; `database` tables exist |
| 8 | Private S3/R2 bucket | `AWS_*`, `FILESYSTEM_DISK` | Local `media` disk |
| 9 | OpinionPadi dashboard URL | `OPINIONPADI_DASHBOARD_URL` | Placeholder value |
| 10 | Secrets manager | — | Keys must never enter a committed `.env` |

## Environment variables

`.env.example` is the authoritative annotated list. Production values that
differ from local defaults:

**Core**
- `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://<domain>`
- `APP_KEY` — `php artisan key:generate --show`
- `TRUSTED_PROXIES` — proxy IPs/CIDRs or `*` when behind a load balancer /
  Vercel (required for correct client IPs and `https` signed URLs)
- `SESSION_SECURE_COOKIE=true`, `SESSION_SAME_SITE=lax`
- `LOG_CHANNEL=stderr` (containers) or per host

**Database** — `DB_CONNECTION=pgsql` + `DB_HOST/PORT/DATABASE/USERNAME/PASSWORD`,
`DB_SSLMODE=require`. (SQLite is not viable on serverless or multi-instance.)

**State backends** — `SESSION_DRIVER=database|redis`,
`CACHE_STORE=database|redis`, `QUEUE_CONNECTION=database|redis`.
The `database` tables are migrated; Redis needs `REDIS_*`.

**Media** — `FILESYSTEM_DISK=s3` + `AWS_ACCESS_KEY_ID`,
`AWS_SECRET_ACCESS_KEY`, `AWS_DEFAULT_REGION`, `AWS_BUCKET`
(+ `AWS_ENDPOINT`/`AWS_USE_PATH_STYLE_ENDPOINT` for R2/B2). Private bucket only.
Requires `league/flysystem-aws-s3-v3` (not yet installed).

**OpinionPadi** — `OPINIONPADI_ISSUER`, `OPINIONPADI_PUBLIC_KEY_PATH` (or PEM),
`OPINIONPADI_HANDOFF_TTL=900`, `OPINIONPADI_API_SECRET`,
`OPINIONPADI_WEBHOOK_URL`, `OPINIONPADI_WEBHOOK_SECRET`,
`OPINIONPADI_DASHBOARD_URL`.

**Journey/policy** — `VERIFICATION_SESSION_TTL=60`,
`COMPLETION_CODE_TTL=72`, `PHOTO_MAX_RETRIES=3`, `LIVENESS_MAX_RETRIES=3`,
`SIMILARITY_THRESHOLD=0.80`, `CONSENT_VERSION` (only after Legal approval),
`ADMIN_MEDIA_URL_TTL=15`.

**Retention** — `RETENTION_SESSION_DAYS`, `RETENTION_CODE_DAYS`,
`RETENTION_AUDIT_DAYS`: **leave unset until the DPO approves periods.** While
unset, `voxelion:prune` deletes nothing.

**Vercel path only** — `PORT` (auto-set), `INTERNAL_SCHEDULE_SECRET` guarding a
cron-invoked schedule route, `CRON_SECRET`.

## Build & release process

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan config:cache route:cache view:cache
```

- **Queue worker** (always-on hosts): `php artisan queue:work --tries=3`
  under supervisor — serves `DispatchWebhook` + `ProcessDeletionRequest`.
- **Scheduler:** cron `* * * * * php artisan schedule:run` (runs
  `voxelion:prune` daily — inert until retention windows are set).
- Keep `tests/`, dev tooling, `.env`, and `storage/app/keys/` out of the
  deployed image (`.dockerignore` when containerising).

## Production security checklist

Already enforced in code/tests:

- Server-side journey state machine; session regenerated at hand-off
- RS256 hand-off verification: signature, issuer, `iat`, `exp` ≤ 15 min, `jti` replay
- `/start` POST throttled 10/min; `/api/v1` throttled 60/min + bearer `hash_equals`
- `/admin/*` basic auth fails closed when credentials unset
- Signed media URLs (default 15-min TTL) gated behind admin auth
- Completion codes HMAC-hashed at rest — plaintext never stored or queryable
- Upload validation: mime/size/dimensions server-side
- PII excluded from audit metadata, logs, and API list responses (tested)
- HMAC-signed outbound webhooks; `/dev/handoff` dead outside `local`
- HTTPS forced in production (`URL::forceScheme`) + trusted-proxy support

Config/ops items to verify at deploy:

- `APP_DEBUG=false`, generic error pages; TLS everywhere + HSTS at the edge
- `TRUSTED_PROXIES` set; `SESSION_SECURE_COOKIE=true`
- DB over TLS (`DB_SSLMODE=require`), least-privilege DB user
- Media bucket private; presigned uploads when fronting with a body-capped host
- `composer install --no-dev`; run `npm audit` on the toolchain
- Replace basic-auth admin with staff SSO; rotate all dev secrets

## Deploy order (once unblocked)

1. Decide architecture (recommend Option C) and select the biometric vendor.
2. Legal/DPO: approve consent text → bump `CONSENT_VERSION`; approve
   retention periods → set `RETENTION_*`; confirm expiry→deletion policy.
3. Obtain OpinionPadi production key/token/webhook details; confirm the
   webhook event contract.
4. Provision Postgres, object storage, (optional) Redis, secrets manager, domain.
5. Implement the real `VerificationProvider` + provider erasure call in
   `ProcessDeletionRequest`; add `flysystem-aws-s3-v3` for S3 media.
6. Deploy to staging; run the smoke checklist below; then production.

## Staging/production smoke checklist

- [ ] Clean build; `migrate --force`; `GET /up` → 200
- [ ] Signed hand-off → consent → intake → photo → live → result + code
- [ ] Retry paths: photo ×3 → `manual_review` with evidence; liveness fail → retry
- [ ] API: code validate → submit → approve → `consumed`; bad auth → 401
- [ ] Admin auth + queue + signed media view
- [ ] Outbound webhook delivered and HMAC verifies on OpinionPadi's side
- [ ] Withdrawal → media deleted + PII scrubbed + deletion record
- [ ] HTTPS-only: secure cookies, `https` signed URLs, no mixed content
- [ ] `voxelion:prune` runs on schedule (no-op until windows set)

## Indicative service costs (early-launch volume)

Postgres (Neon/Supabase) $0–19/mo · S3/R2 private bucket ~$0–2/mo ·
Redis (Upstash, optional) $0–10/mo · host: Option C ~$7–25/mo, Vercel Pro
~$20/mo. Verify current pricing before purchasing.
