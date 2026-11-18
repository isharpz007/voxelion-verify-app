/**
 * Shared MediaPipe face-detection services — one implementation used by both
 * the live camera loop and the uploaded-photo check. The detector instances
 * are cached singletons (WASM + model loaded once per page session).
 */

let filesetPromise = null;
const detectorPromises = {};

function getFileset() {
    if (!filesetPromise) {
        filesetPromise = import('@mediapipe/tasks-vision').then(({ FilesetResolver }) =>
            FilesetResolver.forVisionTasks('/mediapipe'),
        );
    }
    return filesetPromise;
}

/**
 * Get (or lazily create) a FaceDetector for the given running mode.
 * 'VIDEO' for the camera loop (detectForVideo), 'IMAGE' for single photos.
 */
export function getFaceDetector(runningMode) {
    if (!detectorPromises[runningMode]) {
        detectorPromises[runningMode] = Promise.all([
            getFileset(),
            import('@mediapipe/tasks-vision'),
        ]).then(([fileset, { FaceDetector }]) =>
            FaceDetector.createFromOptions(fileset, {
                baseOptions: { modelAssetPath: '/mediapipe/blaze_face_short_range.tflite' },
                runningMode,
                minDetectionConfidence: 0.5,
                minSuppressionThreshold: 0.3,
            }),
        );
    }
    return detectorPromises[runningMode];
}

/** Largest detection wins when several faces are in frame. */
export function pickPrimary(detections) {
    if (!detections?.length) return null;
    let best = detections[0];
    for (const d of detections) {
        if (d.boundingBox.width * d.boundingBox.height > best.boundingBox.width * best.boundingBox.height) {
            best = d;
        }
    }
    return best;
}

/**
 * Normalise a MediaPipe detection into our face metrics (0–1 fractions of
 * the source frame): centre, size, head tilt and box edges.
 */
export function detectionToFace(det, imgW, imgH) {
    const box = det.boundingBox;
    return {
        cx: (box.originX + box.width / 2) / imgW,
        cy: (box.originY + box.height / 2) / imgH,
        w: box.width / imgW,
        h: box.height / imgH,
        left: box.originX / imgW,
        top: box.originY / imgH,
        right: (box.originX + box.width) / imgW,
        bottom: (box.originY + box.height) / imgH,
        tiltDeg: headTiltDeg(det.keypoints),
    };
}

/**
 * Head roll from the eye keypoints — MediaPipe face detection keypoints
 * 0 and 1 are the right and left eye (normalised to the image). Upright
 * face ≈ 0°.
 */
export function headTiltDeg(keypoints) {
    if (!keypoints || keypoints.length < 2) return 0;
    const [right, left] = keypoints;
    return (Math.atan2(left.y - right.y, left.x - right.x) * 180) / Math.PI;
}
