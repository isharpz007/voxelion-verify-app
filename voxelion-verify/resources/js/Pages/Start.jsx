import { router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import Icon from '../Components/Icon';
import CopyLink from '../Components/CopyLink';

const STEP_LABELS = ['Consent', 'Intake', 'Photo', 'Live', 'Result'];

function Header() {
    return (
        <header className="flex items-center justify-between w-full py-space-sm">
            <span className="font-headline-sm text-headline-sm text-primary tracking-tight">Voxelion</span>
            <div className="flex items-center space-x-1.5 px-3 py-1 rounded-full bg-surface-container-high text-on-surface-variant">
                <Icon name="verified_user" className="text-[16px] text-on-tertiary-container" fill />
                <span className="font-label-code text-label-code text-on-surface">256-bit Encrypted Session</span>
            </div>
        </header>
    );
}

function ValidView({ task, dev }) {
    // A signed token in the URL means we were handed off (dev simulator or
    // OpinionPadi in local env) — begin immediately rather than re-rendering
    // the same welcome screen and making the user click a second time.
    const autoBegin = Boolean(dev) && new URLSearchParams(window.location.search).has('t');
    const [starting, setStarting] = useState(autoBegin);

    useEffect(() => {
        if (autoBegin) {
            router.post(window.location.pathname + window.location.search);
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    const begin = () => {
        setStarting(true);
        if (dev) {
            // Local dev: the simulator signs a real one-time JWT and bounces
            // through /start — the identical hand-off OpinionPadi performs.
            window.location.href = `/dev/handoff?task=${encodeURIComponent(task.reference)}`;
        } else {
            router.post(window.location.pathname + window.location.search);
        }
    };

    return (
        <div className="flex flex-col w-full space-y-space-md">
            <div className="flex flex-col items-center text-center pt-2">
                <div className="relative flex items-center justify-center w-16 h-16 rounded-full bg-surface-container-lowest shadow-md mb-space-sm">
                    <div className="absolute inset-0 rounded-full bg-surface-container-high opacity-50 blur-sm" />
                    <div className="relative w-12 h-12 rounded-full bg-surface-variant flex items-center justify-center text-primary-container">
                        <Icon name="badge" className="text-[28px] text-on-tertiary-container" fill />
                    </div>
                    <div className="absolute -bottom-1 -right-1 w-6 h-6 rounded-full bg-primary-container text-on-primary flex items-center justify-center shadow-sm">
                        <Icon name="check" className="text-[14px]" />
                    </div>
                </div>
                <h1 className="font-headline-lg-mobile text-headline-lg-mobile text-primary tracking-tight">Welcome to Voxelion</h1>
                <p className="font-body-md text-body-md text-secondary mt-2 max-w-xs">
                    You're one step closer to completing your volunteer task. Voxelion helps securely verify your
                    identity through a simple guided process.
                </p>
            </div>

            <div className="w-full bg-surface-container-lowest rounded-xl p-space-md shadow-sm">
                <div className="flex items-center justify-between pb-3 border-b border-surface-container-high">
                    <span className="font-label-code text-label-code text-secondary tracking-wider">TASK DETAILS</span>
                </div>
                <div className="grid grid-cols-2 gap-3 pt-3">
                    <div>
                        <span className="font-body-sm text-body-sm text-secondary">Task Reference</span>
                        <div className="flex items-center space-x-1 mt-0.5">
                            <span className="font-label-code text-label-code font-bold text-primary">{task.reference}</span>
                        </div>
                    </div>
                    <div>
                        <span className="font-body-sm text-body-sm text-secondary">Estimated Duration</span>
                        <div className="flex items-center space-x-1 mt-0.5">
                            <Icon name="schedule" className="text-[14px] text-secondary" />
                            <span className="font-title-md text-title-md text-primary font-medium">{task.duration}</span>
                        </div>
                    </div>
                </div>
                <div className="mt-3 pt-3 border-t border-surface-container-high flex items-center justify-between">
                    <span className="font-body-sm text-body-sm text-secondary">Verification Status</span>
                    <div className="flex items-center space-x-2 px-2.5 py-1 rounded-full bg-surface-container text-on-surface">
                        <span className="w-2 h-2 rounded-full bg-[#10b981] animate-pulse" />
                        <span className="font-label-md text-label-md font-semibold text-primary">Ready to begin</span>
                    </div>
                </div>
            </div>

            <div className="w-full bg-surface-container-low rounded-xl p-space-md">
                <h3 className="font-label-code text-label-code uppercase tracking-wider text-secondary mb-3">Verification Steps</h3>
                <div className="flex items-center justify-between relative">
                    {STEP_LABELS.map((label, i) => (
                        <div key={label} className="contents">
                            <div className="flex flex-col items-center flex-1 text-center">
                                <div
                                    className={`w-7 h-7 rounded-full font-label-code text-label-code flex items-center justify-center font-bold ${
                                        i === 0 ? 'bg-primary-container text-on-primary shadow-sm' : 'bg-surface-variant text-on-surface-variant'
                                    }`}
                                >
                                    {i + 1}
                                </div>
                                <span
                                    className={`font-label-code text-label-code mt-1.5 leading-tight ${
                                        i === 0 ? 'text-primary font-medium' : 'text-on-surface-variant'
                                    }`}
                                >
                                    {label}
                                </span>
                            </div>
                            {i < STEP_LABELS.length - 1 && <div className="h-0.5 bg-surface-variant flex-1 -mt-4" />}
                        </div>
                    ))}
                </div>
            </div>

            <div className="w-full pt-1">
                <button
                    onClick={begin}
                    disabled={starting}
                    className="w-full h-[52px] bg-primary-container hover:bg-primary text-on-primary rounded-xl font-title-md text-title-md flex items-center justify-center space-x-2 shadow-md active:scale-[0.99] transition-transform disabled:opacity-60"
                >
                    <span>{starting ? 'Starting…' : 'Get Started'}</span>
                    <Icon name="arrow_forward" className="text-[20px]" />
                </button>
                <p className="font-body-sm text-body-sm text-secondary text-center mt-2">Next step: Volunteer Consent &amp; Permissions</p>
            </div>

            <div className="flex items-center justify-center space-x-2 p-2 rounded-lg bg-surface-container-high/60 text-on-surface-variant">
                <Icon name="lock" className="text-[16px] text-on-surface-variant" />
                <span className="font-body-sm text-body-sm">Your information is handled securely throughout the verification process.</span>
            </div>
        </div>
    );
}

function ErrorView({ state, opinionpadiUrl }) {
    const config = {
        invalid: {
            icon: 'error_outline',
            chip: 'Authentication Error',
            chipClass: 'bg-error-container text-on-error-container',
            iconClass: 'bg-error-container text-on-error-container',
            title: 'Invalid verification link',
            body: 'This verification link could not be validated or is incomplete. Security protocols require a fresh authenticated token from OpinionPadi.',
        },
        malformed: {
            icon: 'error_outline',
            chip: 'Authentication Error',
            chipClass: 'bg-error-container text-on-error-container',
            iconClass: 'bg-error-container text-on-error-container',
            title: 'Invalid verification link',
            body: 'This verification link could not be validated or is incomplete. Security protocols require a fresh authenticated token from OpinionPadi.',
        },
        expired: {
            icon: 'hourglass_disabled',
            chip: 'Session Expired',
            chipClass: 'bg-surface-container-high text-on-secondary-fixed-variant',
            iconClass: 'bg-surface-container-highest text-secondary',
            title: 'This link has expired',
            body: 'For your security, verification links are only valid for a short time. Please request a new link from OpinionPadi to continue.',
        },
        used: {
            icon: 'replay_circle_filled',
            chip: 'Link Already Used',
            chipClass: 'bg-surface-container-high text-on-secondary-fixed-variant',
            iconClass: 'bg-surface-container-highest text-secondary',
            title: 'This link was already used',
            body: 'Each verification link can only be used once. If you did not complete verification, request a fresh link from OpinionPadi.',
        },
    }[state] ?? {
        icon: 'error_outline',
        chip: 'Authentication Error',
        chipClass: 'bg-error-container text-on-error-container',
        iconClass: 'bg-error-container text-on-error-container',
        title: 'Verification unavailable',
        body: 'We could not start your verification session. Please request a fresh link.',
    };

    return (
        <div className="flex flex-col w-full space-y-space-md">
            <div className="flex flex-col items-center text-center pt-4">
                <div className={`w-16 h-16 rounded-full ${config.iconClass} flex items-center justify-center shadow-sm mb-space-sm`}>
                    <Icon name={config.icon} className="text-[32px]" />
                </div>
                <div className={`px-3 py-1 rounded-full ${config.chipClass} font-label-code text-label-code uppercase tracking-wider mb-2`}>
                    {config.chip}
                </div>
                <h2 className="font-headline-lg-mobile text-headline-lg-mobile text-primary tracking-tight">{config.title}</h2>
                <p className="font-body-md text-body-md text-secondary mt-2 max-w-sm">{config.body}</p>
            </div>

            <div className="w-full bg-surface-container-lowest rounded-xl p-space-md shadow-sm space-y-3">
                <h3 className="font-title-md text-title-md text-primary">What should you do?</h3>
                <div className="flex items-start space-x-3">
                    <div className="w-6 h-6 rounded-full bg-surface-container text-on-surface font-label-code text-label-code flex items-center justify-center shrink-0 mt-0.5">1</div>
                    <p className="font-body-md text-body-md text-on-surface-variant">
                        Return to your <strong>OpinionPadi dashboard</strong> or original volunteer assignment email.
                    </p>
                </div>
                <div className="flex items-start space-x-3">
                    <div className="w-6 h-6 rounded-full bg-surface-container text-on-surface font-label-code text-label-code flex items-center justify-center shrink-0 mt-0.5">2</div>
                    <p className="font-body-md text-body-md text-on-surface-variant">
                        Request a newly generated verification link or get in touch with your task coordinator.
                    </p>
                </div>
            </div>

            <div className="w-full space-y-2 pt-2">
                {opinionpadiUrl && <CopyLink url={opinionpadiUrl} label="Copy link" />}
                <button className="w-full h-[44px] text-primary hover:bg-surface-container-high rounded-xl font-label-lg text-label-lg flex items-center justify-center">
                    Contact Task Coordinator
                </button>
            </div>
        </div>
    );
}

export default function Start({ linkState, task, dev, opinionpadiUrl }) {
    return (
        <main className="flex-1 flex flex-col relative w-full bg-surface min-h-screen">
            <div className="flex flex-col w-full max-w-md mx-auto px-margin pb-space-xl">
                <Header />
                <div className="w-full relative">
                    {linkState === 'valid' && task ? <ValidView task={task} dev={dev} /> : <ErrorView state={linkState} opinionpadiUrl={opinionpadiUrl} />}
                </div>
            </div>
        </main>
    );
}
