<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $participant_id
 * @property string $consent_version
 * @property Carbon|null $checkbox_1_at
 * @property Carbon|null $checkbox_2_at
 * @property Carbon|null $declined_at
 * @property Carbon|null $withdrawn_at
 * @property string $disclosure_hash
 * @property-read Participant $participant
 */
class Consent extends Model
{
    protected $fillable = [
        'participant_id', 'consent_version', 'checkbox_1_at', 'checkbox_2_at',
        'declined_at', 'withdrawn_at', 'disclosure_hash',
    ];

    protected function casts(): array
    {
        return [
            'checkbox_1_at' => 'datetime',
            'checkbox_2_at' => 'datetime',
            'declined_at' => 'datetime',
            'withdrawn_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Participant, $this> */
    public function participant(): BelongsTo
    {
        return $this->belongsTo(Participant::class);
    }
}
