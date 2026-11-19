/**
 * React hook wrapping the face-geometry helpers: runs detection on a stream/frame and reports live status for guidance UI.
 */
import { useEffect, useRef, useState } from 'react';
import { classifyFace, ema, faceToOverlay, isSteady, NEUTRAL_OVAL } from '../lib/faceGeometry';
import { detectionToFace, getFaceDetector, pickPrimary } from '../lib/faceDetector';

const DETECT_INTERVAL_MS = 90; // ~11fps — enough for smooth guidance, cheap on CPU
const SMOOTH_ALPHA = 0.35;
const STEADY_WINDOW_MS = 800;

/**
 * Real-time face detection over the camera preview.
 *
 * Lazily loads MediaPipe's blaze-face detector (WASM + model vendored under
 * /public/mediapipe) once `enabled` is true, then analyses frames on a
 * throttled rAF loop. The video element is assumed to fill its container
 * with object-cover; the returned `overlay` is in container fractions.
 *
 * Returns:
 *   state        'idle' | 'loading' | 'ready' | 'unsupported'
 *   overlay      {x, y, w, h} container fractions + current neutral default
 *   tone         'neutral' | 'detected' | 'warn' | 'ok'
 *   guidanceText human instruction for the current frame
 *   ready        face detected, aligned and held still — capture may proceed
 */
export function useFaceDetection(videoRef, enabled) {
    const [state, setState] = useState('idle');
    const [guide, setGuide] = useState({
        overlay: NEUTRAL_OVAL,
        tone: 'neutral',
        guidanceText: 'Position your face inside the frame.',
        ready: false,
    });

    const smoothRef = useRef(null);
    const samplesRef = useRef([]);

    useEffect(() => {
        if (!enabled) return undefined;

        let cancelled = false;
        let raf = 0;
        let detector = null;
        let lastDetect = 0;
        smoothRef.current = null;
        samplesRef.current = [];
        setState('loading');

        const loop = () => {
            if (cancelled) return;
            raf = requestAnimationFrame(loop);

            const video = videoRef.current;
            if (!video || video.readyState < 2) return;

            const now = performance.now();
            if (now - lastDetect < DETECT_INTERVAL_MS) return;
            lastDetect = now;

            let result;
            try {
                result = detector.detectForVideo(video, now);
            } catch {
                return; // transient frame error — try the next tick
            }

            const det = pickPrimary(result?.detections);
            const rect = video.getBoundingClientRect();

            if (!det) {
                smoothRef.current = null;
                samplesRef.current = [];
                setGuide({
                    overlay: NEUTRAL_OVAL,
                    tone: 'neutral',
                    guidanceText: 'Position your face inside the frame.',
                    ready: false,
                });
                return;
            }

            const raw = detectionToFace(det, video.videoWidth, video.videoHeight);
            const face = (smoothRef.current = ema(smoothRef.current, raw, SMOOTH_ALPHA));

            // Track recent centres for stillness — cap the window.
            samplesRef.current.push({ t: now, cx: face.cx, cy: face.cy });
            while (samplesRef.current.length && now - samplesRef.current[0].t > STEADY_WINDOW_MS) {
                samplesRef.current.shift();
            }

            const guidance = classifyFace(face);
            const steady = isSteady(samplesRef.current, now);
            const tone = guidance === 'ok' ? (steady ? 'ok' : 'detected') : guidance === 'none' ? 'neutral' : 'warn';
            const overlay = faceToOverlay(face, video.videoWidth, video.videoHeight, rect.width, rect.height) ?? NEUTRAL_OVAL;

            setGuide({
                overlay,
                tone,
                guidanceText: GUIDANCE_LABEL(guidance),
                ready: guidance === 'ok' && steady,
            });
        };

        (async () => {
            try {
                const created = await getFaceDetector('VIDEO');
                if (cancelled) return;
                detector = created;
                setState('ready');
                loop();
            } catch {
                if (!cancelled) setState('unsupported');
            }
        })();

        return () => {
            cancelled = true;
            cancelAnimationFrame(raf);
            // The detector is a shared singleton — it stays alive for reuse
            // (e.g. retake / uploaded-photo checks); only the loop stops.
        };
    }, [enabled, videoRef]);

    return { state, ...guide };
}

function GUIDANCE_LABEL(guidance) {
    return {
        none: 'Position your face inside the frame.',
        too_far: 'Move closer.',
        too_close: 'Move slightly back.',
        off_center_x: 'Centre your face.',
        off_center_y: 'Adjust your position.',
        tilted: 'Keep your head straight.',
        ok: 'Perfect! Hold still.',
    }[guidance];
}
