# Phase 0 — Decision Record

**Updated:** 2026-11-18. Maps 1:1 to Section 9 of the build plan
("Open decisions"). Status is one of:

- **DECIDED** — answer recorded and already implemented/tested.
- **PROPOSED** — concrete recommendation recorded; needs the named
  owner's sign-off to close. No further engineering input required.

Phase 0 is "complete" in the plan's sense when every row is DECIDED.
Today: 4 decided · 4 proposed-with-owner.

| # | Decision | Owner | Status |
|---|---|---|---|
| 1 | Verification provider | Eng lead | **PROPOSED** — Smile ID (fallback Dojah) |
| 2 | Intake field list (spec §5.3) | Product | **PROPOSED** — canonical list below |
| 3 | Consent wording + NDPA lawful basis | Legal | **PROPOSED** — explicit consent, wording drafted |
| 4 | Biometric controller + retention schedule | Legal/DPO | **PROPOSED** — roles + periods below |
| 5 | Auto vs reviewer-led reward approval | Product | **DECIDED** — reviewer-led |
| 6 | Token/session/TTL/retry limits | Eng | **DECIDED** — implemented + tested |
| 7 | Accessibility fallback for video challenge | Product/Eng | **DECIDED** — still-frame → manual review |
| 8 | Screenshot format/limits, duplicates | Product | **PROPOSED** — rules below |

---

## #1 — Face-verification provider  ·  PROPOSED

**Proposal:** procure **Smile ID**; hold **Dojah** as fallback. Full
evidence and procurement checklist: `FACE_MATCHING_RESEARCH.md`.

If Voxelion already exposes an internal face-verification API/SDK, it
must pass the same checklist (NIST FRTE West-Africa demographic data,
PAD-2 liveness, erasure API) before we bind to it — ask the Voxelion
platform team first. `VoxelionProvider` remains a stub until either
answer lands; the `VerificationProvider` interface makes the swap a
one-line binding change.

**Needs:** Eng lead confirms internal-API question → opens procurement.

## #2 — Intake field list (spec §5.3)  ·  PROPOSED

**Proposal:** the implemented set is the canonical list —

| Field | Type | Validation |
|---|---|---|
| `full_name` | string | required, ≥2 name parts, letters only (accents/hyphens/apostrophes ok), ≤120 |
| `email` | string | required, RFC + real TLD, MX/A/AAAA domain check, unique per volunteer, plus a 6-digit verification code |
| `phone_country_code` | enum | required, one of `+234 +1 +44 +254 +27` |
| `phone` | string | required, 6–20 chars, digits/spaces/dashes/parens, national-length check per country code |
| `preferred_contact` | enum | nullable, one of `email whatsapp phone` |

**Needs:** Product confirms this is the §5.3 list or supplies the delta.

## #3 — Consent wording + NDPA lawful basis  ·  PROPOSED

**Proposal:** lawful basis = **explicit consent** under the NDPA —
facial images used for verification are sensitive/biometric data, so no
lighter basis applies. Two affirmative checkboxes (processing +
biometrics) recorded with timestamps and a `disclosure_hash` of the
wording version, satisfying the consent-record requirement.

Draft wording lives in `resources/js/Pages/Consent.jsx`, versioned
`CONSENT_VERSION=v1.0-draft`. Bumping the version after Legal edits
invalidates nothing retroactively — old records keep their hash.

**Needs:** Legal approves wording + confirms lawful basis → set
`CONSENT_VERSION` to the approved tag.

## #4 — Biometric data controller + retention  ·  PROPOSED

**Proposal:**

- **Controller:** OpinionPadi — they determine purpose (task eligibility)
  and instruct collection. **Processor:** Voxelion, acting on documented
  instructions. Confirm this mirrors the inter-company agreement.
- **Retention (proposed periods for DPO ratification):**
  - Media (photo + capture): deleted on withdrawal/expiry immediately
    (implemented); on success, retain until reward decision + dispute
    window — propose **30 days**.
  - Session records: **90 days** after terminal state.
  - Completion codes: live for `COMPLETION_CODE_TTL=72h`, record kept
    **90 days** for audit joins.
  - Audit events (PII-free): **365 days**.
