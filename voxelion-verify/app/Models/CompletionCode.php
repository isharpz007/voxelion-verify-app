<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $code_hash
 * @property string $session_id
 * @property string $participant_id
 * @property Carbon $issued_at
 * @property Carbon $expires_at
 * @property Carbon|null $consumed_at
 * @property string|null $consumed_by_task
 * @property-read Participant $participant
 * @property-read VerificationSession $session
 * @property-read RewardSubmission|null $rewardSubmission
 */
class CompletionCode extends Model
{
    use HasUuids;

    protected $fillable = [
        'code_hash', 'session_id', 'participant_id',
        'issued_at', 'expires_at', 'consumed_at', 'consumed_by_task',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Participant, $this> */
    public function participant(): BelongsTo
    {
        return $this->belongsTo(Participant::class);
    }

    /** @return BelongsTo<VerificationSession, $this> */
    public function session(): BelongsTo
    {
        return $this->belongsTo(VerificationSession::class, 'session_id');
    }

    /** @return HasOne<RewardSubmission, $this> */
    public function rewardSubmission(): HasOne
    {
        return $this->hasOne(RewardSubmission::class, 'code_id');
    }
}
