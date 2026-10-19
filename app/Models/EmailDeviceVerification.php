<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;

/**
 * Device-scoped email verification trust for public participants.
 *
 * Only RegistrationVerificationService writes this model (W5). The
 * raw device token lives in the HttpOnly `wdems_email_trust`
 * cookie, app-wide scope; only its SHA-256 hash is stored here.
 *
 * Trust is per (email, device): verifying family@example.com on
 * this browser skips OTP for family@example.com on this browser
 * only. A different email, a different browser, or an expired row
 * all fall back to full OTP verification.
 *
 * @property int $id
 * @property string $email
 * @property string $device_token_hash
 * @property CarbonInterface $verified_at
 * @property CarbonInterface $expires_at
 * @property CarbonInterface $created_at
 * @property-read null $updated_at
 */
#[Hidden(['device_token_hash'])]
class EmailDeviceVerification extends Model
{
    use MassPrunable;

    protected $fillable = ['email', 'device_token_hash', 'verified_at', 'expires_at'];

    protected function casts(): array
    {
        return [
            'verified_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
        ];
    }

    /** @return Builder<static> */
    public function prunable(): Builder
    {
        return static::query()->where('expires_at', '<=', now());
    }
}
