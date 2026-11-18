import { useState } from 'react';
import Icon from './Icon';

/**
 * A URL the user can copy — for flows where we can't navigate away
 * ourselves (partner dashboard return, fresh-link recovery). Shows the
 * truncated URL with a copy button and "Copied" feedback.
 */
export default function CopyLink({ url, label = 'Copy link' }) {
    const [copied, setCopied] = useState(false);

    const copy = async () => {
        try {
            await navigator.clipboard.writeText(url);
        } catch {
            // Clipboard API needs a secure context — fall back for older setups.
            const el = document.createElement('textarea');
            el.value = url;
            document.body.appendChild(el);
            el.select();
            document.execCommand('copy');
            el.remove();
        }
        setCopied(true);
        setTimeout(() => setCopied(false), 2000);
    };

    return (
        <div className="w-full bg-surface-container-lowest rounded-xl p-3 shadow-sm flex items-center gap-2">
            <Icon name="link" className="text-[18px] text-on-surface-variant shrink-0" />
            <span className="flex-1 min-w-0 font-label-code text-label-code text-on-surface-variant truncate">
                {url}
            </span>
            <button
                onClick={copy}
                type="button"
                className={`shrink-0 h-9 px-3 rounded-lg font-label-lg text-label-lg flex items-center gap-1.5 transition-colors ${
                    copied
                        ? 'bg-emerald-100 text-emerald-700'
                        : 'bg-primary-container text-on-primary hover:bg-primary'
                }`}
            >
                <Icon name={copied ? 'check' : 'content_copy'} className="text-[15px]" />
                <span>{copied ? 'Copied' : label}</span>
            </button>
        </div>
    );
}
