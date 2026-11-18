# Open Decisions & User Actions Required

**Updated:** 2026-11-17 — all code-side work is complete; only human/external items remain.

## Blocking (the only hard blocker)

### 1. Face-verification provider — **BLOCKED · Engineering + Vendor**
- Provide: vendor name, sandbox credentials, API docs covering auth, accepted **video formats (`webm`/`mp4` — confirm or specify transcode)**, photo limits, response schema, hard-fail verdicts, **erasure/deletion API**, rate limits, pricing.
- Plan requires NIST FRTE West-Africa evidence — `FACE_MATCHING_RESEARCH.md` referenced but absent from workspace.
- Ready on our side: `VerificationProvider` interface, `hard_fail` → `Failed` mapping (new), retry/manual-review logic, deletion hook awaiting a real erasure call.

## Needs your decision (no code risk either way)

| # | Question | Current behaviour | Options |
|---|---|---|---|
| 2 | Code "shown once" vs persistent-in-session | Code re-displays on `/result` refresh until browser session ends | Keep current (UX-friendly) or strict once via `pull()` |
| 3 | Should session **expiry also delete PII**? | Expiry → `withdrawn` + OpinionPadi notified + audited; PII kept for retention window | Legal/DPO decides — implementable in the middleware hook |
| 4 | Webhook event contract with OpinionPadi | `declined`/`expired` both sent as `withdrawn` + `reason`; terminal-only now | Confirm event names/enum on their side |
| 5 | Accessibility fallback policy | No-MediaRecorder → still-frame capture; no camera → `manual_review` route | Document-upload + manual review? Assisted verification? |
| 6 | Intake fields vs spec §5.3 | 6 fields implemented | Confirm or supply canonical list |
| 7 | Reward approval policy | Reviewer-led only | Keep, or define auto-approve criteria |
| 8 | Screenshot rules | `screenshot_s3_key` stored as reference | Define formats/size/ACL/who-uploads |
| 9 | Consent wording + NDPA lawful basis | `v1.0-draft` | Legal approves → I bump `CONSENT_VERSION` |
| 10 | Retention periods | Windows off; command now safe (`P0-2` fixed) | DPO approves periods → set `RETENTION_*` env vars |

## Credentials/accounts to provide when deploying

Production OpinionPadi public key · `OPINIONPADI_API_TOKEN` · `OPINIONPADI_WEBHOOK_URL` + secret · `ADMIN_*` creds → staff SSO · Postgres · Redis · S3 private bucket · secrets manager · real `OPINIONPADI_DASHBOARD_URL` · HTTPS/proxy config. Full list: `EXTERNAL_CREDENTIALS_REQUIRED.md`.

## Resolved since the last version of this doc

- Token/session rules incl. `iat` — now fully enforced + tested.
- `manual_review`-without-evidence — failed photo now preserved (`accepted=false`).
- Misleading `failed` webhooks — terminal-only now.
- Silent session expiry — webhook + audit now fire.
- Retention prune over-deletion — scoping fixed; safe to enable once periods are approved.
