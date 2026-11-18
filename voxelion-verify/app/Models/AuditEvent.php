<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $actor
 * @property string $action
 * @property string $entity_type
 * @property string $entity_id
 * @property array|null $metadata_json
 * @property Carbon $created_at
 */
class AuditEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['actor', 'action', 'entity_type', 'entity_id', 'metadata_json'];

    protected function casts(): array
    {
        return ['metadata_json' => 'array'];
    }
}
