<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $participant_id
 * @property string $kind
 * @property bool $accepted
 * @property string $storage_key
 * @property array|null $quality_check_json
 * @property Carbon $created_at
 * @property-read Participant $participant
 */
class MediaAsset extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    protected $fillable = ['participant_id', 'kind', 'accepted', 'storage_key', 'quality_check_json'];

    protected function casts(): array
    {
        return ['quality_check_json' => 'array', 'accepted' => 'boolean'];
    }

    /** @return BelongsTo<Participant, $this> */
    public function participant(): BelongsTo
    {
        return $this->belongsTo(Participant::class);
    }
}
