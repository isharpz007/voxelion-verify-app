/**
 * Admin screen listing reward submissions for manual review — each row reveals staggered on scroll.
 */
import { router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import Icon from '../../Components/Icon';
import Reveal, { RevealGroup } from '../../Components/Reveal';

const STATE_STYLES = {
    pending: 'bg-surface-container-high text-on-surface-variant',
    approved: 'bg-primary-container text-on-primary',
    rejected: 'bg-error-container text-on-error-container',
};

function ValidationBadge({ validation }) {
    if (validation.valid) {
        return (
            <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-tertiary-fixed text-on-tertiary-fixed-variant font-label-code text-label-code">
                <Icon name="verified" className="text-[12px]" /> CODE VALID
            </span>
        );
    }
    const labels = { expired: 'EXPIRED', consumed: 'CONSUMED', not_found: 'NOT FOUND', not_verified: 'NOT VERIFIED' };
    return (
        <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-error-container text-on-error-container font-label-code text-label-code">
            <Icon name="gpp_bad" className="text-[12px]" /> {labels[validation.reason] ?? 'INVALID'}
        </span>
    );
}

function Row({ submission, onDecide, busy }) {
    const [reason, setReason] = useState('');

    return (
        <div className="bg-surface-container-lowest rounded-xl p-space-md shadow-sm space-y-space-sm">
            <div className="flex items-start justify-between gap-2">
                <div>
                    <div className="flex items-center gap-2">
                        <span className="font-title-md text-title-md text-on-surface">{submission.participant.name ?? 'Unknown'}</span>
                        <span className={`px-2 py-0.5 rounded-full font-label-code text-label-code uppercase ${STATE_STYLES[submission.review_state]}`}>
                            {submission.review_state}
                        </span>
                    </div>
                    <p className="font-body-sm text-body-sm text-on-surface-variant mt-0.5">
                        {submission.participant.ref} • Task {submission.task_id}
                    </p>
                </div>
                <ValidationBadge validation={submission.code_validation} />
            </div>

            <div className="bg-surface-container-low rounded-lg p-space-sm grid grid-cols-2 gap-2 font-body-sm text-body-sm">
                <div>
                    <span className="text-on-surface-variant">Screenshot proof</span>
                    <p className="font-medium text-on-surface">{submission.has_screenshot ? 'Attached' : 'None'}</p>
                </div>
                <div>
                    <span className="text-on-surface-variant">Submitted</span>
                    <p className="font-medium text-on-surface">{new Date(submission.created_at).toLocaleDateString()}</p>
                </div>
            </div>

            {submission.media?.length > 0 && (
                <div className="flex gap-2 flex-wrap">
                    {submission.media.map((m, i) => (
                        <a
                            key={i}
                            href={m.url}
                            target="_blank"
                            rel="noreferrer"
                            className={`inline-flex items-center gap-1 px-2.5 py-1 rounded-lg font-label-code text-label-code ${
                                m.accepted === false
                                    ? 'bg-error-container text-on-error-container'
                                    : 'bg-surface-container-high text-primary-container'
                            }`}
                        >
                            <Icon name={m.kind === 'video' ? 'videocam' : 'photo_camera'} className="text-[14px]" />
                            View {m.kind}{m.accepted === false ? ' (failed check)' : ''}
                        </a>
                    ))}
                </div>
            )}

            {submission.review_state === 'pending' && (
                <div className="space-y-2 pt-1">
                    <input
                        type="text"
                        placeholder="Rejection reason (required only when rejecting)"
                        value={reason}
                        onChange={(e) => setReason(e.target.value)}
                        className="w-full h-10 px-space-md rounded-lg bg-surface font-body-md text-on-surface placeholder:text-on-surface-variant/50 focus:outline-none focus:ring-2 focus:ring-[#0ea5e9] border border-outline-variant/40"
                    />
                    <div className="flex gap-2">
                        <button
                            onClick={() => onDecide(submission.id, 'approved')}
                            disabled={busy || !submission.code_validation.valid}
                            title={submission.code_validation.valid ? '' : 'Code must re-validate before approval'}
                            className="flex-1 h-10 rounded-lg bg-primary-container text-on-primary font-label-lg text-label-lg font-semibold disabled:opacity-40"
                            type="button"
                        >
                            Approve reward
                        </button>
                        <button
                            onClick={() => onDecide(submission.id, 'rejected', reason)}
                            disabled={busy}
                            className="flex-1 h-10 rounded-lg bg-error-container text-on-error-container font-label-lg text-label-lg font-semibold disabled:opacity-40"
                            type="button"
                        >
                            Reject
                        </button>
                    </div>
                </div>
            )}

            {submission.review_state !== 'pending' && (
                <p className="font-body-sm text-body-sm text-on-surface-variant">
                    Decided {submission.decision_at ? new Date(submission.decision_at).toLocaleString() : ''}
                    {submission.rejection_reason ? ` — ${submission.rejection_reason}` : ''}
                </p>
            )}
        </div>
    );
}

export default function ReviewQueue({ submissions, pendingCount }) {
    const { errors } = usePage().props;
    const [busy, setBusy] = useState(false);
    const rows = submissions.data ?? [];

    const decide = (id, decision, reason = '') => {
        setBusy(true);
        router.post(`/admin/review/${id}`, { decision, reason: reason || null }, { onFinish: () => setBusy(false) });
    };

    return (
        <main className="flex-1 flex flex-col w-full bg-surface min-h-screen">
            <div className="flex flex-col w-full max-w-2xl mx-auto px-margin py-space-md space-y-space-md">
                <header className="flex items-center justify-between">
                    <div>
                        <h1 className="font-headline-md text-headline-md text-primary tracking-tight">Reward Review Queue</h1>
                        <p className="font-body-sm text-body-sm text-on-surface-variant">
                            Every approval is backed by a server-side completion-code check — screenshots alone are never sufficient.
                        </p>
                    </div>
                    <span className="px-3 py-1 rounded-full bg-surface-container-high font-label-code text-label-code text-on-surface-variant">
                        {pendingCount ?? rows.filter((s) => s.review_state === 'pending').length} pending
                    </span>
                </header>

                <RevealGroup>
                {errors.decision && (
                    <div className="bg-error-container rounded-xl p-space-md font-body-sm text-body-sm text-on-error-container">{errors.decision}</div>
                )}

                {rows.length === 0 && (
                    <div className="bg-surface-container-lowest rounded-xl p-space-xl shadow-sm text-center text-on-surface-variant font-body-md text-body-md">
                        No reward submissions yet.
                    </div>
                )}

                <div className="space-y-space-md">
                    {rows.map((s, i) => (
                        <Reveal key={s.id} delay={i * 100}>
                            <Row submission={s} onDecide={decide} busy={busy} />
                        </Reveal>
                    ))}
                </div>

                {submissions.last_page > 1 && (
                    <div className="flex items-center justify-center gap-1 pt-space-xs">
                        {submissions.links.map((link, i) => (
                            <button
                                key={i}
                                type="button"
                                disabled={!link.url}
                                onClick={() => link.url && router.get(link.url, {}, { preserveScroll: true })}
                                className={`min-w-9 h-9 px-2 rounded-lg font-label-md text-label-md ${
                                    link.active
                                        ? 'bg-primary-container text-on-primary'
                                        : 'bg-surface-container-lowest text-on-surface-variant disabled:opacity-40'
                                }`}
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        ))}
                    </div>
                )}
                </RevealGroup>
            </div>
        </main>
    );
}
