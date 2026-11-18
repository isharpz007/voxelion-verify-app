# External Credentials Required

Everything production needs from outside the repo — one row per
credential, where it's set, and what stands in for it today. All values
must live in a secrets manager in production; **never committed**.

## OpinionPadi integration

| Credential | Env var | Today | To get it |
|---|---|---|---|
| OpinionPadi RS256 **public** key (verifies hand-off JWTs) | `OPINIONPADI_PUBLIC_KEY_PATH` | Dev keypair in `storage/app/keys/` (gitignored, `voxelion:keys`) | OpinionPadi publishes their public key / JWKS |
| API HMAC shared secret (signs their calls to `/api/v1/*`) | `OPINIONPADI_API_SECRET` | `op-dev-secret` | Agree a high-entropy secret with OpinionPadi |
| Webhook signing secret (signs our status events to them) | `OPINIONPADI_WEBHOOK_SECRET` | `dev-webhook-secret` | Same — ideally a *different* secret than the API one |
| Webhook delivery URL on their side | `OPINIONPADI_WEBHOOK_URL` | unset → webhooks are a no-op | OpinionPadi endpoint |
| OpinionPadi dashboard URL (error-page links) | `OPINIONPADI_DASHBOARD_URL` | `opinionpadi.example` placeholder | OpinionPadi |
| Hand-off issuer claim | `OPINIONPADI_ISSUER` | `opinionpadi` | Confirm their `iss` value |

## Verification provider (blocked on Phase 0 #1)

| Credential | Env var | Today | To get it |
|---|---|---|---|
| Vendor API key/secret | provider-specific (e.g. `SMILE_ID_*`) | `VoxelionProvider` stub, no creds | Vendor sandbox after procurement — see `FACE_MATCHING_RESEARCH.md` |
| Provider selection | `VERIFICATION_PROVIDER` | `voxelion` | Flip once real vendor bound |

## Reviewer console

| Credential | Env var | Today | To get it |
|---|---|---|---|
| Reviewer basic-auth user/pass | `ADMIN_USERNAME`, `ADMIN_PASSWORD` | unset → fails closed | Staff SSO in prod, or secrets-manager creds interim |

## Infrastructure

| Service | Env vars | Today | To get it |
|---|---|---|---|
| PostgreSQL | `DB_CONNECTION=pgsql`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | SQLite file | Managed PG instance |
| Redis (queue/cache/session) | `REDIS_HOST`, `REDIS_PASSWORD`, `REDIS_PORT`, plus `QUEUE_CONNECTION`, `CACHE_STORE`, `SESSION_DRIVER` | `database`/`sync`/`file` drivers | Managed Redis |
| S3-compatible private bucket | `FILESYSTEM_DISK=s3`, `AWS_*` (+ `AWS_ENDPOINT` for R2/B2) | local `media` disk | Private bucket + scoped IAM keys; needs `league/flysystem-aws-s3-v3` added |
| SMTP (verification-code emails) | `MAIL_*` | `log` mailer | SMTP provider creds |
| Trusted proxies | `TRUSTED_PROXIES` | unset | LB/proxy IPs once deployed behind one |
| `APP_KEY` | `APP_KEY` | `key:generate` locally | Generate per environment |

## Policy env (not secrets, but human-gated)

| Setting | Env var | Gate |
|---|---|---|
| Consent wording version | `CONSENT_VERSION` | Legal approval (Phase 0 #3) |
| Retention periods | `RETENTION_SESSION_DAYS`, `RETENTION_CODE_DAYS`, `RETENTION_AUDIT_DAYS` | DPO approval (Phase 0 #4) |

Key rotation note: hand-off verification reads a single public key file;
rotating means OpinionPadi publishes the new key and both are accepted
through a ≤15-min overlap window (`OPINIONPADI_HANDOFF_TTL`).
