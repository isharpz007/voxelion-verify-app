import { router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import Icon from '../Components/Icon';
import JourneyLayout from '../Components/JourneyLayout';

const COUNTRY_CODES = ['+234', '+1', '+44', '+254', '+27'];
const CONTACT_METHODS = [
    { value: 'email', label: 'Email' },
    { value: 'whatsapp', label: 'WhatsApp' },
    { value: 'phone', label: 'Phone Call' },
];

function Field({ label, helper, error, children }) {
    return (
        <div className="space-y-1.5">
            <label className="block font-label-lg text-label-lg font-semibold text-on-surface">{label}</label>
            {children}
            {error ? (
                <p className="font-body-sm text-body-sm text-error flex items-center gap-1">
                    <Icon name="error" className="text-[14px]" /> {error}
                </p>
            ) : (
                helper && <p className="font-body-sm text-body-sm text-on-surface-variant">{helper}</p>
            )}
        </div>
    );
}

const inputClass =
    'w-full h-12 px-space-md rounded-lg bg-surface font-body-md text-on-surface placeholder:text-on-surface-variant/50 focus:outline-none focus:ring-2 focus:ring-[#0ea5e9] transition-all border border-outline-variant/40';

export default function Intake({ task, participant, awaitingVerification, devCode }) {
    const form = useForm({
        full_name: participant?.full_name ?? '',
        email: participant?.email ?? '',
        phone_country_code: participant?.phone_country_code ?? '+234',
        phone: participant?.phone ?? '',
        preferred_contact: participant?.preferred_contact ?? 'email',
    });
    const codeForm = useForm({ code: '' });

    // Details submitted and a code was emailed — show the verify screen
    // unless the user chooses to edit their details.
    const [editing, setEditing] = useState(!awaitingVerification);

    const submit = (e) => {
        e.preventDefault();
        form.put('/intake', { onSuccess: () => setEditing(false) });
    };

    const verify = (e) => {
        e.preventDefault();
        codeForm.post('/intake/verify');
    };

    if (awaitingVerification && !editing) {
        return (
            <JourneyLayout step={2} taskRef={task.reference} showBack>
                <div className="space-y-space-xs">
                    <p className="font-label-code text-label-code text-[#0ea5e9] uppercase font-bold tracking-wider">Email Verification</p>
                    <h1 className="font-headline-lg-mobile text-headline-lg-mobile font-semibold text-on-surface tracking-tight">Check your email</h1>
                    <p className="font-body-md text-body-md text-on-surface-variant leading-relaxed">
                        We sent a 6-digit verification code to <strong className="text-on-surface">{participant?.email}</strong>. Enter it below to continue — it expires in 10 minutes.
                    </p>
                </div>

                {devCode && (
                    <div className="w-full bg-surface-container-high rounded-xl p-space-md flex items-center gap-2">
                        <Icon name="bug_report" className="text-on-surface-variant" />
                        <span className="font-body-sm text-body-sm text-on-surface-variant">Dev mode code: <strong className="font-label-code">{devCode}</strong></span>
                    </div>
                )}

                <form onSubmit={verify} className="space-y-space-lg">
                    <div className="w-full bg-surface-container-lowest rounded-xl p-space-md shadow-sm space-y-space-md">
                        <Field label="Verification code" error={codeForm.errors.code}>
                            <input
                                className={`${inputClass} text-center tracking-[0.5em] font-label-code text-label-lg`}
                                type="text"
                                inputMode="numeric"
                                autoComplete="one-time-code"
                                maxLength={6}
                                value={codeForm.data.code}
                                onChange={(e) => codeForm.setData('code', e.target.value.replace(/\D/g, ''))}
                            />
                        </Field>
                    </div>

                    <button
                        type="submit"
                        disabled={codeForm.processing || codeForm.data.code.length !== 6}
                        className="w-full h-[52px] bg-primary-container hover:bg-primary text-on-primary rounded-xl font-title-md text-title-md flex items-center justify-center space-x-2 shadow-md active:scale-[0.99] transition-transform disabled:opacity-60"
                    >
                        <span>{codeForm.processing ? 'Verifying…' : 'Verify & Continue'}</span>
                        <Icon name="arrow_forward" className="text-[20px]" />
                    </button>

                    <div className="flex items-center justify-between">
                        <button type="button" onClick={() => router.post('/intake/verify/resend')} className="font-label-lg text-label-lg text-primary hover:underline">
                            Resend code
                        </button>
                        <button type="button" onClick={() => setEditing(true)} className="font-label-lg text-label-lg text-on-surface-variant hover:underline">
                            Use a different email
                        </button>
                    </div>
                </form>
            </JourneyLayout>
        );
    }

    return (
        <JourneyLayout step={2} taskRef={task.reference} showBack>
            <div className="space-y-space-xs">
                <p className="font-label-code text-label-code text-[#0ea5e9] uppercase font-bold tracking-wider">Volunteer Profile</p>
                <h1 className="font-headline-lg-mobile text-headline-lg-mobile font-semibold text-on-surface tracking-tight">Your Information</h1>
                <p className="font-body-md text-body-md text-on-surface-variant leading-relaxed">
                    Please provide the official details requested below. Make sure all entries accurately correspond to your government ID before continuing.
                </p>
            </div>

            {form.hasErrors && !Object.keys(form.errors).length && (
                <div className="w-full bg-error-container rounded-xl p-space-md flex items-center gap-2">
                    <Icon name="error" className="text-on-error-container" />
                    <span className="font-body-md text-body-md text-on-error-container">Submission failed — please try again.</span>
                </div>
            )}

            <form onSubmit={submit} className="space-y-space-lg">
                <div className="w-full bg-surface-container-lowest rounded-xl p-space-md shadow-sm space-y-space-md">
                    <div className="pb-2 border-b border-surface-container-high">
                        <h2 className="font-title-md text-title-md text-on-surface">Personal Information</h2>
                    </div>

                    <Field label="Full Name" error={form.errors.full_name} helper="As it appears on your official documents.">
                        <input
                            className={inputClass}
                            type="text"
                            autoComplete="name"
                            value={form.data.full_name}
                            onChange={(e) => form.setData('full_name', e.target.value)}
                        />
                    </Field>

                    <Field label="Email Address" error={form.errors.email} helper="We'll send verification confirmation and audit receipts here.">
                        <input
                            className={inputClass}
                            type="email"
                            autoComplete="email"
                            value={form.data.email}
                            onChange={(e) => form.setData('email', e.target.value)}
                        />
                    </Field>

                    <Field label="Phone Number" error={form.errors.phone ?? form.errors.phone_country_code} helper="For critical verification updates & SMS security challenges.">
                        <div className="flex gap-2">
                            <div className="relative">
                                <select
                                    className="h-12 pl-space-sm pr-7 rounded-lg bg-surface font-label-lg text-on-surface focus:outline-none focus:ring-2 focus:ring-[#0ea5e9] appearance-none border border-outline-variant/40"
                                    value={form.data.phone_country_code}
                                    onChange={(e) => form.setData('phone_country_code', e.target.value)}
                                    aria-label="Country Calling Code"
                                >
                                    {COUNTRY_CODES.map((c) => (
                                        <option key={c} value={c}>{c}</option>
                                    ))}
                                </select>
                                <Icon name="expand_more" className="absolute right-1.5 top-1/2 -translate-y-1/2 text-[16px] text-on-surface-variant pointer-events-none" />
                            </div>
                            <input
                                className={inputClass}
                                type="tel"
                                autoComplete="tel-national"
                                value={form.data.phone}
                                onChange={(e) => form.setData('phone', e.target.value)}
                            />
                        </div>
                    </Field>
                </div>

                <div className="w-full bg-surface-container-lowest rounded-xl p-space-md shadow-sm space-y-space-md">
                    <div className="flex items-center justify-between pb-2 border-b border-surface-container-high">
                        <h2 className="font-title-md text-title-md text-on-surface">Additional Details</h2>
                        <span className="font-label-code text-label-code text-outline">Optional</span>
                    </div>

                    <Field label="Preferred Contact Method" error={form.errors.preferred_contact}>
                        <div className="relative">
                            <select
                                className="w-full h-12 px-space-md pr-10 rounded-lg bg-surface font-body-md text-on-surface focus:outline-none focus:ring-2 focus:ring-[#0ea5e9] appearance-none border border-outline-variant/40"
                                value={form.data.preferred_contact}
                                onChange={(e) => form.setData('preferred_contact', e.target.value)}
                            >
                                {CONTACT_METHODS.map((m) => (
                                    <option key={m.value} value={m.value}>{m.label}</option>
                                ))}
                            </select>
                            <Icon name="expand_more" className="absolute right-3 top-1/2 -translate-y-1/2 text-[18px] text-on-surface-variant pointer-events-none" />
                        </div>
                    </Field>
                </div>

                <button
                    type="submit"
                    disabled={form.processing}
                    className="w-full h-[52px] bg-primary-container hover:bg-primary text-on-primary rounded-xl font-title-md text-title-md flex items-center justify-center space-x-2 shadow-md active:scale-[0.99] transition-transform disabled:opacity-60"
                >
                    <span>{form.processing ? 'Saving…' : 'Continue to Photo'}</span>
                    <Icon name="arrow_forward" className="text-[20px]" />
                </button>
            </form>
        </JourneyLayout>
    );
}
