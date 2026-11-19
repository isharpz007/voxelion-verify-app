/**
 * Shared journey shell: back button, wordmark, security chip, animated 5-step progress (clickable to revisit steps), flash banner, and Withdraw affordance.
 */
import { useEffect, useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import Icon from './Icon';
import Reveal, { RevealGroup } from './Reveal';

const STEPS = ['Consent', 'Intake', 'Photo', 'Live', 'Result'];
const STEP_ROUTES = ['/consent', '/intake', '/photo', '/live', '/result'];

/**
 * Wraps every journey page: back button, VOXELION wordmark, security chip,
 * the 5-step progress indicator, and the persistent Withdraw affordance
 * with its confirmation dialog.
 */
export default function JourneyLayout({ step, taskRef, children, showBack = false }) {
    const [confirming, setConfirming] = useState(false);
    const [withdrawing, setWithdrawing] = useState(false);
    // Steps animate up to the real position on mount — same fill/glow
    // treatment as the welcome screen, but honest progress per page.
    const [shown, setShown] = useState(0);

    useEffect(() => {
        if (!step) return undefined;
        setShown(0);
        const t = setInterval(() => setShown((c) => (c >= step ? c : c + 1)), 450);
        return () => clearInterval(t);
    }, [step]);

    const withdraw = () => {
        setWithdrawing(true);
        router.post('/withdraw', {}, { onFinish: () => setWithdrawing(false) });
    };

    return (
        <main className="flex-1 flex flex-col relative w-full bg-surface min-h-screen">
            <div className="flex flex-col w-full max-w-md mx-auto px-margin pb-space-2xl space-y-space-lg select-none">
                <header className="flex items-center justify-between w-full pt-space-xs">
                    {showBack ? (
                        <button
                            aria-label="Go back"
                            onClick={() => window.history.back()}
                            className="w-10 h-10 rounded-full bg-surface-container-lowest shadow-sm flex items-center justify-center text-on-surface active:scale-95 transition-transform"
                            type="button"
                        >
                            <Icon name="arrow_back" className="text-[20px]" />
                        </button>
                    ) : (
                        <span className="w-10" />
                    )}
                    <div className="flex flex-col items-center">
                        <span className="font-title-md text-title-md tracking-tight text-on-surface font-semibold">VOXELION</span>
                        {step && (
                            <span className="font-label-code text-label-code text-tertiary-container tracking-wider font-semibold">
                                STEP {step} OF 5
                            </span>
                        )}
                    </div>
                    <div className="flex items-center gap-1 bg-surface-container-lowest px-2.5 py-1.5 rounded-full shadow-sm">
                        <span className="w-1.5 h-1.5 rounded-full bg-tertiary-container animate-pulse" />
                        <span className="font-label-code text-label-code text-on-surface-variant">Secured</span>
                    </div>
                </header>

                {step && (
                    <Reveal>
                        <div className="w-full bg-surface-container-lowest rounded-xl p-space-md shadow-sm space-y-space-sm">
                        <div className="relative flex items-center justify-between px-2">
                            <div className="absolute left-4 right-4 top-1/2 -translate-y-1/2 h-0.5 bg-surface-container-high z-0" />
                            <div
                                className="absolute left-4 top-1/2 -translate-y-1/2 h-0.5 bg-[#111111] z-0"
                                style={{
                                    width: `${Math.max(0, ((shown - 1) / (STEPS.length - 1)) * 90)}%`,
                                    transition: 'width .5s ease',
                                }}
                            />
                            {STEPS.map((label, i) => {
                                const n = i + 1;
                                const active = n === shown;
                                const done = n < shown;
                                const reachable = n <= step;
                                return (
                                    <div
                                        key={label}
                                        className={`relative z-10 flex flex-col items-center gap-1 ${
                                            reachable ? 'cursor-pointer group' : 'cursor-default'
                                        }`}
                                        role={reachable ? 'button' : undefined}
                                        tabIndex={reachable ? 0 : undefined}
                                        title={reachable ? `Go to ${label}` : undefined}
                                        onClick={() => reachable && router.get(STEP_ROUTES[n - 1])}
                                        onKeyDown={(e) => reachable && e.key === 'Enter' && router.get(STEP_ROUTES[n - 1])}
                                    >
                                        <div
                                            className={`rounded-full flex items-center justify-center transition-all duration-300 ${
                                                done
                                                    ? 'w-6 h-6 bg-[#111111] text-white shadow-sm'
                                                    : active
                                                      ? 'w-6 h-6 bg-white border-2 border-[#111111] text-[#111111]'
                                                      : 'w-5 h-5 bg-surface-container-high text-on-surface-variant'
                                            } ${reachable ? 'group-hover:scale-110 group-hover:ring-2 group-hover:ring-on-surface/20' : ''}`}
                                            style={
                                                active
                                                    ? {
                                                          boxShadow:
                                                              '0 0 0 3px rgba(0,0,0,.14), 0 0 12px 3px rgba(0,0,0,.22)',
                                                      }
                                                    : undefined
                                            }
                                        >
                                            {done ? (
                                                <Icon name="check" className="text-[13px]" fill />
                                            ) : (
                                                <span className="text-[10px] font-semibold">{n}</span>
                                            )}
                                        </div>
                                        <span
                                            className={`font-label-code text-label-code transition-colors duration-300 ${
                                                active
                                                    ? 'text-[#111111] font-bold'
                                                    : done
                                                      ? 'text-on-surface'
                                                      : 'text-outline'
                                            }`}
                                        >
                                            {label}
                                        </span>
                                    </div>
                                );
                            })}
                        </div>
                        </div>
                    </Reveal>
                )}

                {taskRef && (
                    <Reveal delay={100}>
                        <div className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-surface-container-high text-on-surface-variant font-label-code text-label-code shadow-sm self-start">
                            <Icon name="verified" className="text-[14px] text-tertiary-container" />
                            <span>Task Ref: {taskRef}</span>
                        </div>
                    </Reveal>
                )}

                {usePage().props.flash?.success && (
                    <Reveal delay={0}>
                        <div className="flex items-center gap-2 bg-tertiary-container/15 border border-tertiary-container/40 text-on-surface rounded-xl px-4 py-3 font-body-sm text-body-sm">
                            <Icon name="check_circle" className="text-[18px] text-tertiary-container" />
                            {usePage().props.flash.success}
                        </div>
                    </Reveal>
                )}

                <RevealGroup>{children}</RevealGroup>

                <div className="text-center pt-space-xs">
                    <button
                        className="font-label-md text-label-md text-outline hover:text-error transition-colors py-2 px-3"
                        onClick={() => setConfirming(true)}
                        type="button"
                    >
                        Withdraw Consent
                    </button>
                </div>

                <footer className="pt-space-md text-center space-y-1">
                    <div className="flex items-center justify-center gap-1.5 text-on-surface-variant font-label-md text-label-md">
                        <Icon name="verified_user" className="text-[14px] text-tertiary-container" />
                        <span>Powered by Voxelion Identity Platform</span>
                    </div>
                    <p className="font-label-code text-label-code text-outline">© 2026 Voxelion Security Systems Inc. All rights reserved.</p>
                </footer>
            </div>

            {confirming && (
                <div className="fixed inset-0 z-50 flex items-center justify-center p-space-md bg-tertiary/40 backdrop-blur-sm">
                    <div className="w-full max-w-sm bg-surface-container-lowest rounded-xl p-space-lg shadow-xl space-y-space-md">
                        <div className="w-12 h-12 rounded-full bg-surface-container-high text-primary-container flex items-center justify-center mx-auto">
                            <Icon name="delete_sweep" className="text-[24px]" />
                        </div>
                        <div className="text-center space-y-1.5">
                            <h3 className="font-headline-sm text-headline-sm text-on-surface">Withdraw Consent?</h3>
                            <p className="font-body-md text-body-md text-on-surface-variant leading-relaxed">
                                Withdrawing your consent stops further identity processing and immediately triggers the
                                applicable data purge workflow under applicable privacy regulations.
                            </p>
                        </div>
                        <div className="space-y-space-xs pt-space-xs">
                            <button
                                className="w-full h-11 rounded-lg bg-error text-on-error font-title-md text-title-md flex items-center justify-center transition-colors disabled:opacity-60"
                                onClick={withdraw}
                                disabled={withdrawing}
                                type="button"
                            >
                                {withdrawing ? 'Withdrawing…' : 'Confirm Withdrawal'}
                            </button>
                            <button
                                className="w-full h-11 rounded-lg bg-surface-container-low text-on-surface font-title-md text-title-md flex items-center justify-center transition-colors"
                                onClick={() => setConfirming(false)}
                                type="button"
                            >
                                Cancel
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </main>
    );
}
