import Icon from '../Components/Icon';
import CopyLink from '../Components/CopyLink';

const COPY = {
    declined: {
        chip: 'Consent declined',
        title: 'You chose not to continue',
        body: 'No photographs or personal information were processed for verification. You can close this session safely.',
        icon: 'gpp_bad',
        iconClass: 'bg-error-container text-on-error-container',
    },
    withdrawn: {
        chip: 'Consent withdrawn',
        title: 'Your consent has been withdrawn',
        body: 'All processing has stopped. A data deletion request has been initiated for any information collected during this session.',
        icon: 'delete_sweep',
        iconClass: 'bg-surface-container-high text-primary-container',
    },
    expired: {
        chip: 'Session expired',
        title: 'Your session timed out',
        body: 'For your security this verification session expired. Any collected information will be removed — request a fresh link from OpinionPadi to verify.',
        icon: 'lock_clock',
        iconClass: 'bg-surface-container-highest text-secondary',
    },
};

export default function Exit({ reason, deletionRef, task, opinionpadiUrl }) {
    const copy = COPY[reason] ?? COPY.withdrawn;

    return (
        <main className="flex-1 flex flex-col relative w-full bg-surface min-h-screen">
            <div className="flex flex-col w-full max-w-md mx-auto px-margin pb-space-xl space-y-space-md">
                <header className="flex items-center justify-between w-full py-space-sm">
                    <span className="font-headline-sm text-headline-sm text-primary tracking-tight">Voxelion ID</span>
                    <span className="font-label-code text-label-code text-on-surface-variant">Session Verification Gateway</span>
                </header>

                <div className="flex flex-col items-center text-center pt-4 space-y-space-sm">
                    <div className={`w-16 h-16 rounded-full ${copy.iconClass} flex items-center justify-center shadow-sm`}>
                        <Icon name={copy.icon} className="text-[32px]" />
                    </div>
                    <div className="px-3 py-1 rounded-full bg-surface-container-high text-on-surface-variant font-label-code text-label-code uppercase tracking-wider">
                        {copy.chip}
                    </div>
                    <h1 className="font-headline-lg-mobile text-headline-lg-mobile text-primary tracking-tight">{copy.title}</h1>
                    <p className="font-body-md text-body-md text-secondary max-w-sm">{copy.body}</p>
                </div>

                <div className="w-full bg-surface-container-lowest rounded-xl p-space-md shadow-sm space-y-3">
                    <div className="flex items-center gap-3">
                        <div className="w-9 h-9 rounded-lg bg-surface-container-low flex items-center justify-center text-primary-container">
                            <Icon name="lock_clock" className="text-[20px]" />
                        </div>
                        <div>
                            <h3 className="font-title-md text-title-md text-on-surface">Zero data footprint</h3>
                            <p className="font-body-sm text-body-sm text-on-surface-variant">
                                {reason === 'declined'
                                    ? 'Nothing was collected — consent is required before any data is processed.'
                                    : 'Media and records are being purged per the deletion workflow.'}
                            </p>
                        </div>
                    </div>
                    {deletionRef && (
                        <div className="bg-surface-container-low rounded-lg p-space-sm flex items-center justify-between">
                            <div className="flex items-center gap-2">
                                <Icon name="receipt_long" className="text-[16px] text-on-tertiary-container" />
                                <span className="font-label-code text-label-code text-on-surface">{deletionRef}</span>
                            </div>
                            <span className="font-label-code text-label-code text-on-surface-variant">NDPA Sec. 3.2</span>
                        </div>
                    )}
                </div>

                <div className="flex items-center justify-center gap-2 p-2 rounded-lg bg-surface-container-high/60 text-on-surface-variant">
                    <Icon name="cleaning_services" className="text-[16px]" />
                    <span className="font-body-sm text-body-sm">Session closed safely</span>
                </div>

                {opinionpadiUrl && <CopyLink url={opinionpadiUrl} label="Copy link" />}

                <footer className="pt-space-md text-center space-y-1">
                    <div className="flex items-center justify-center gap-1.5 text-on-surface-variant font-label-md text-label-md">
                        <Icon name="enhanced_encryption" className="text-[14px] text-tertiary-container" />
                        <span>Secured by Voxelion Identity Engine</span>
                    </div>
                    <p className="font-label-code text-label-code text-outline">NDPA Compliant • In official partnership with OpinionPadi</p>
                </footer>
            </div>
        </main>
    );
}
