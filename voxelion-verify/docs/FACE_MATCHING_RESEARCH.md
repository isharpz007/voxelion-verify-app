# Face Matching Research — NIST FRTE evidence & provider shortlist

**Status:** Complete — feeds Phase 0 decision #1 (verification provider).
**Audience:** Eng lead + whoever owns vendor procurement.

## 1. Why this document exists

OpinionPadi's volunteers are predominantly West African (Nigerian phone
codes are the primary intake path). NIST's Face Recognition Technology
Evaluation (FRTE) shows that **aggregate accuracy is the wrong selection
criterion for this population** — demographic differentials in false-match
rates are the largest source of vendor variance that matters to us.

## 2. What NIST FRTE actually shows (1:1 verification)

Sources: NIST FRTE 1:1 demographic differentials table
(`pages.nist.gov/frvt/html/frvt11.html`), NIST IR 8280 (2019) and
NIST IR 8429 (2022) on demographic effects.

- **FMR (false positives — wrong person accepted) carries the demographic
  bias.** In the current FRTE 1:1 summary, the best regional group
  (Eastern Europe) shows an FMR-max around 0.14%, while the worst group —
  **West Africa — reaches ~30.7%**. Women, and subjects over 65, are the
  worst sub-groups; across the top-ten algorithms the average West-African
  FMR is roughly **23× the Eastern-European rate**, and for the worst
  subgroup (West African women 65+) ~358× the best subgroup.
- **FNMR (false negatives — right person rejected) is mostly image-quality
  driven**, not anatomy-driven. NIST explicitly attributes elevated FNMR
  for darker skin tones to *under-exposure and poor lighting*, and to
  camera geometry issues. This is directly actionable by us, not just the
  vendor.
- Algorithms displaced similarity-score distributions for
  under-represented demographics — i.e., vendors trained mostly on
  non-African faces are the ones producing the West-Africa outliers.

**Consequence for us:** pick a vendor whose FRTE 1:1 submission shows a
flat West-Africa FMR/FNMR profile — or one trained specifically on
African faces — and keep our own quality gate strict, because good
capture conditions collapse most of the FNMR differential.

## 3. What we already do that mitigates the FNMR side

| NIST finding | Our mitigation |
|---|---|
| Under-exposed dark skin → FNMR | `PhotoQualityService` lighting check rejects dim captures before the provider sees them |
| Low-res/blurry frames → FNMR | `min_width/height` (480px) + provider blur check; per-check "retake" messaging |
| Weak attempts exhaust users | 3 retries each for photo and liveness, then `manual_review` — never a silent hard-fail |
| Demographic skew must be measured, not assumed | Phase 5 bias check: pass/fail rates reported across a representative sample (see `build_plan.txt` §7) |

The FMR side is entirely the vendor's problem — hence the selection
criteria below.

## 4. Vendor shortlist

### Primary candidate — Smile ID (Smile Identity)

- The only shortlisted vendor combining **biometric liveness + face
  match with Nigerian government-database coverage** (9 ID authorities,
  132M+ records — NIN, BVN, driver's licence, passport, voter's card).
- Publicly claims **de-bian't accessible right nowsed models at 99.8% accuracy across African
  skin tones** — built on African training data rather than retrofitted.
- Capture SDKs tuned for our actual conditions: low bandwidth, Android
  5.0+, older cameras. Volunteers are mostly on phones.
- ISO 27001 + SOC 2 Type 1 & 2. Nigeria-dedicated product surface
  (`smile.id/countries/nigeria`).
- **Verify in procurement:** FRTE 1:1 submission or third-party bias
  evaluation; liveness PAD certification (ISO 30107-3 / iBeta Level 2);
  erasure/deletion API for NDPA withdrawal handling; accepted video
  formats (`webm`/`mp4` or required transcode); sandbox SLA.

### Alternate — Dojah

- Nigerian anti-fraud platform; selfie/short-video liveness + face match
  against ID document photo.
- Only vendor on the list with **public pricing** ($0.04–$0.06/call) —
  usable for a cost model before any sales conversation.
- NDPR-aligned positioning; PHP/JS SDKs available.
- **Verify in procurement:** same checklist as Smile ID, plus
  published demographic-accuracy evidence (their 98% figure is
  marketing copy, not a NIST number).

### Watchlist — Prembly / Identitypass

- Nigerian vendor; documented `POST /api/v1/verification/api/liveliness_check`
  returning a liveness confidence score; unique NYSC-certificate check.
- Face-match-against-selfie journey is thinner in public docs than the
  two above — keep as third option only if primary/alternate stall.

### Not shortlisted (for the record)

Global vendors — Onfido, Veriff, iProov, Sumsub — some have strong
aggregate FRTE submissions, but none publish West-Africa demographic
breakdowns in a way we could verify, none cover Nigerian government ID
sources, and none are built around low-bandwidth African handset
conditions. Revisit only if both shortlisted vendors fail procurement.

## 5. Open question that gates this

**"Does Voxelion (the platform) already expose a face-verification
API/SDK?"** — Phase 0 item #1. If yes, it must still pass the checklist
below before we bind to it; `VoxelionProvider` is currently a
deterministic stub behind `VerificationProvider`. If no internal API
exists, proceed to procure Smile ID (fallback: Dojah).

## 6. Procurement checklist (applies to whichever vendor wins)

| Requirement | Why |
|---|---|
| FRTE 1:1 demographic breakdown (West-Africa FMR/FNMR) or equivalent third-party bias audit | §2 — the whole point of this document |
| Liveness PAD-2 certification (ISO 30107-3 / iBeta L2) | Spoof resistance on printed-photo + screen-replay attacks |
| Erasure/deletion API | NDPA withdrawal → `ProcessDeletionRequest` must propagate |
| Accepted media formats (webm/mp4 or transcode requirement) | LiveCheck capture format is browser-driven |
| Server-to-server API + signed webhooks/callbacks | Fits the `(provider callbacks)` lane in the architecture |
| Rate limits + pricing per call | Load + cost model for Phase 5/6 |
| DPA under NDPA; data-residency statement | Legal/DPO sign-off (Phase 0 #3/#4) |

## 7. Recommendation

**Procure Smile ID as the verification provider; hold Dojah as the
negotiated fallback.** Both are West-Africa-native, which is the only
defensible position given the NIST demographic data. Keep both behind
the `VerificationProvider` interface so the binding stays swappable —
`AppServiceProvider` is the single seam.

*This recommendation satisfies the "provider chosen or build-vs-buy
decided" exit criterion once the contract/sandbox is confirmed. It does
not remove the legal/DPO sign-offs in `PHASE_0_DECISIONS.md`.*
