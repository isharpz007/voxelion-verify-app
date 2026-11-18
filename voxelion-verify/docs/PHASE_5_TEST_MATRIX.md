# Phase 5 — Hardening, Privacy & Testing Matrix

Maps every Phase 5 requirement to its automated evidence (or flags it as a
human/infra item). Run order reference: `php artisan test` (100 tests).

## Security testing

| Requirement | Evidence | Status |
|---|---|---|
| Token forgery | `HandoffTokenTest`: invalid signature, wrong issuer, missing claims | ✅ |
| Replayed `jti` | `test_replayed_jti_rejected` — one-time `jti` enforced | ✅ |
| Out-of-order state calls | `JourneyStateTest` — every route bounces to the correct step via `EnforceSessionState` | ✅ |
| Code guessing / enumeration | `test_completion_code_enumeration_is_rate_limited` — 429 after 60 req/min; codes are HMAC-hashed at rest | ✅ |
| Bucket access checks | `PhotoUploadTest` — `storage_key` must match the participant's own prefix; unsigned media URLs rejected | ✅ |
| Signed-URL TTL | expired signed media URLs rejected; presigned upload URLs are 10-minute TTL | ✅ |
| Provider outage/timeout | `test_provider_timeout_is_retriable_and_burns_no_attempt`, `test_provider_outage_in_photo_check_burns_no_attempt` — exception degrades to retriable error, no attempt consumed, `provider.error` audited | ✅ (new) |

## Privacy testing

| Requirement | Evidence | Status |
|---|---|---|
| Withdrawal mid-journey → processing stops | `JourneyStateTest` — terminal `Withdrawn` blocks all journey routes | ✅ |
| Media deleted from bucket | `JourneyFlowTest::withdrawal_scrubs_pii_and_deletes_media` | ✅ |
| Records removed/anonymized | same test — PII scrubbed, record retained only as anonymized shell | ✅ |
| Processor deletion confirmations | `deletion_requests.processor_confirmations_json` written by `ProcessDeletionRequest` | ✅ |
| Consent gating cannot be bypassed | `JourneyStateTest` — `/photo` refuses before consent even with a crafted request | ✅ |

## Failure-matrix testing

| Scenario | Evidence |
|---|---|
| Declined consent | `ConsentFlowTest` — terminal `Declined` |
| Invalid intake | `IntakeFlowTest` — field validation, duplicate email rejected |
| Every photo quality failure | `PhotoQualityTest` — face/blur/lighting/format/size each produces specific feedback |
| Camera denied / unavailable | `LiveCheck.jsx` fallback screens + `still_frame_fallback_goes_straight_to_manual_review` |
| Liveness fail | `LivenessFailureTest` — retriable failure stays in `live_check`, no webhook |
| Match fail | `LivenessFailureTest` — similarity below threshold → retriable |
| Retry-limit exhaustion | `exhausted_liveness_sends_manual_review_webhook_once`; photo 3x → `manual_review` |
| Network drop mid-upload | `test_invalid_upload_leaves_no_residue` — rejected upload leaves no file/row/attempt |
| Network drop mid-video | session stays in `live_check`; reload resumes at the same step (state is server-side) |
| Duplicate session | `JourneyFlowTest` — one `verification_session_id` per HTTP session |
| Expired link | `HandoffTokenTest` — `exp` rejected; `test_expired_session_notifies_opinionpadi_and_audits` |
| Concurrent sessions | `test_concurrent_sessions_do_not_bleed` — two volunteers can't cross states | ✅ (new) |

## Human / infrastructure items (not automatable in CI)

| Requirement | What's needed |
|---|---|
| Load testing at scale | k6/locust against a staging deploy — concurrent sessions, large uploads, provider latency injection |
| Mobile device matrix | real-device runs (Android Chrome / iOS Safari camera quirks, low-bandwidth) |
| Bias check | requires production provider + demographic sampling decision — pass/fail rates by cohort per `FACE_MATCHING_RESEARCH.md`; no demographic fields are collected today (privacy trade-off to ratify) |
| Real S3 bucket checks | presigned PUT end-to-end against a live bucket |

## Hardening changes landed in this phase

- `LivenessController::complete` — provider exceptions caught → retriable error, **no attempt consumed**, `provider.error` audited, temp file cleaned in `finally`.
- `RunQualityChecks` — provider exceptions degrade to a failed `provider` check instead of a crashed job.
- `PhotoController::store` — provider-only failures free the attempt (asset deleted, error surfaced), so an outage can't push a volunteer to `manual_review`.
