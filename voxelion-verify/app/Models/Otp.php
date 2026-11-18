<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A one-time password issued to a participant over one delivery channel.
 * Only a hash of the code is stored — the plaintext never touches the DB,
 * logs, or API responses.
 *
 * @property string $id
 * @property string $participant_id
 * @property string $channel
 * @property string $purpose
 * @property string $code_hash
 * @property int $attempts
 * @property Carbon $expires_at
 * @property Carbon|null $verified_at
 * @property Carbon|null $invalidated_at
 */
class Otp extends Model
{
    use HasUuids;

    protected $fillable = [
        'participant_id', 'channel', 'purpose', 'code_hash',
        'attempts', 'expires_at', 'verified_at', 'invalidated_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'verified_at' => 'datetime',
            'invalidated_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Participant, $this> */
    public function participant(): BelongsTo
    {
        return $this->belongsTo(Participant::class);
    }
}
