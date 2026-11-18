<?php

namespace App\Models;

use App\Enums\SessionState;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $participant_id
 * @property SessionState $state
 * @property string $handoff_jti
 * @property string|null $current_challenge
 * @property int $photo_attempts
 * @property int $liveness_attempts
 * @property Carbon $expires_at
 * @property-read Participant $participant
 * @property-read VerificationResult|null $result
 * @property-read CompletionCode|null $completionCode
 */
class VerificationSession extends Model
{
    use HasUuids;

    protected $fillable = [
        'participant_id', 'state', 'handoff_jti', 'current_challenge',
        'photo_attempts', 'liveness_attempts', 'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'state' => SessionState::class,
            'expires_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Participant, $this> */
    public function participant(): BelongsTo
    {
        return $this->belongsTo(Participant::class);
    }

    /** @return HasOne<VerificationResult, $this> */
    public function result(): HasOne
    {
        return $this->hasOne(VerificationResult::class, 'session_id');
    }

    /** @return HasOne<CompletionCode, $this> */
    public function completionCode(): HasOne
    {
        return $this->hasOne(CompletionCode::class, 'session_id');
    }
}
