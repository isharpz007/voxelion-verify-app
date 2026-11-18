import { router, useForm } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
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

function OtpInput({ value, onChange }) {
    // Six auto-advancing boxes: digit entry moves focus forward, backspace
    // moves it back, and pasting a full code fills every box at once.
    const refs = useRef([]);

    const handleKey = (i, e) => {
        const digit = e.target.value.replace(/\D/g, '');
        if (digit) {
            onChange((value.slice(0, i) + digit.slice(-1) + value.slice(i + 1)).slice(0, 6));
            if (i < 5) refs.current[i + 1]?.focus();
        }
    };

    const handleKeyDown = (i, e) => {
        if (e.key === 'Backspace' && !value[i] && i > 0) {
            refs.current[i - 1]?.focus();
        }
    };

    const handlePaste = (e) => {
        const pasted = e.clipboardData.getData('text').replace(/\D/g, '').slice(0, 6);
        if (!pasted) return;
        e.preventDefault();
        onChange(pasted);
        refs.current[Math.min(pasted.length, 5)]?.focus();
    };

    return (
        <div className="flex justify-between gap-2" onPaste={handlePaste}>
            {[0, 1, 2, 3, 4, 5].map((i) => (
                <input
                    key={i}
                    ref={(el) => (refs.current[i] = el)}
                    className="w-12 h-14 text-center rounded-lg bg-surface font-label-code text-label-lg text-on-surface focus:outline-none focus:ring-2 focus:ring-[#0ea5e9] transition-all border border-outline-variant/40"
                    type="text"
                    inputMode="numeric"
                    autoComplete={i === 0 ? 'one-time-code' : 'off'}
                    maxLength={1}
                    value={value[i] ?? ''}
                    onChange={(e) => handleKey(i, e)}
                    onKeyDown={(e) => handleKeyDown(i, e)}
                />
            ))}
        </div>
    );
}

