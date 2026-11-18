<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $participant_id
 * @property string $trigger
 * @property string $status
 * @property array|null $processor_confirmations_json
 * @property-read Participant $participant
 */
class DeletionRequest extends Model
{
    use HasUuids;

    protected $fillable = ['participant_id', 'trigger', 'status', 'processor_confirmations_json'];

    protected function casts(): array
    {
        return ['processor_confirmations_json' => 'array'];
    }

    /** @return BelongsTo<Participant, $this> */
    public function participant(): BelongsTo
    {
        return $this->belongsTo(Participant::class);
    }
}
