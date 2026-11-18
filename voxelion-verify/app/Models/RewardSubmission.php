<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $task_id
 * @property string $participant_id
 * @property string $code_id
 * @property string|null $screenshot_s3_key
 * @property string $review_state
 * @property string|null $reviewer_id
 * @property string|null $rejection_reason
 * @property Carbon|null $decision_at
 * @property Carbon $created_at
 * @property-read Participant $participant
 * @property-read CompletionCode|null $code
 */
class RewardSubmission extends Model
{
    use HasUuids;

    protected $fillable = [
        'task_id', 'participant_id', 'code_id', 'screenshot_s3_key',
        'review_state', 'reviewer_id', 'rejection_reason', 'decision_at',
    ];

    protected function casts(): array
    {
        return ['decision_at' => 'datetime'];
    }

    /** @return BelongsTo<Participant, $this> */
    public function participant(): BelongsTo
    {
        return $this->belongsTo(Participant::class);
    }

    /** @return BelongsTo<CompletionCode, $this> */
    public function code(): BelongsTo
    {
        return $this->belongsTo(CompletionCode::class, 'code_id');
    }
}
