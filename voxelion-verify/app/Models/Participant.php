<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $op_volunteer_ref
 * @property string $task_id
 * @property string|null $submission_id
 * @property string|null $full_name
 * @property string|null $email
 * @property Carbon|null $email_verified_at
 * @property string|null $email_code_hash
 * @property Carbon|null $email_code_expires_at
 * @property string|null $phone_country_code
 * @property string|null $phone
 * @property string|null $volunteer_role_ref
 * @property string|null $preferred_contact
 * @property string $status
 * @property-read Collection<int, VerificationSession> $sessions
 * @property-read Consent|null $consent
 * @property-read Collection<int, MediaAsset> $mediaAssets
 * @property-read CompletionCode|null $completionCode
 */
class Participant extends Model
{
    use HasUuids;

    protected $fillable = [
        'op_volunteer_ref', 'task_id', 'submission_id',
        'full_name', 'email', 'email_verified_at', 'email_code_hash',
        'email_code_expires_at', 'phone_country_code', 'phone',
        'phone_code_hash', 'phone_code_expires_at', 'phone_verified_at',
        'volunteer_role_ref', 'preferred_contact', 'status',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'email_code_expires_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'phone_code_expires_at' => 'datetime',
        ];
    }

    /** @return HasMany<VerificationSession, $this> */
    public function sessions(): HasMany
    {
        return $this->hasMany(VerificationSession::class);
    }

    /** @return HasOne<Consent, $this> */
    public function consent(): HasOne
    {
        return $this->hasOne(Consent::class);
    }

    /** @return HasMany<MediaAsset, $this> */
    public function mediaAssets(): HasMany
    {
        return $this->hasMany(MediaAsset::class);
    }

    /** @return HasOne<CompletionCode, $this> */
    public function completionCode(): HasOne
    {
        return $this->hasOne(CompletionCode::class);
    }

    /** @return HasMany<RewardSubmission, $this> */
    public function rewardSubmissions(): HasMany
    {
        return $this->hasMany(RewardSubmission::class);
    }
}
