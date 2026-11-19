/**
 * Step 4: guided liveness capture — challenge prompts, camera feed, retry countdowns.
 */
import { router, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import Icon from '../Components/Icon';
import JourneyLayout from '../Components/JourneyLayout';
import QualityFeedback from '../Components/QualityFeedback';
import { useCamera, CameraFrame } from '../Components/CameraCapture';

const CHALLENGES = {
    blink: { icon: 'visibility', label: 'Blink your eyes', hint: 'Slowly blink twice while looking at the camera.' },
    turn_left: { icon: 'turn_left', label: 'Turn your head left', hint: 'Gently turn your head to your left, then back to centre.' },
    turn_right: { icon: 'turn_right', label: 'Turn your head right', hint: 'Gently turn your head to your right, then back to centre.' },
};

const PHASES = { PERMISSION: 0, READY: 1, COUNTDOWN: 2, RECORDING: 3, PROCESSING: 4 };

export default function LiveCheck({ task, challenge: initialChallenge, attemptsUsed, maxAttempts }) {
    const { errors } = usePage().props;
    const camera = useCamera();
    const [phase, setPhase] = useState(PHASES.PERMISSION);
    const [challenge, setChallenge] = useState(initialChallenge || 'blink');
    const [countdown, setCountdown] = useState(3);
    const [progress, setProgress] = useState(0);
    const [captureError, setCaptureError] = useState(null);
    const attemptsLeft = maxAttempts - attemptsUsed;
    const csrfToken = useRef(document.querySelector('meta[name="csrf-token"]')?.content);

    const begin = async () => {
        await camera.start();
    };

    useEffect(() => {
        if (camera.state === 'active' && phase === PHASES.PERMISSION) setPhase(PHASES.READY);
    }, [camera.state, phase]);

    const fetchChallenge = async () => {
        try {
            const res = await fetch('/live/challenge', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken.current ?? document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '',
                },
                credentials: 'same-origin',
            });
            const data = await res.json();
            if (data.challenge) setChallenge(data.challenge);
        } catch {
            /* keep current challenge */
        }
    };

    const startChallenge = async () => {
        await fetchChallenge();
        setPhase(PHASES.COUNTDOWN);
        setCountdown(3);
    };

    useEffect(() => {
        if (phase !== PHASES.COUNTDOWN) return;
        if (countdown <= 0) {
            setPhase(PHASES.RECORDING);
            setProgress(0);
            return;
        }
        const t = setTimeout(() => setCountdown((c) => c - 1), 1000);
        return () => clearTimeout(t);
    }, [phase, countdown]);

    useEffect(() => {
        if (phase !== PHASES.RECORDING) return;
        const interval = setInterval(() => setProgress((p) => Math.min(100, p + 100 / 30)), 100);
        const record = async () => {
            const clip = await camera.recordClip(3000);
            if (clip) {
                setCaptureError(null);
                setPhase(PHASES.PROCESSING);
                router.post('/live/complete', { capture: clip }, { forceFormData: true });
            } else {
                setCaptureError('We could not record a verification clip on this browser. Please try again, or use a different device.');
                setPhase(PHASES.READY);
            }
        };
        record();
        return () => clearInterval(interval);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [phase]);

    // Accessibility fallback: a clear still photo goes straight to manual
    // review — no liveness attempt is consumed (Phase 0 decision).
    const stillInput = useRef(null);
    const submitStill = (file) => {
        if (!file) return;
        setPhase(PHASES.PROCESSING);
        router.post('/live/complete', { capture: file, still: true }, { forceFormData: true });
    };

    const ch = CHALLENGES[challenge] ?? CHALLENGES.blink;

    const StillFallback = () => (
        <div className="w-full bg-surface-container-lowest rounded-xl p-space-md shadow-sm space-y-2 text-center">
            <p className="font-body-sm text-body-sm text-on-surface-variant">
                Can&apos;t use a camera? Upload one clear photo of your face instead — it goes to a human reviewer.
            </p>
            <button
                onClick={() => stillInput.current?.click()}
                className="w-full h-11 rounded-lg bg-surface-container-high text-on-surface font-title-md text-title-md flex items-center justify-center gap-2"
                type="button"
            >
                <Icon name="upload" className="text-[18px]" /> Upload a clear photo instead
            </button>
            <input
                ref={stillInput}
                type="file"
                accept="image/jpeg,image/png"
                className="hidden"
                onChange={(e) => submitStill(e.target.files?.[0])}
            />
        </div>
    );

    return (
        <JourneyLayout step={4} taskRef={task.reference} showBack>
            <div className="space-y-space-xs">
                <div className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-surface-container-high text-on-surface-variant font-label-code text-label-code">
                    <Icon name="gavel" className="text-[14px] text-tertiary-container" />
                    <span>LiveID</span>
                </div>
                <h1 className="font-headline-lg-mobile text-headline-lg-mobile font-semibold text-on-surface tracking-tight">
                    {phase <= PHASES.READY ? "Let's verify it's really you" : 'Active Verification Challenge'}
                </h1>
            </div>

            <QualityFeedback error={errors.capture || captureError} />

            {attemptsLeft <= 1 && phase <= PHASES.READY && (
                <p className="font-body-sm text-body-sm text-error">This is your last attempt — {attemptsLeft} remaining.</p>
            )}

            <CameraFrame videoRef={camera.videoRef} state={camera.state}>
                {phase === PHASES.COUNTDOWN && (
                    <div className="absolute inset-0 flex items-center justify-center">
                        <span className="font-display-lg text-display-lg text-on-tertiary drop-shadow-lg">{countdown}</span>
                    </div>
                )}
                {phase === PHASES.RECORDING && (
                    <>
                        <div className="absolute top-3 left-3 flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-error/80 text-on-error font-label-code text-label-code">
                            <span className="w-2 h-2 rounded-full bg-on-error animate-pulse" /> REC
                        </div>
                        <div className="absolute bottom-0 left-0 h-1 bg-on-tertiary-container transition-all" style={{ width: `${progress}%` }} />
                    </>
                )}
            </CameraFrame>

            {phase === PHASES.PERMISSION && (
                <div className="w-full bg-surface-container-lowest rounded-xl p-space-md shadow-sm text-center space-y-space-sm">
                    <Icon name="videocam" className="text-[40px] text-primary-container" />
                    <h2 className="font-headline-sm text-headline-sm text-primary">Camera access required</h2>
                    <p className="font-body-md text-body-md text-on-surface-variant">
                        We'll ask you to complete a short challenge — like blinking or turning your head — to confirm a live person is present.
                    </p>
                    <button
                        onClick={begin}
                        className="w-full h-12 bg-primary-container text-on-primary rounded-xl font-title-md text-title-md flex items-center justify-center gap-2 shadow-md"
                        type="button"
                    >
                        <Icon name="security" className="text-[18px]" /> Allow Camera Access
                    </button>
                </div>
            )}

            {camera.state === 'denied' && (
                <div className="w-full bg-error-container rounded-xl p-space-md text-center space-y-2">
                    <h2 className="font-headline-sm text-headline-sm text-on-error-container">Camera access was denied</h2>
                    <p className="font-body-sm text-body-sm text-on-error-container/90">
                        Please enable camera permission for this site in your browser settings, then try again.
                    </p>
                    <button onClick={begin} className="w-full h-11 rounded-lg bg-on-error-container text-on-error font-title-md text-title-md" type="button">
                        Retry
                    </button>
                </div>
            )}
            {camera.state === 'denied' && <StillFallback />}

            {camera.state === 'unavailable' && (
                <div className="w-full bg-surface-container-high rounded-xl p-space-md text-center space-y-2">
                    <h2 className="font-headline-sm text-headline-sm text-on-surface">Camera unavailable</h2>
                    <p className="font-body-sm text-body-sm text-on-surface-variant">
                        No camera was detected on this device. Try another device, or upload a clear photo for manual review.
                    </p>
                </div>
            )}
            {camera.state === 'unavailable' && <StillFallback />}

            {phase === PHASES.READY && camera.state === 'active' && (
                <div className="w-full bg-surface-container-lowest rounded-xl p-space-md shadow-sm space-y-space-md">
                    <div className="flex items-center gap-2 text-on-surface-variant font-body-sm text-body-sm">
                        <Icon name="wb_sunny" className="text-[16px] text-on-tertiary-container" />
                        <span>Good lighting detected — keep head still</span>
                    </div>
                    <div className="flex items-center justify-center gap-3 p-space-md rounded-lg bg-surface-container-low">
                        <Icon name={ch.icon} className="text-[28px] text-primary-container" />
                        <div>
                            <h3 className="font-title-md text-title-md text-primary font-bold">{ch.label}</h3>
                            <p className="font-body-sm text-body-sm text-on-surface-variant">{ch.hint}</p>
                        </div>
                    </div>
                    <button
                        onClick={startChallenge}
                        className="w-full h-12 bg-primary-container text-on-primary rounded-xl font-title-md text-title-md flex items-center justify-center gap-2 shadow-md"
                        type="button"
                    >
                        Start Challenge <Icon name="arrow_forward" className="text-[18px]" />
                    </button>
                </div>
            )}

            {phase === PHASES.PROCESSING && (
                <div className="w-full bg-surface-container-lowest rounded-xl p-space-lg shadow-sm text-center space-y-space-sm">
                    <div className="w-12 h-12 mx-auto rounded-full border-4 border-surface-container-high border-t-primary-container animate-spin" />
                    <h2 className="font-headline-sm text-headline-sm text-primary">Processing your verification</h2>
                    <p className="font-body-sm text-body-sm text-on-surface-variant">Securely checking liveness and similarity…</p>
                </div>
            )}
        </JourneyLayout>
    );
}
