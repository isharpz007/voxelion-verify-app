<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $session_id
 * @property string|null $challenge
 * @property array|null $liveness_response_json
 * @property float|null $similarity_score
 * @property string $outcome
 * @property Carbon|null $completed_at
 * @property-read VerificationSession $session
 */
class VerificationResult extends Model
{
    use HasUuids;

    protected $fillable = [
        'session_id', 'challenge', 'liveness_response_json',
        'similarity_score', 'outcome', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'liveness_response_json' => 'array',
            'similarity_score' => 'float',
            'completed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<VerificationSession, $this> */
    public function session(): BelongsTo
    {
        return $this->belongsTo(VerificationSession::class, 'session_id');
    }
}
