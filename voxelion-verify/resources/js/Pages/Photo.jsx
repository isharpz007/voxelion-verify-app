import { router, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import Icon from '../Components/Icon';
import JourneyLayout from '../Components/JourneyLayout';
import QualityFeedback from '../Components/QualityFeedback';
import { useCamera, CameraFrame, FaceOval } from '../Components/CameraCapture';
import { useFaceDetection } from '../Components/useFaceDetection';
import { analyzePhotoFile } from '../lib/photoFaceCheck';
import { faceToOverlay } from '../lib/faceGeometry';

const TIPS = [
    { icon: 'face', text: 'Position your face in the centre of the frame' },
    { icon: 'wb_sunny', text: 'Ensure surroundings are brightly and evenly lit' },
    { icon: 'visibility', text: 'Remove tinted glasses, hats, or masks' },
];

export default function Photo({ task, attemptsUsed, maxAttempts }) {
    const { errors } = usePage().props;
    const camera = useCamera();
    const fileInput = useRef(null);
    const [preview, setPreview] = useState(null);
    const [file, setFile] = useState(null);
    const [uploading, setUploading] = useState(false);
    const [capturing, setCapturing] = useState(false);
    // Upload check: null | 'analyzing' | analyzePhotoFile result
    const [check, setCheck] = useState(null);
    const analyzeSeq = useRef(0);
    const attemptsLeft = maxAttempts - attemptsUsed;

    // Live face tracking — drives the oval, guidance text and capture gating.
    const detection = useFaceDetection(camera.videoRef, camera.state === 'active' && !preview);
    // When the detector can't run (no WASM/older browser) fall back to a
    // manual capture with the static oval rather than blocking the journey.
    const canCapture = detection.state !== 'ready' || detection.ready;

    const chooseFile = (picked) => {
        if (!picked) return;
        setFile(picked);
        setPreview(URL.createObjectURL(picked));

        // Same detector as the live camera — analyse the image in-browser.
        const seq = ++analyzeSeq.current;
        setCheck({ state: 'analyzing' });
        analyzePhotoFile(picked)
            .then((res) => { if (seq === analyzeSeq.current) setCheck(res); })
            .catch(() => { if (seq === analyzeSeq.current) setCheck({ state: 'unreadable' }); });
    };

    const capture = async () => {
        if (capturing || !canCapture) return; // gated on face alignment
        setCapturing(true);
        try {
            const snap = await camera.snapshot('photo.jpg');
            if (snap) {
                camera.stop();
                chooseFile(snap);
            }
        } finally {
            setCapturing(false);
        }
    };

    const retake = () => {
        analyzeSeq.current += 1; // discard any in-flight analysis
        setCheck(null);
        setPreview(null);
        setFile(null);
        camera.start();
    };

    const upload = () => {
        if (!file) return;
        setUploading(true);
        router.post('/photo', { photo: file }, {
            forceFormData: true,
            onFinish: () => setUploading(false),
        });
    };

    return (
        <JourneyLayout step={3} taskRef={task.reference} showBack>
            <div className="space-y-space-xs">
                <div className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-surface-container-high text-on-surface-variant font-label-code text-label-code">
                    <Icon name="add_a_photo" className="text-[14px] text-tertiary-container" />
                    <span>Biometric Capture</span>
                </div>
                <h1 className="font-headline-lg-mobile text-headline-lg-mobile text-primary tracking-tight">Let's take your photo</h1>
            </div>

            <QualityFeedback error={errors.photo} />

            {attemptsLeft <= 1 && (
                <p className="font-body-sm text-body-sm text-error">This is your last attempt — {attemptsLeft} remaining.</p>
            )}

            {/* Tips card */}
            <div className="w-full bg-surface-container-lowest rounded-xl p-space-md shadow-sm space-y-2">
                <h2 className="font-label-code text-label-code uppercase tracking-wider text-secondary">For a fast approval:</h2>
                {TIPS.map((t) => (
                    <div key={t.icon} className="flex items-center gap-3">
                        <Icon name={t.icon} className="text-[18px] text-on-tertiary-container" />
                        <span className="font-body-sm text-body-sm text-on-surface-variant">{t.text}</span>
                    </div>
                ))}
            </div>

            {preview ? (
                <div className="space-y-space-md">
                    <UploadPreview src={preview} check={check} />

                    {/* Verdict message */}
                    {check && check.state !== 'analyzing' && check.state !== 'unsupported' && (
                        <p
                            className={`font-body-sm text-body-sm ${
                                check.tone === 'ok' ? 'text-emerald-700' : 'text-error'
                            }`}
                        >
                            {check.state === 'unreadable'
                                ? 'We couldn’t read that image — try a JPEG, PNG or WebP.'
                                : check.text}
                        </p>
                    )}

                    <div className="flex gap-2">
                        <button
                            onClick={retake}
                            type="button"
                            className="flex-1 h-12 rounded-xl bg-surface-container-high text-on-surface font-title-md text-title-md flex items-center justify-center gap-2"
                        >
                            <Icon name="flip_camera_ios" className="text-[18px]" /> Retake
                        </button>
                        <button
                            onClick={upload}
                            disabled={uploading || (check && !['ok', 'unsupported'].includes(check.state))}
                            type="button"
                            className="flex-1 h-12 rounded-xl bg-primary-container text-on-primary font-title-md text-title-md flex items-center justify-center gap-2 shadow-md disabled:opacity-60"
                        >
                            <Icon name="cloud_upload" className="text-[18px]" />
                            {uploading ? 'Uploading…' : 'Upload Photo'}
                        </button>
                    </div>
                </div>
            ) : (
                <div className="space-y-space-md">
                    <CameraFrame
                        videoRef={camera.videoRef}
                        state={camera.state}
                        overlay={detection.state === 'ready' ? detection.overlay : null}
                        tone={detection.tone}
                    >
                        {camera.state === 'active' && (
                            <div className="absolute bottom-3 left-1/2 -translate-x-1/2 flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-tertiary/60 text-on-tertiary font-label-code text-label-code">
                                <Icon name="filter_center_focus" className="text-[14px]" />
                                <span>
                                    {detection.state === 'ready'
                                        ? detection.guidanceText
                                        : detection.state === 'loading'
                                          ? 'Loading face detection…'
                                          : 'Position your face inside the oval'}
                                </span>
                            </div>
                        )}
                    </CameraFrame>

                    {camera.state === 'denied' && (
                        <div className="w-full bg-error-container rounded-xl p-space-md text-center space-y-1">
                            <h3 className="font-title-md text-title-md text-on-error-container">Camera access was denied</h3>
                            <p className="font-body-sm text-body-sm text-on-error-container/90">
                                Allow camera permission in your browser settings, or upload a photo instead.
                            </p>
                        </div>
                    )}
                    {camera.state === 'unavailable' && (
                        <div className="w-full bg-surface-container-high rounded-xl p-space-md text-center space-y-1">
                            <h3 className="font-title-md text-title-md text-on-surface">Camera unavailable</h3>
                            <p className="font-body-sm text-body-sm text-on-surface-variant">
                                No usable camera was detected. You can upload a photo instead.
                            </p>
                        </div>
                    )}

                    <div className="flex gap-2">
                        {camera.state === 'active' ? (
                            <button
                                onClick={capture}
                                disabled={!canCapture || capturing}
                                type="button"
                                title={canCapture ? undefined : 'Centre your face and hold still'}
                                className="flex-1 h-[52px] bg-primary-container text-on-primary rounded-xl font-title-md text-title-md flex items-center justify-center gap-2 shadow-md disabled:opacity-60"
                            >
                                <Icon name="photo_camera" className="text-[20px]" />
                                {capturing ? 'Capturing…' : 'Capture'}
                            </button>
                        ) : (
                            <button
                                onClick={camera.start}
                                disabled={camera.state === 'requesting'}
                                type="button"
                                className="flex-1 h-[52px] bg-primary-container text-on-primary rounded-xl font-title-md text-title-md flex items-center justify-center gap-2 shadow-md disabled:opacity-60"
                            >
                                <Icon name="videocam" className="text-[20px]" />
                                {camera.state === 'requesting' ? 'Requesting…' : 'Enable Camera'}
                            </button>
                        )}
                        <button
                            onClick={() => fileInput.current?.click()}
                            type="button"
                            className="flex-1 h-[52px] rounded-xl bg-surface-container-high text-on-surface font-title-md text-title-md flex items-center justify-center gap-2"
                        >
                            <Icon name="upload" className="text-[20px]" /> Upload File
                        </button>
                    </div>
                    <input
                        ref={fileInput}
                        type="file"
                        accept="image/jpeg,image/png,image/webp"
                        className="hidden"
                        onChange={(e) => chooseFile(e.target.files?.[0])}
                    />
                </div>
            )}
        </JourneyLayout>
    );
}

/**
 * Still-photo preview with the face-tracking oval. Measures its own
 * container so the oval maps correctly through the object-cover crop,
 * and remeasures on resize/orientation change.
 */
function UploadPreview({ src, check }) {
    const boxRef = useRef(null);
    const [size, setSize] = useState(null);

    useEffect(() => {
        const measure = () => {
            const r = boxRef.current?.getBoundingClientRect();
            if (r) setSize({ w: r.width, h: r.height });
        };
        measure();
        window.addEventListener('resize', measure);
        return () => window.removeEventListener('resize', measure);
    }, []);

    const overlay =
        check?.face && size
            ? faceToOverlay(check.face, check.imgW, check.imgH, size.w, size.h)
            : null;
    const analyzing = check?.state === 'analyzing';
    const tone = analyzing || !overlay ? 'neutral' : check.tone;

    return (
        <div
            ref={boxRef}
            className="relative w-full aspect-[3/4] max-h-[420px] rounded-xl overflow-hidden bg-tertiary"
        >
            <img src={src} alt="Captured" className="absolute inset-0 w-full h-full object-cover" />

            {overlay ? (
                <FaceOval overlay={overlay} tone={tone} />
            ) : (
                <div className="absolute inset-0 flex items-center justify-center pointer-events-none">
                    <div className="w-44 h-56 rounded-[50%] border-2 border-dashed border-on-tertiary/70" />
                </div>
            )}

            <div className="absolute top-3 left-3 flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-tertiary/60 text-on-tertiary font-label-code text-label-code">
                <Icon name="check_circle" className="text-[14px]" />
                <span>Preview</span>
            </div>

            {analyzing && (
                <div className="absolute inset-0 flex flex-col items-center justify-center gap-2 bg-tertiary/50 text-on-tertiary">
                    <span className="w-8 h-8 rounded-full border-2 border-on-tertiary/40 border-t-on-tertiary animate-spin" />
                    <span className="font-label-code text-label-code">Analysing photo…</span>
                </div>
            )}
        </div>
    );
}
