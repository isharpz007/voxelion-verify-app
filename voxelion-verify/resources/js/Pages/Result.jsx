/**
 * Step 5: outcome screen — completion code with copy button, expiry, similarity score and reward status.
 */
import { useState } from 'react';
import Icon from '../Components/Icon';
import CopyLink from '../Components/CopyLink';

const STEP_LABELS = ['Consent', 'Intake', 'Photo', 'Live Check', 'Result'];

function Header() {
    return (
        <header className="flex items-center justify-between w-full py-space-sm">
            <span className="font-title-md text-title-md tracking-tight text-primary-container font-semibold">Voxelion</span>
            <div className="inline-flex items-center gap-space-xs px-2.5 py-1 rounded-full bg-surface-container-high/60">
                <Icon name="lock" className="text-on-surface-variant text-[14px]" />
                <span className="font-label-code text-label-code text-on-surface-variant tracking-wider uppercase">Encrypted</span>
            </div>
        </header>
    );
}

function Stepper() {
    return (
        <div className="bg-surface-container-lowest rounded-xl p-space-md shadow-sm">
            <div className="flex items-center justify-between mb-space-sm">
                <span className="font-label-code text-label-code text-on-surface-variant uppercase tracking-wider">Verification Pipeline</span>
                <span className="font-label-code text-label-code text-primary-container font-semibold">Step 5 of 5 • Finalized</span>
            </div>
            <div className="relative flex items-start justify-between w-full">
                <div className="absolute left-4 right-4 top-3 -translate-y-1/2 h-1 bg-surface-container rounded-full z-0">
                    <div className="h-full bg-primary-container rounded-full w-full" />
                </div>
                {STEP_LABELS.map((label, i) => (
                    <div key={label} className="relative z-10 flex flex-col items-center flex-1">
                        <div className="w-6 h-6 rounded-full bg-primary-container text-on-primary flex items-center justify-center shadow-sm">
                            <Icon name="check" className="text-[13px]" />
                        </div>
                        <span className="font-label-md text-[10px] text-on-surface-variant font-medium mt-1.5 text-center leading-tight whitespace-nowrap">
                            {i + 1}. {label}
                        </span>
                    </div>
                ))}
            </div>
        </div>
    );
}

function Hero({ icon, chip, chipClass, title, body }) {
    return (
        <div className="bg-surface-container-lowest rounded-xl p-space-lg shadow-sm flex flex-col items-center text-center relative overflow-hidden">
            <div className="absolute -top-12 left-1/2 -translate-x-1/2 w-48 h-48 bg-primary-fixed/30 rounded-full blur-2xl pointer-events-none" />
            <div className="relative mb-space-md">
                <div className="w-20 h-20 rounded-full bg-primary-fixed flex items-center justify-center shadow-md">
                    <div className="w-14 h-14 rounded-full bg-primary-container flex items-center justify-center text-on-primary">
                        <Icon name={icon} className="text-[32px]" />
                    </div>
                </div>
            </div>
            {chip && (
                <div className={`inline-flex items-center gap-1 px-3 py-1 rounded-full ${chipClass} mb-space-sm`}>
                    <span className="w-2 h-2 rounded-full bg-current inline-block" />
                    <span className="font-label-md text-label-md font-semibold tracking-wide">{chip}</span>
                </div>
            )}
            <h1 className="font-headline-lg-mobile text-headline-lg-mobile text-on-surface tracking-tight mb-space-xs">{title}</h1>
            <p className="font-body-md text-body-md text-on-surface-variant max-w-sm">{body}</p>
        </div>
    );
}

