/**
 * getUserMedia camera wrapper: live preview, capture-to-blob, and permission/error states shared by the photo and liveness steps.
 */
import { useEffect, useRef, useState } from 'react';
import Icon from './Icon';

/**
 * getUserMedia wrapper used by the photo and live-check screens.
 * Exposes { videoRef, state, error, snapshot(), start(), stop() }.
 * state: idle | requesting | active | denied | unavailable
 */
export function useCamera() {
    const videoRef = useRef(null);
    const streamRef = useRef(null);
    const [state, setState] = useState('idle');

    const start = async () => {
        if (!navigator.mediaDevices?.getUserMedia) {
            setState('unavailable');
            return;
        }
        setState('requesting');
        try {
            const stream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: 'user', width: { ideal: 1280 }, height: { ideal: 720 } },
                audio: false,
            });
            streamRef.current = stream;
            if (videoRef.current) {
                videoRef.current.srcObject = stream;
                await videoRef.current.play();
            }
            setState('active');
        } catch (err) {
            setState(err?.name === 'NotAllowedError' ? 'denied' : 'unavailable');
        }
    };

    const stop = () => {
        streamRef.current?.getTracks().forEach((t) => t.stop());
        streamRef.current = null;
    };

    /** Capture the current frame as a JPEG File. */
    const snapshot = (filename = 'capture.jpg') => {
        const video = videoRef.current;
        if (!video) return null;
        const canvas = document.createElement('canvas');
        canvas.width = video.videoWidth || 640;
        canvas.height = video.videoHeight || 480;
        canvas.getContext('2d').drawImage(video, 0, 0);
        return new Promise((resolve) =>
            canvas.toBlob(
                (blob) => resolve(blob ? new File([blob], filename, { type: 'image/jpeg' }) : null),
                'image/jpeg',
                0.9
            )
        );
    };

    /**
     * Record a short video clip (for the liveness challenge).
     * Falls back to a still frame on browsers without MediaRecorder support
     * (e.g. old Safari) — the backend accepts image captures too.
     * Resolves null when no capture could be produced at all.
     */
    const recordClip = (ms = 3000) =>
        new Promise((resolve) => {
            const stream = streamRef.current;
            if (!stream) return resolve(null);

            const stillFallback = () =>
                Promise.resolve(snapshot('liveness.jpg')).then(resolve).catch(() => resolve(null));

            if (typeof MediaRecorder === 'undefined') {
                return stillFallback();
            }

            const mime = ['video/webm', 'video/mp4'].find((m) => MediaRecorder.isTypeSupported?.(m));
            if (!mime) return stillFallback();

            try {
                const rec = new MediaRecorder(stream, { mimeType: mime });
                const chunks = [];
                let settled = false;
                const finish = (fn) => {
                    if (!settled) {
                        settled = true;
                        fn();
                    }
                };
                rec.ondataavailable = (e) => e.data.size && chunks.push(e.data);
                rec.onerror = () => finish(stillFallback);
                rec.onstop = () =>
                    finish(() =>
                        resolve(
                            chunks.length
                                ? new File(chunks, `liveness.${mime === 'video/webm' ? 'webm' : 'mp4'}`, { type: mime })
                                : null
                        )
                    );
                rec.start();
                setTimeout(() => {
                    try {
                        rec.stop();
                    } catch {
                        finish(stillFallback);
                    }
                }, ms);
            } catch {
                stillFallback();
            }
        });

    useEffect(() => () => stop(), []);

    return { videoRef, state, start, stop, snapshot, recordClip };
}

const OVAL_TONE_CLASS = {
    neutral: 'border-dashed border-on-tertiary/70',
    detected: 'border-solid border-sky-300',
    warn: 'border-solid border-amber-400',
    ok: 'border-solid border-emerald-400',
};

/**
 * Face oval — tracks the detected face. `overlay` = {x, y, w, h} in
 * container fractions; `tone` picks the border colour. Transitions smooth
 * residual jitter left over from the detector-side smoothing.
 */
export function FaceOval({ overlay, tone }) {
    return (
        <div
            className={`absolute pointer-events-none rounded-[50%] border-2 transition-all duration-150 ease-out ${OVAL_TONE_CLASS[tone] ?? OVAL_TONE_CLASS.neutral}`}
            style={{
                left: `${(overlay.x - overlay.w / 2) * 100}%`,
                top: `${(overlay.y - overlay.h / 2) * 100}%`,
                width: `${overlay.w * 100}%`,
                height: `${overlay.h * 100}%`,
            }}
        />
    );
}

export function CameraFrame({ videoRef, state, overlay, tone = 'neutral', children }) {
    return (
        <div className="relative w-full aspect-[3/4] max-h-[420px] rounded-xl overflow-hidden bg-tertiary">
            <video
                ref={videoRef}
                playsInline
                muted
                className={`absolute inset-0 w-full h-full object-cover ${state === 'active' ? '' : 'hidden'}`}
            />
            {state !== 'active' && (
                <div className="absolute inset-0 flex flex-col items-center justify-center gap-2 text-on-tertiary">
                    <Icon name="videocam_off" className="text-[40px] opacity-60" />
                    <span className="font-body-sm text-body-sm opacity-70">
                        {state === 'requesting' ? 'Requesting camera access…' : 'Camera preview'}
                    </span>
                </div>
            )}
            {/* Face oval guide — follows the detected face; neutral centred
                placeholder when nothing is tracked */}
            {state === 'active' &&
                (overlay ? (
                    <FaceOval overlay={overlay} tone={tone} />
                ) : (
                    <div className="absolute inset-0 flex items-center justify-center pointer-events-none">
                        <div className="w-44 h-56 rounded-[50%] border-2 border-dashed border-on-tertiary/70" />
                    </div>
                ))}
            {children}
        </div>
    );
}

export default function CameraCapture() {
    return null; // Hook + frame exported above; page composes them.
}
