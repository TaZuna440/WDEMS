<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pending public intake. Only RegistrationVerificationService writes this model.
 * The bearer token lives in an encrypted HttpOnly browser cookie, never in URLs.
 *
 * @property int $id
 * @property int $event_id
 * @property string $token_hash
 * @property array<string, mixed>|null $payload
 * @property bool $email_required
 * @property bool $phone_required
 * @property CarbonInterface $expires_at
 * @property CarbonInterface|null $finalized_at
 * @property CarbonInterface|null $code_acknowledged_at
 * @property int|null $registration_id
 * @property string|null $email_code_hash
 * @property string|null $phone_code_hash
 * @property int $email_attempts
 * @property int $phone_attempts
 * @property CarbonInterface|null $email_issued_at
 * @property CarbonInterface|null $phone_issued_at
 * @property CarbonInterface|null $email_expires_at
 * @property CarbonInterface|null $phone_expires_at
 * @property CarbonInterface|null $email_verified_at
 * @property CarbonInterface|null $phone_verified_at
 * @property bool $email_delivery_failed
 * @property CarbonInterface $created_at
 * @property-read Event $event
 */
#[Hidden(['token_hash', 'payload', 'email_code_hash', 'phone_code_hash'])]
class RegistrationVerificationChallenge extends Model
{
    use MassPrunable;

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'payload' => 'encrypted:array',
            'email_required' => 'boolean',
            'phone_required' => 'boolean',
            'email_delivery_failed' => 'boolean',
            'email_attempts' => 'integer',
            'phone_attempts' => 'integer',
            'expires_at' => 'immutable_datetime',
            'finalized_at' => 'immutable_datetime',
            'code_acknowledged_at' => 'immutable_datetime',
            'email_issued_at' => 'immutable_datetime',
            'phone_issued_at' => 'immutable_datetime',
            'email_expires_at' => 'immutable_datetime',
            'phone_expires_at' => 'immutable_datetime',
            'email_verified_at' => 'immutable_datetime',
            'phone_verified_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /** @return Builder<static> */
    public function prunable(): Builder
    {
        return static::query()->where('expires_at', '<=', now());
    }
}