export default function Result({ outcome, task, participant, code, codeExpiresAt, similarityScore, verifiedAt, auditHash, reward, opinionpadiUrl }) {
    const [copied, setCopied] = useState(false);

    const copy = async () => {
        try {
            await navigator.clipboard.writeText(code);
            setCopied(true);
            setTimeout(() => setCopied(false), 2000);
        } catch {
            /* clipboard unavailable */
        }
    };

    const verified = outcome === 'verified';
    const review = outcome === 'manual_review';

    return (
        <main className="flex-1 flex flex-col relative w-full bg-surface min-h-screen">
            <div className="flex flex-col w-full max-w-md mx-auto px-gutter pb-space-xl space-y-space-md">
                <Header />
                <Stepper />

                {verified && (
                    <>
                        <Hero
                            icon="check_circle"
                            chip="VERIFYING IN PROGRESS"
                            chipClass="bg-surface-container-high text-primary-container"
                            title="Under review"
                            body="Your verification requires additional review before a final decision can be made. No further action is required at this stage unless you are contacted."
                        />
                        {code && (
                            <div className="bg-surface-container-lowest rounded-xl p-space-md shadow-md flex flex-col gap-space-sm relative overflow-hidden">
                                <div className="flex items-center justify-between">
                                    <div className="flex items-center gap-1.5">
                                        <Icon name="key" className="text-[16px] text-primary-container" />
                                        <span className="font-label-code text-label-code text-on-surface-variant uppercase tracking-wider">Completion Auth Token</span>
                                    </div>
                                    {codeExpiresAt && (
                                        <span className="bg-surface-container-high text-primary-container font-label-code text-[10px] px-2 py-0.5 rounded-md uppercase font-semibold">
                                            Valid 72h
                                        </span>
                                    )}
                                </div>
                                <div className="bg-surface-container-low rounded-lg p-space-md flex flex-col items-center justify-center gap-1">
                                    <span className="font-label-code text-[20px] font-bold tracking-[0.18em] text-primary-container select-all text-center">{code}</span>
                                    <span className="font-body-sm text-[11px] text-on-surface-variant">Single-use authenticated token</span>
                                </div>
                                <button
                                    onClick={copy}
                                    className="w-full h-11 bg-primary-container text-on-primary rounded-lg font-label-lg text-label-lg font-semibold flex items-center justify-center gap-2 active:scale-[0.99] transition-all shadow-sm"
                                    type="button"
                                >
                                    <Icon name={copied ? 'check' : 'content_copy'} className="text-[18px]" />
                                    <span>{copied ? 'Copied!' : 'Copy Completion Code'}</span>
                                </button>
                                <div className="bg-surface-container-high/60 rounded-lg p-2.5 flex items-start gap-2">
                                    <Icon name="tips_and_updates" className="text-[16px] text-primary-container mt-0.5" />
                                    <p className="font-body-sm text-body-sm text-on-surface-variant">
                                        <strong className="font-semibold text-on-surface">Pro-Tip:</strong> Capture or save this token. OpinionPadi auto-syncs this status, but keep this code as fallback proof.
                                    </p>
                                </div>
                            </div>
                        )}
                    </>
                )}

                {review && (
                    <Hero
                        icon="pending_actions"
                        chip="UNDER REVIEW"
                        chipClass="bg-surface-container-high text-primary-container"
                        title="Under review"
                        body="Your verification requires additional review before a final decision can be made. No further action is required at this stage unless you are contacted."
                    />
                )}

                {outcome === 'failed' && (
                    <Hero
                        icon="error"
                        chip="VERIFICATION FAILED"
                        chipClass="bg-error-container text-on-error-container"
                        title="We couldn't verify you"
                        body="The live verification check did not pass. You can return to OpinionPadi and request a new verification link to try again."
                    />
                )}

                <div className="bg-surface-container-lowest rounded-xl p-space-md shadow-sm flex flex-col gap-space-sm">
                    <div className="flex items-center justify-between pb-space-xs">
                        <div className="flex items-center gap-2">
                            <div className="w-9 h-9 rounded-full bg-surface-container-high flex items-center justify-center text-primary-container font-semibold">
                                {(participant?.name ?? 'V').charAt(0).toUpperCase()}
                            </div>
                            <div>
                                <h3 className="font-title-md text-title-md text-on-surface leading-tight">{participant?.name ?? 'Volunteer'}</h3>
                                <span className="font-body-sm text-body-sm text-on-surface-variant">{participant?.role}</span>
                            </div>
                        </div>
                        <Icon name="verified" className="text-primary-container text-[20px]" />
                    </div>
                    <div className="bg-surface-container-low rounded-lg p-space-sm flex flex-col gap-2 font-body-sm text-body-sm">
                        <div className="flex justify-between items-center text-on-surface-variant">
                            <span>Task Reference</span>
                            <span className="font-medium text-on-surface">{task.reference}</span>
                        </div>
                        {similarityScore != null && (
                            <div className="flex justify-between items-center text-on-surface-variant">
                                <span>Match Score</span>
                                <span className="font-medium text-on-surface">{(similarityScore * 100).toFixed(1)}%</span>
                            </div>
                        )}
                        {verifiedAt && (
                            <div className="flex justify-between items-center text-on-surface-variant">
                                <span>Verification Time</span>
                                <span className="font-medium text-on-surface">{new Date(verifiedAt).toLocaleString()}</span>
                            </div>
                        )}
                        {auditHash && (
                            <div className="flex justify-between items-center text-on-surface-variant">
                                <span>Audit Protocol Hash</span>
                                <div className="flex items-center gap-1 font-label-code text-[11px] text-primary-container font-semibold">
                                    <Icon name="lock" className="text-[12px]" />
                                    <span>{auditHash}</span>
                                </div>
                            </div>
                        )}
                        {reward && (
                            <div className="flex justify-between items-center text-on-surface-variant">
                                <span>Reward Status</span>
                                <span className={`font-medium ${
                                    reward.state === 'approved'
                                        ? 'text-emerald-700'
                                        : reward.state === 'rejected'
                                          ? 'text-error'
                                          : 'text-on-surface'
                                }`}>
                                    {reward.state === 'approved'
                                        ? 'Approved'
                                        : reward.state === 'rejected'
                                          ? `Rejected${reward.reason ? ` — ${reward.reason}` : ''}`
                                          : 'Under review'}
                                </span>
                            </div>
                        )}
                    </div>
                </div>

                {opinionpadiUrl && <CopyLink url={opinionpadiUrl} label="Copy link" />}
            </div>
        </main>
    );
}
