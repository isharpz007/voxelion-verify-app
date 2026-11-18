/**
 * Pure geometry for face-guided capture — no DOM or detector imports, so it
 * is unit-testable in isolation.
 *
 * All face metrics are normalised to the video frame (0–1):
 *   cx, cy — face-centre as a fraction of frame width/height
 *   w      — face bounding-box width as a fraction of frame width
 *   tiltDeg — head roll in degrees (0 = level eyes)
 */

export const GUIDANCE_TEXT = {
    none: 'Position your face inside the frame.',
    too_far: 'Move closer.',
    too_close: 'Move slightly back.',
    off_center_x: 'Centre your face.',
    off_center_y: 'Adjust your position.',
    tilted: 'Keep your head straight.',
    ok: 'Perfect! Hold still.',
};

// Oval border tone per guidance state.
export const GUIDANCE_TONE = {
    none: 'neutral',      // white — no face yet
    loading: 'detected',  // blue — face found, evaluating
    warn: 'warn',         // orange — face present but mis-positioned
    ok: 'ok',             // green — aligned and steady
};

const MIN_FACE_W = 0.26;  // below → "Move closer"
const MAX_FACE_W = 0.62;  // above → "Move slightly back"
const MAX_CX_OFFSET = 0.08;
const MAX_CY_OFFSET = 0.10;
const CENTER_Y = 0.5;
const MAX_TILT_DEG = 12;

/** Classify a detected face into a guidance state. */
export function classifyFace(face) {
    if (!face) return 'none';
    if (face.w < MIN_FACE_W) return 'too_far';
    if (face.w > MAX_FACE_W) return 'too_close';
    if (Math.abs(face.cx - 0.5) > MAX_CX_OFFSET) return 'off_center_x';
    if (Math.abs(face.cy - CENTER_Y) > MAX_CY_OFFSET) return 'off_center_y';
    if (Math.abs(face.tiltDeg) > MAX_TILT_DEG) return 'tilted';
    return 'ok';
}

/** Exponential moving average — smooths detector jitter. alpha ≈ 0.25–0.4. */
export function ema(prev, next, alpha = 0.35) {
    if (prev == null) return next;
    return {
        cx: prev.cx + (next.cx - prev.cx) * alpha,
        cy: prev.cy + (next.cy - prev.cy) * alpha,
        w: prev.w + (next.w - prev.w) * alpha,
        tiltDeg: prev.tiltDeg + (next.tiltDeg - prev.tiltDeg) * alpha,
    };
}

const STEADY_SPREAD = 0.02;
const STEADY_MIN_MS = 600;

/**
 * Is the face holding still? `samples` = recent [{t, cx, cy}] — steady when
 * they span ≥600ms with centre spread below threshold.
 */
export function isSteady(samples, now) {
    if (samples.length < 2) return false;
    const span = now - samples[0].t;
    if (span < STEADY_MIN_MS) return false;
    let minX = 1, maxX = 0, minY = 1, maxY = 0;
    for (const s of samples) {
        minX = Math.min(minX, s.cx); maxX = Math.max(maxX, s.cx);
        minY = Math.min(minY, s.cy); maxY = Math.max(maxY, s.cy);
    }
    return maxX - minX < STEADY_SPREAD && maxY - minY < STEADY_SPREAD;
}

/**
 * Map normalised face coords to overlay fractions, accounting for the
 * `object-cover` crop of the video inside its container.
 *   videoW/videoH — source frame pixels (video.videoWidth/videoHeight)
 *   viewW/viewH   — rendered element size (getBoundingClientRect)
 * Returns {x, y, w, h} in 0–1 fractions of the *container*, or null when
 * the oval would be entirely outside the visible crop.
 */
export function faceToOverlay(face, videoW, videoH, viewW, viewH) {
    if (!videoW || !videoH || !viewW || !viewH) return null;

    const scale = Math.max(viewW / videoW, viewH / videoH);
    const dispW = videoW * scale;
    const dispH = videoH * scale;
    const offX = (dispW - viewW) / 2;
    const offY = (dispH - viewH) / 2;

    // Oval around the head — wider/taller than the tight face box.
    const ovalW = (face.w * 1.5 * dispW) / viewW;
    const ovalH = (face.w * 1.95 * dispH) / viewH;
    const x = (face.cx * dispW - offX) / viewW;
    const y = (face.cy * dispH - offY) / viewH;

    return { x, y, w: ovalW, h: ovalH };
}

/** Neutral oval — centred placeholder shown when no face is tracked. */
export const NEUTRAL_OVAL = { x: 0.5, y: 0.5, w: 0.42, h: 0.55 };

/* ---------- Uploaded-photo validation ---------- */

export const PHOTO_VERDICT_TEXT = {
    no_face: 'No face detected. Please upload another photo.',
    multi_face: 'Please upload a photo containing only one face.',
    too_small: 'Your face is too far away. Upload a closer photo.',
    cropped: 'Ensure your entire face is visible.',
    tilted: 'Please upload a photo with your head straight.',
    dark: 'Please upload a clearer photo with better lighting.',
    ok: 'Your photo looks good.',
};

const UPLOAD_MIN_FACE_W = 0.22;    // uploads get a slightly looser distance bound
const CROP_EDGE_MARGIN = 0.015;    // box within 1.5% of a frame edge → cropped
const UPLOAD_MAX_TILT_DEG = 15;    // stills tolerate a bit more roll than live capture
const DARK_LUMA = 45;              // mean luminance (0–255) below → "better lighting"

/**
 * Validate a still photo's detections. `faces` = normalised face objects
 * (from detectionToFace), `luma` = mean image luminance 0–255.
 * Returns { verdict, text, tone } — tone 'warn' (orange/red) or 'ok' (green).
 */
export function validatePhoto(faces, luma) {
    if (!faces.length) return verdict('no_face');
    if (faces.length > 1) return verdict('multi_face');

    const face = faces[0];
    if (face.w < UPLOAD_MIN_FACE_W) return verdict('too_small');
    if (
        face.left < CROP_EDGE_MARGIN || face.top < CROP_EDGE_MARGIN ||
        face.right > 1 - CROP_EDGE_MARGIN || face.bottom > 1 - CROP_EDGE_MARGIN
    ) return verdict('cropped');
    if (Math.abs(face.tiltDeg) > UPLOAD_MAX_TILT_DEG) return verdict('tilted');
    if (luma != null && luma < DARK_LUMA) return verdict('dark');
    return verdict('ok');
}

function verdict(v) {
    return { verdict: v, text: PHOTO_VERDICT_TEXT[v], tone: v === 'ok' ? 'ok' : 'warn' };
}