- Wiring exists: `RETENTION_SESSION_DAYS`, `RETENTION_CODE_DAYS`,
  `RETENTION_AUDIT_DAYS` env vars drive `voxelion:prune`; unset = nothing
  is deleted (fail-safe default).

**Needs:** DPO ratifies controller/processor roles + periods → set env.

## #5 — Automatic vs reviewer-led reward approval  ·  DECIDED

**Reviewer-led.** Every approval is backed by a `VERIFIED` code
validation against the backend; screenshot alone is never sufficient.
`ReviewController` + `Admin/ReviewQueue.jsx` implement queue, decision,
and audit. Auto-approve rules (e.g. score > X + no flags) can be layered
on later without changing the contract.

## #6 — Token / session / TTL / retry limits  ·  DECIDED

| Rule | Value | Where |
|---|---|---|
| Hand-off token | RS256 JWT, `exp ≤ 15 min`, `iat` not future, `iss` check, single-use `jti` | `HandoffTokenService`, `ValidateHandoffToken` |
| Key rotation | `kid`-less single-key for now; rotate by deploying new public key + OpinionPadi overlap window (old key accepted ≤15 min during cutover). Documented in `EXTERNAL_CREDENTIALS_REQUIRED.md` | — |
| Session expiry | `VERIFICATION_SESSION_TTL=60` min → auto `withdrawn` + webhook + audit | `EnforceSessionState` |
| Duplicate sessions | one session per `jti`; participant deduped on `(op_volunteer_ref, task_id)` | `VerificationSession`, `StartController` |
| Resume behaviour | server-side `verification_sessions.state`; reload resumes at the right step | `EnforceSessionState` |
| Photo retries | 3 → `manual_review` | `verification.photo.max_retries` |
| Liveness retries | 3 → `manual_review` | `verification.liveness.max_retries` |
| Completion code TTL | 72 h, single-use, hashed at rest | `COMPLETION_CODE_TTL`, `CompletionCodeService` |
| API auth | HMAC-SHA256 `"<ts>.<body>"`, 5-min replay window | `VerifyWebhookSignature` |

## #7 — Accessibility fallback for the video challenge  ·  DECIDED

Fallback chain (implemented in `LiveCheck.jsx` + `LivenessController`):

1. `getUserMedia`/`MediaRecorder` unavailable → **still-frame capture**
   (photo instead of video) offered on-page.
2. Camera denied/unavailable → retry prompt, then **manual_review** route
   rather than a hard failure — volunteers without capable devices still
   complete the journey.
3. Any unresolved capture failure after retries → `manual_review`, same
   as liveness exhaustion — no path strands a volunteer.

**Needs:** Product ratifies this is the *approved* fallback (vs.
document-upload or assisted verification — either is a larger build).

## #8 — Screenshot format/limits + duplicates  ·  PROPOSED

**Proposal:**

- Formats: JPEG/PNG/WebP, ≤ **5 MB**, submitted by the volunteer on
  OpinionPadi's proof-submission screen (they own that UI); we store the
  reference in `reward_submissions.screenshot_s3_key` and render it to
  reviewers via short-lived signed URLs only.
- Duplicates: one reward submission per completion code — second POST
  returns **409** (implemented + tested). Re-submission after a
  *rejected* review is allowed via a fresh submission row.

**Needs:** Product confirms format/size ceiling and who uploads
(volunteer vs reviewer paste-in).

---

## What "Phase 0 done" still needs from humans

1. Eng lead: answer the internal-API question → trigger Smile ID
   (or Dojah) procurement + sandbox creds.
2. Product: ratify #2 fields, #5 approval policy, #7 fallback, #8 rules.
3. Legal: approve consent wording + lawful basis (#3).
4. Legal/DPO: ratify controller/processor roles + retention periods (#4).

Nothing in the codebase is blocked on these answers — every proposal
above is already the implemented default.
