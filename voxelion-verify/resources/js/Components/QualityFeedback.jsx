import Icon from './Icon';

/** Inline quality-check feedback banner for the photo step. */
export default function QualityFeedback({ error }) {
    if (!error) return null;

    return (
        <div className="w-full bg-error-container rounded-xl p-space-md flex items-start gap-3">
            <Icon name="error" className="text-on-error-container text-[20px] shrink-0 mt-0.5" />
            <div className="min-w-0">
                <h3 className="font-title-md text-title-md text-on-error-container">Check failed</h3>
                <p className="font-body-sm text-body-sm text-on-error-container/90 mt-0.5">{error}</p>
            </div>
        </div>
    );
}
