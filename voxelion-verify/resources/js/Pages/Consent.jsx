import { useForm, router } from '@inertiajs/react';
import { useState } from 'react';
import Icon from '../Components/Icon';
import JourneyLayout from '../Components/JourneyLayout';

const DISCLOSURES = [
    { icon: 'badge', title: 'Why we verify your identity', body: 'Identity verification helps confirm that you are the person participating in the volunteer task you accepted on OpinionPadi.' },
    { icon: 'photo_camera_front', title: 'What the process involves', body: 'During this process, you will provide the required information, upload a photograph, and complete a short live verification check.' },
    { icon: 'encrypted', title: 'How your information is handled', body: 'Your information is processed for the purpose of completing your identity verification. Please review the privacy disclosure carefully before continuing.' },
];

function ConsentCheck({ id, checked, onChange, children }) {
    return (
        <label className="flex items-start gap-3 p-space-md bg-surface-container-lowest rounded-xl shadow-sm cursor-pointer select-none active:bg-surface-container-low transition-colors" htmlFor={id}>
            <div className="relative flex items-center justify-center w-6 h-6 mt-0.5 flex-shrink-0">
                <input className="peer sr-only" id={id} type="checkbox" checked={checked} onChange={onChange} />
                <div className="w-6 h-6 rounded-md bg-surface-container-high transition-all peer-checked:bg-primary-container flex items-center justify-center shadow-inner">
                    <Icon name="check" className="text-on-primary text-[18px] opacity-0 peer-checked:opacity-100 transition-opacity" fill />
                </div>
            </div>
            <span className="font-body-md text-body-md text-on-surface leading-snug">{children}</span>
        </label>
    );
}

function Modal({ icon, iconClass, title, body, confirmLabel, confirmClass, onConfirm, onCancel, busy }) {
    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-space-md bg-tertiary/40 backdrop-blur-sm">
            <div className="w-full max-w-sm bg-surface-container-lowest rounded-xl p-space-lg shadow-xl space-y-space-md">
                <div className={`w-12 h-12 rounded-full ${iconClass} flex items-center justify-center mx-auto`}>
                    <Icon name={icon} className="text-[24px]" />
                </div>
                <div className="text-center space-y-1.5">
                    <h3 className="font-headline-sm text-headline-sm text-on-surface">{title}</h3>
                    <p className="font-body-md text-body-md text-on-surface-variant leading-relaxed">{body}</p>
                </div>
                <div className="space-y-space-xs pt-space-xs">
                    <button
                        className={`w-full h-11 rounded-lg ${confirmClass} font-title-md text-title-md flex items-center justify-center transition-colors disabled:opacity-60`}
                        onClick={onConfirm}
                        disabled={busy}
                        type="button"
                    >
                        {busy ? 'Working…' : confirmLabel}
                    </button>
                    <button
                        className="w-full h-11 rounded-lg bg-surface-container-low text-on-surface font-title-md text-title-md flex items-center justify-center transition-colors"
                        onClick={onCancel}
                        type="button"
                    >
                        Go Back
                    </button>
                </div>
            </div>
        </div>
    );
}

export default function Consent({ task }) {
    const form = useForm({ agree_processing: false, agree_biometrics: false });
    const [declining, setDeclining] = useState(false);
    const [busy, setBusy] = useState(false);

    const ready = form.data.agree_processing && form.data.agree_biometrics;

    const submit = () => {
        form.transform((data) => ({ ...data, agree_processing: 'yes', agree_biometrics: 'yes' }));
        form.post('/consent');
    };

    const decline = () => {
        setBusy(true);
        router.post('/consent/decline', {}, { onFinish: () => setBusy(false) });
    };

    return (
        <JourneyLayout step={1} taskRef={task.reference} showBack>
            <div className="space-y-space-xs">
                <h1 className="font-headline-lg-mobile text-headline-lg-mobile font-semibold text-on-surface tracking-tight">Your Consent Matters</h1>
                <p className="font-body-md text-body-md text-on-surface-variant leading-relaxed">
                    Before we begin verifying your identity, please review the information below and confirm your consent.
                </p>
            </div>

            <div className="w-full bg-surface-container-lowest rounded-xl p-space-md shadow-sm space-y-space-md">
                {DISCLOSURES.map((d) => (
                    <div key={d.title} className="flex items-start gap-3">
                        <div className="w-9 h-9 rounded-lg bg-surface-container-low flex-shrink-0 flex items-center justify-center text-primary-container">
                            <Icon name={d.icon} className="text-[20px]" />
                        </div>
                        <div className="space-y-1 min-w-0">
                            <h2 className="font-title-md text-title-md text-on-surface">{d.title}</h2>
                            <p className="font-body-sm text-body-sm text-on-surface-variant leading-relaxed">{d.body}</p>
                        </div>
                    </div>
                ))}
                <div className="p-space-sm rounded-lg bg-surface-container-low flex items-start gap-2">
                    <Icon name="info" className="text-outline text-[16px] flex-shrink-0 mt-0.5" />
                    <p className="font-body-sm text-body-sm text-outline leading-snug">
                        Draft copy for interface review. Official NDPA &amp; legal wording will be applied upon final review.
                    </p>
                </div>
            </div>

            <div className="space-y-space-sm">
                <ConsentCheck
                    id="consent-check-1"
                    checked={form.data.agree_processing}
                    onChange={(e) => form.setData('agree_processing', e.target.checked)}
                >
                    I have read and understood the privacy disclosure and agree to the processing of my personal information for identity verification.
                </ConsentCheck>
                <ConsentCheck
                    id="consent-check-2"
                    checked={form.data.agree_biometrics}
                    onChange={(e) => form.setData('agree_biometrics', e.target.checked)}
                >
                    I voluntarily consent to the collection and use of my photograph and live verification data for the identity verification process.
                </ConsentCheck>
            </div>

            <div className="space-y-space-sm pt-space-xs">
                <button
                    className={`w-full h-12 rounded-lg font-title-md text-title-md flex items-center justify-center gap-2 transition-all ${
                        ready
                            ? 'bg-primary-container text-on-primary shadow-md active:scale-[0.99]'
                            : 'bg-surface-container text-outline cursor-not-allowed shadow-none'
                    }`}
                    disabled={!ready || form.processing}
                    onClick={submit}
                    type="button"
                >
                    <span>{form.processing ? 'Recording…' : 'Continue'}</span>
                    <Icon name="arrow_forward" className="text-[18px]" />
                </button>
                <button
                    className="w-full h-12 rounded-lg bg-transparent hover:bg-surface-container-low text-on-surface-variant font-title-md text-title-md flex items-center justify-center transition-colors"
                    onClick={() => setDeclining(true)}
                    type="button"
                >
                    Decline Consent
                </button>
            </div>

            {declining && (
                <Modal
                    icon="gpp_bad"
                    iconClass="bg-error-container text-on-error-container"
                    title="Decline Consent?"
                    body={`Declining means you cannot continue identity verification for task ${task.reference} on OpinionPadi. Your task assignment may be released.`}
                    confirmLabel="Confirm Decline"
                    confirmClass="bg-error text-on-error"
                    onConfirm={decline}
                    onCancel={() => setDeclining(false)}
                    busy={busy}
                />
            )}
        </JourneyLayout>
    );
}
