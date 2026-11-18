# Open Decisions & User Actions Required

**Updated:** 2026-11-18 — Phase 0 discovery is complete. Every decision
now has a recorded answer: decided items are implemented + tested;
proposed items carry a concrete recommendation and a named owner for
sign-off. **The canonical record is `PHASE_0_DECISIONS.md`** — this file
tracks only what humans still need to do.

## Blocking (the only hard blocker)

### 1. Face-verification provider — **PROPOSED · Engineering + Vendor**
- **Recommendation recorded:** Smile ID (primary) / Dojah (fallback) —
  evidence and procurement checklist in `FACE_MATCHING_RESEARCH.md`
  (now written; was previously referenced-but-absent).
- Remaining question: does Voxelion expose an internal face-verification
  API/SDK? If yes it must pass the same checklist before binding.
- Needs: vendor name → sandbox credentials → bind behind
  `VerificationProvider` (one-line swap).
- Provide: sandbox creds, API docs covering auth, accepted video formats
  (`webm`/`mp4` — confirm or specify transcode), photo limits, response
  schema, hard-fail verdicts, erasure/deletion API, rate limits, pricing.

## Needs your decision — proposals recorded in `PHASE_0_DECISIONS.md`

| # | Question | Proposal / current behaviour | Who signs off |
|---|---|---|---|
| 2 | Code "shown once" vs persistent-in-session | Code re-displays on `/result` refresh until browser session ends | Product |
| 3 | Session expiry → also delete PII? | Expiry → `withdrawn` + webhook + audit; PII kept for retention window | Legal/DPO (#4) |
| 4 | Webhook event contract | `declined`/`expired` sent as `withdrawn` + `reason`; terminal-only | OpinionPadi |
| 5 | Accessibility fallback policy | No-MediaRecorder → still-frame; no camera → `manual_review` | Product (ratify) |
| 6 | Intake fields vs spec §5.3 | 5 fields implemented — canonical list in PHASE_0_DECISIONS #2 | Product |
| 7 | Reward approval policy | Reviewer-led (decided) | Product (ratify) |
| 8 | Screenshot rules | Proposal: JPEG/PNG/WebP ≤5MB, volunteer uploads on OP side, dup → 409 | Product |
| 9 | Consent wording + NDPA lawful basis | Proposal: explicit consent; `v1.0-draft` wording | Legal |
| 10 | Retention periods | Proposal: media 30d · sessions 90d · codes 90d · audit 365d | DPO → set `RETENTION_*` |

## Credentials/accounts to provide when deploying

Production OpinionPadi public key · `OPINIONPADI_API_SECRET` ·
`OPINIONPADI_WEBHOOK_URL` + secret · `ADMIN_*` creds → staff SSO ·
Postgres · Redis · S3 private bucket · secrets manager · real
`OPINIONPADI_DASHBOARD_URL` · HTTPS/proxy config.
Full list: `EXTERNAL_CREDENTIALS_REQUIRED.md` (now written).

## Resolved since the last version of this doc

- `FACE_MATCHING_RESEARCH.md` written — NIST FRTE West-Africa evidence +
  provider shortlist (Smile ID / Dojah / Prembly watchlist).
- `EXTERNAL_CREDENTIALS_REQUIRED.md` written — was referenced but absent.
- Token/session rules incl. `iat` — fully enforced + tested.
- `manual_review`-without-evidence — failed photo now preserved
  (`accepted=false`).
- Misleading `failed` webhooks — terminal-only now.
- Silent session expiry — webhook + audit now fire.
- Retention prune over-deletion — scoping fixed; safe to enable once
  periods are approved.