export default function Intake({ task, participant, awaitingVerification, contactMethods = [], otpStatus }) {
    const form = useForm({
        full_name: participant?.full_name ?? '',
        email: participant?.email ?? '',
        phone_country_code: participant?.phone_country_code ?? '+234',
        phone: participant?.phone ?? '',
        preferred_contact: participant?.preferred_contact ?? 'email',
    });
    const sendForm = useForm({ channel: '' });
    const codeForm = useForm({ code: '' });

    // Details submitted but no channel verified — show method selection
    // (or the in-flight OTP screen) unless the user edits their details.
    const [editing, setEditing] = useState(!awaitingVerification);
    const [choosingMethod, setChoosingMethod] = useState(!otpStatus?.active);
    const [secondsLeft, setSecondsLeft] = useState(otpStatus?.expires_in ?? 0);
    const [cooldown, setCooldown] = useState(otpStatus?.cooldown_remaining ?? 0);

    // Tick down OTP validity and resend cooldown once per second.
    useEffect(() => {
        const t = setInterval(() => {
            setSecondsLeft((s) => Math.max(0, s - 1));
            setCooldown((c) => Math.max(0, c - 1));
        }, 1000);
        return () => clearInterval(t);
    }, []);

    const submit = (e) => {
        e.preventDefault();
        form.put('/intake', { onSuccess: () => setEditing(false) });
    };

    const sendCode = (channel) => {
        // Optimistic transition — waiting on onSuccess left the screen stuck
        // on method selection even though the code was sent. onError flips
        // back so a delivery failure still shows the error on the picker.
        setChoosingMethod(false);
        sendForm.transform(() => ({ channel })).post('/api/otp/send', {
            onSuccess: () => { setSecondsLeft(300); setCooldown(60); },
            onError: () => setChoosingMethod(true),
        });
    };

    const resend = () => {
        sendForm.transform(() => ({ channel: otpStatus?.channel ?? 'email' })).post('/api/otp/resend', {
            onSuccess: () => { setSecondsLeft(300); setCooldown(60); },
        });
    };

    const verify = (e) => {
        e.preventDefault();
        codeForm.post('/api/otp/verify');
    };

    const activeChannel = otpStatus?.channel;
    const activeDestination = contactMethods.find((m) => m.channel === activeChannel)?.destination;
    const expired = secondsLeft === 0;

    if (awaitingVerification && !editing) {
        // Step A — pick a delivery channel (only shown when no OTP is in
        // flight, or when the user asks to switch method).
        if (choosingMethod || !activeChannel) {
            return (
                <JourneyLayout step={2} taskRef={task.reference} showBack>
                    <div className="space-y-space-xs">
                        <p className="font-label-code text-label-code text-[#0ea5e9] uppercase font-bold tracking-wider">Contact Verification</p>
                        <h1 className="font-headline-lg-mobile text-headline-lg-mobile font-semibold text-on-surface tracking-tight">Verify your details</h1>
                        <p className="font-body-md text-body-md text-on-surface-variant leading-relaxed">
                            How would you like to receive your verification code?
                        </p>
                    </div>

                    {sendForm.errors.channel && (
                        <p className="font-body-sm text-body-sm text-error flex items-center gap-1">
                            <Icon name="error" className="text-[14px]" /> {sendForm.errors.channel}
                        </p>
                    )}

                    <div className="space-y-space-sm">
                        {contactMethods.map((m) => (
                            <button
                                key={m.channel}
                                type="button"
                                disabled={sendForm.processing}
                                onClick={() => sendCode(m.channel)}
                                className="w-full bg-surface-container-lowest rounded-xl p-space-md shadow-sm border border-outline-variant/40 hover:border-[#0ea5e9] text-left flex items-center gap-3 transition-all active:scale-[0.99] disabled:opacity-60"
                            >
                                <Icon name={m.channel === 'email' ? 'mail' : 'sms'} className="text-[22px] text-[#0ea5e9]" />
                                <span>
                                    <span className="block font-title-md text-title-md text-on-surface">{m.channel === 'email' ? 'Email' : 'SMS'}</span>
                                    <span className="block font-body-sm text-body-sm text-on-surface-variant">
                                        {sendForm.processing ? 'Sending code…' : m.destination}
                                    </span>
                                </span>
                            </button>
                        ))}
                    </div>

                    <button type="button" onClick={() => setEditing(true)} className="font-label-lg text-label-lg text-on-surface-variant hover:underline">
                        Edit my details
                    </button>
                </JourneyLayout>
            );
        }

        // Step B — an OTP is in flight: enter it, watch the countdown,
        // resend after cooldown, or switch delivery method.
        const mm = String(Math.floor(secondsLeft / 60));
        const ss = String(secondsLeft % 60).padStart(2, '0');

        return (
            <JourneyLayout step={2} taskRef={task.reference} showBack>
                <div className="space-y-space-xs">
                    <p className="font-label-code text-label-code text-[#0ea5e9] uppercase font-bold tracking-wider">Contact Verification</p>
                    <h1 className="font-headline-lg-mobile text-headline-lg-mobile font-semibold text-on-surface tracking-tight">Enter your code</h1>
                    <p className="font-body-md text-body-md text-on-surface-variant leading-relaxed">
                        We sent a verification code to <strong className="text-on-surface">{activeDestination}</strong>.
                    </p>
                </div>

                <form onSubmit={verify} className="space-y-space-lg">
                    <div className="w-full bg-surface-container-lowest rounded-xl p-space-md shadow-sm space-y-space-md">
                        <Field label="Verification code" error={codeForm.errors.code}>
                            <OtpInput value={codeForm.data.code} onChange={(v) => codeForm.setData('code', v)} />
                        </Field>
                        <p className={`font-body-sm text-body-sm ${expired ? 'text-error' : 'text-on-surface-variant'}`}>
                            {expired ? 'This code has expired — request a new one.' : `Code expires in ${mm}:${ss}`}
                        </p>
                    </div>

                    <button
                        type="submit"
                        disabled={codeForm.processing || codeForm.data.code.length !== 6 || expired}
                        className="w-full h-[52px] bg-primary-container hover:bg-primary text-on-primary rounded-xl font-title-md text-title-md flex items-center justify-center space-x-2 shadow-md active:scale-[0.99] transition-transform disabled:opacity-60"
                    >
                        <span>{codeForm.processing ? 'Verifying…' : 'Verify & Continue'}</span>
                        <Icon name="arrow_forward" className="text-[20px]" />
                    </button>

                    <div className="flex items-center justify-between">
                        <button
                            type="button"
                            onClick={resend}
                            disabled={sendForm.processing || cooldown > 0}
                            className="font-label-lg text-label-lg text-primary hover:underline disabled:opacity-50 disabled:no-underline"
                        >
                            {cooldown > 0 ? `Resend code (${cooldown}s)` : 'Resend code'}
                        </button>
                        <button type="button" onClick={() => setChoosingMethod(true)} className="font-label-lg text-label-lg text-on-surface-variant hover:underline">
                            Change delivery method
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
