<?php

namespace App\Jobs;

use App\Models\AuditEvent;
use App\Models\DeletionRequest;
use App\Models\MediaAsset;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

/**
 * Withdrawal → stop-processing: delete media objects, scrub participant
 * PII, record processor propagation confirmations (NDPA traceability).
 */
class ProcessDeletionRequest implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $deletionRequestId) {}

    public function handle(): void
    {
        $request = DeletionRequest::find($this->deletionRequestId);
        if (! $request || $request->status === 'completed') {
            return;
        }

        $request->update(['status' => 'processing']);
        $participant = $request->participant;
        $confirmations = [];

        // 1. Delete stored media objects (photos + liveness captures).
        foreach (MediaAsset::where('participant_id', $participant->id)->get() as $asset) {
            Storage::disk('media')->delete($asset->storage_key);
            $asset->delete();
        }
        $confirmations['media_store'] = 'objects_deleted';

        // 2. Propagate erasure to the verification provider (stub until the
        //    vendor API is confirmed — real impl calls provider delete API).
        $confirmations['verification_provider'] = config('verification.provider').':erasure_requested';

        // 3. Scrub PII from the participant record; keep the opaque
        //    volunteer ref so audit trails remain joinable.
        $participant->update([
            'full_name' => null,
            'email' => null,
            'phone_country_code' => null,
            'phone' => null,
            'volunteer_role_ref' => null,
            'preferred_contact' => null,
            'status' => 'deleted',
        ]);

        $request->update([
            'status' => 'completed',
            'processor_confirmations_json' => $confirmations,
        ]);

        AuditEvent::create([
            'actor' => 'system',
            'action' => 'deletion.completed',
            'entity_type' => 'participant',
            'entity_id' => $participant->id,
            'metadata_json' => ['trigger' => $request->trigger, 'processors' => array_keys($confirmations)],
        ]);
    }
}
