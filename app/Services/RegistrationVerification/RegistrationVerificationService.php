<?php

namespace App\Services\RegistrationVerification;

use App\Enums\EventStatus;
use App\Enums\RegistrationStatus;
use App\Http\Requests\PublicRegistrationRequest;
use App\Mail\RegistrationVerificationOtpMail;
use App\Models\Event;
use App\Models\Participant;
use App\Models\Registration;
use App\Models\RegistrationFieldResponse;
use App\Models\RegistrationVerificationChallenge as Challenge;
use App\Models\User;
use App\Services\Otp\OtpCode;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

/**
 * Owns public contact verification and atomic intake. Attendance callers keep
 * their existing Participant::resolveFrom contract and never enter this flow.
 */
class RegistrationVerificationService
{
    public const CODE_TTL_SECONDS = 600;

    public const RESEND_COOLDOWN_SECONDS = 60;

    public const MAX_ATTEMPTS = 5;

    public const CHALLENGE_TTL_HOURS = 24;

    /** @return non-empty-string */
    public static function cookieName(Event $event): string
    {
        return 'wdems_registration_'.$event->id;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{token: string, created: bool}
     */
    public function begin(Event $event, #[\SensitiveParameter] array $payload, #[\SensitiveParameter] ?string $token): array
    {
        return DB::transaction(function () use ($event, $payload, $token): array {
            $event = Event::query()->lockForUpdate()->findOrFail($event->id);
            $this->assertOpen($event);
            $payload = PublicRegistrationRequest::validatePending($event, $payload);
            $existing = $this->findChallenge($event, $token, true);

            if ($existing !== null && $this->challengeState($existing, $event) === 'pending'
                && $existing->email_attempts < self::MAX_ATTEMPTS
                && $existing->phone_attempts < self::MAX_ATTEMPTS
                && $existing->payload === $payload) {
                return ['token' => $token, 'created' => false];
            }

            if ($existing !== null && $existing->finalized_at === null) {
                $existing->forceFill([
                    'expires_at' => now(), 'payload' => null,
                    'email_code_hash' => null, 'phone_code_hash' => null,
                ])->save();
            }

            $token = bin2hex(random_bytes(32));
            Challenge::forceCreate([
                'event_id' => $event->id,
                'token_hash' => hash('sha256', $token),
                'payload' => $payload,
                'email_required' => $event->isCommonFieldRequired('email'),
                'phone_required' => $event->isCommonFieldRequired('contact_number'),
                'expires_at' => now()->addHours(self::CHALLENGE_TTL_HOURS),
            ]);

            return ['token' => $token, 'created' => true];
        }, 3);
    }

    public function findChallenge(Event $event, #[\SensitiveParameter] ?string $token, bool $lock = false): ?Challenge
    {
        if ($token === null || preg_match('/\A[a-f0-9]{64}\z/', $token) !== 1) {
            return null;
        }

        return Challenge::query()->where('event_id', $event->id)
            ->where('token_hash', hash('sha256', $token))
            ->when($lock, fn ($query) => $query->lockForUpdate())->first();
    }

    /** @return array<string, mixed> */
    public function publicState(Event $event, #[\SensitiveParameter] ?string $token): array
    {
        $challenge = $this->findChallenge($event, $token);
        if ($challenge === null) {
            return ['status' => 'missing'];
        }

        $status = $this->challengeState($challenge, $event);
        if ($status !== 'pending') {
            return ['status' => $status];
        }

        return [
            'status' => 'pending',
            'expires_at' => $challenge->expires_at->toIso8601String(),
            'email' => $this->channelState($challenge, 'email'),
            'phone' => $this->channelState($challenge, 'phone'),
            'ready' => $this->allVerified($challenge),
        ];
    }

    /** Send synchronously through the existing Resend transport; never the log mailer. */
    public function sendEmail(Event $event, #[\SensitiveParameter] ?string $token): ?string
    {
        // No automatic transaction retry around external mail delivery.
        return DB::transaction(function () use ($event, $token): ?string {
            [$event, $challenge] = $this->lockPending($event, $token);
            if ($error = $this->issuanceError($challenge, 'email')) {
                return $error;
            }

            $code = $this->issue($challenge, 'email');
            try {
                Mail::mailer('resend')->to($challenge->payload['email'])->send(
                    new RegistrationVerificationOtpMail($code, $event->event_name),
                );
                $challenge->forceFill(['email_delivery_failed' => false])->save();
            } catch (\Throwable) {
                // Transport messages may include contact values or code content.
                // Do not log or report the exception. Retain cooldown, invalidate code.
                $challenge->forceFill([
                    'email_code_hash' => null,
                    'email_expires_at' => null,
                    'email_delivery_failed' => true,
                ])->save();

                return 'We could not send your email code. Wait 60 seconds and try again, or contact the organizer.';
            }

            return null;
        });
    }

    /** Only the authorized action receives the plaintext code, once. */
    public function generatePhone(Challenge $challenge, User $actor): string
    {
        return DB::transaction(function () use ($challenge, $actor): string {
            $event = Event::query()->lockForUpdate()->findOrFail($challenge->event_id);
            $this->authorizeStaff($event, $actor);
            $challenge = Challenge::query()->where('event_id', $event->id)
                ->lockForUpdate()->findOrFail($challenge->id);
            $this->assertPending($challenge, $event);

            if ($error = $this->issuanceError($challenge, 'phone')) {
                throw ValidationException::withMessages(['phone' => $error]);
            }

            return $this->issue($challenge, 'phone');
        }, 3);
    }

    public function authorizeStaff(Event $event, User $actor): void
    {
        abort_unless($actor->isAdmin() || ($actor->role === 'staff'
            && (int) $event->created_by === (int) $actor->id), 403);
    }

    public function verify(Event $event, #[\SensitiveParameter] ?string $token, string $channel, #[\SensitiveParameter] string $code): ?string
    {
        abort_unless(in_array($channel, ['email', 'phone'], true), 404);

        return DB::transaction(function () use ($event, $token, $channel, $code): ?string {
            [, $challenge] = $this->lockPending($event, $token);
            if (! $challenge->getAttribute("{$channel}_required")) {
                return 'This contact does not require verification.';
            }
            if ($challenge->getAttribute("{$channel}_verified_at") !== null) {
                return 'This contact is already verified. The code cannot be used again.';
            }
            if ($challenge->getAttribute("{$channel}_attempts") >= self::MAX_ATTEMPTS) {
                return 'Too many incorrect attempts. Start a new registration.';
            }
            if ($challenge->getAttribute("{$channel}_code_hash") === null) {
                return 'No active code. Request an email code or wait for the organizer to provide your phone code.';
            }
            if ($challenge->getAttribute("{$channel}_expires_at") === null
                || now()->greaterThanOrEqualTo($challenge->getAttribute("{$channel}_expires_at"))) {
                return 'The code has expired. Request a new code.';
            }
            if (preg_match('/\A[0-9]{6}\z/', $code) !== 1
                || ! OtpCode::matches($code, $challenge->getAttribute("{$channel}_code_hash"))) {
                $attempts = $challenge->getAttribute("{$channel}_attempts") + 1;
                $challenge->forceFill(["{$channel}_attempts" => $attempts])->save();

                // Returning commits the failed attempt; throwing here would roll it back.
                return $attempts >= self::MAX_ATTEMPTS
                    ? 'Too many incorrect attempts. Start a new registration.'
                    : 'The verification code is incorrect.';
            }

            $challenge->forceFill([
                "{$channel}_verified_at" => now(),
                "{$channel}_code_hash" => null,
                "{$channel}_expires_at" => null,
            ])->save();

            return null;
        }, 3);
    }

    /**
     * Exactly one transaction owns the Participant, Registration, custom answers,
     * and finalized marker. Unique constraints also arbitrate other intake paths.
     */
    public function finalize(Event $event, #[\SensitiveParameter] ?string $token): void
    {
        for ($attempt = 0; $attempt < 3; $attempt++) {
            try {
                DB::transaction(function () use ($event, $token): void {
                    $event = Event::query()->lockForUpdate()->findOrFail($event->id);
                    $challenge = $this->findChallenge($event, $token, true);
                    $state = $challenge === null ? 'missing' : $this->challengeState($challenge, $event);
                    if ($state === 'finalized') {
                        return; // Retry after a lost response cannot recreate a deleted registration.
                    }
                    $this->assertPending($challenge, $event);
                    if (! $this->allVerified($challenge)) {
                        throw ValidationException::withMessages(['verification' => 'Verify every required contact before completing registration.']);
                    }

                    $payload = PublicRegistrationRequest::validatePending($event, $challenge->payload ?? []);
                    $email = $challenge->email_required ? ($payload['email'] ?? null) : null;
                    $phone = $challenge->phone_required ? ($payload['contact_number'] ?? null) : null;
                    $normalized = Participant::normalizeContactNumber($phone);
                    if ($email === null && $normalized === null) {
                        $this->identityConflict();
                    }

                    $matches = Participant::query()->where(function ($query) use ($email, $normalized) {
                        $query->whereRaw('1 = 0');
                        if ($email !== null) {
                            $query->orWhere('email', $email);
                        }
                        if ($normalized !== null) {
                            $query->orWhere('contact_number_normalized', $normalized);
                        }
                    })->orderBy('id')->lockForUpdate()->get();

                    if ($matches->count() > 1) {
                        $this->identityConflict();
                    }

                    // Preserve first-write-wins. Never fill existing identities from intake.
                    $participant = $matches->first() ?? Participant::create([
                        'first_name' => $payload['first_name'],
                        'last_name' => $payload['last_name'],
                        'age' => $payload['age'],
                        'address' => $payload['address'] ?? null,
                        'email' => $email,
                        'contact_number' => $phone,
                    ]);

                    if (Registration::query()->where('event_id', $event->id)
                        ->where('participant_id', $participant->id)->exists()) {
                        $this->identityConflict();
                    }

                    $registration = Registration::create([
                        'event_id' => $event->id,
                        'participant_id' => $participant->id,
                        'registration_date' => now(),
                        'registration_status' => RegistrationStatus::Confirmed,
                        'source' => 'form',
                        'registered_at' => now(),
                        'submitted_email' => $payload['email'] ?? null,
                        'submitted_contact_number' => $payload['contact_number'] ?? null,
                    ]);

                    foreach ($payload['responses'] ?? [] as $fieldId => $value) {
                        RegistrationFieldResponse::create([
                            'registration_id' => $registration->id,
                            'registration_field_id' => (int) $fieldId,
                            'value' => is_array($value) ? json_encode($value, JSON_THROW_ON_ERROR) : (string) $value,
                        ]);
                    }

                    $challenge->forceFill([
                        'registration_id' => $registration->id,
                        'finalized_at' => now(),
                        'payload' => null,
                        'email_code_hash' => null,
                        'phone_code_hash' => null,
                    ])->save();
                }, 3);

                return;
            } catch (UniqueConstraintViolationException) {
                // A different event or walk-in may race global identity creation.
                // Roll back all writes and resolve again from current persisted state.
                if ($attempt === 2) {
                    $this->identityConflict();
                }
            }
        }
    }

    /** @return array{Event, Challenge} */
    private function lockPending(Event $event, #[\SensitiveParameter] ?string $token): array
    {
        $event = Event::query()->lockForUpdate()->findOrFail($event->id);
        $challenge = $this->findChallenge($event, $token, true);
        $this->assertPending($challenge, $event);

        return [$event, $challenge];
    }

    private function assertPending(?Challenge $challenge, Event $event): void
    {
        if ($challenge === null || $this->challengeState($challenge, $event) !== 'pending') {
            throw ValidationException::withMessages([
                'verification' => 'This verification is unavailable, expired, or already completed. Refresh the page or start a new registration.',
            ]);
        }
    }

    public function challengeState(Challenge $challenge, Event $event): string
    {
        if ((int) $challenge->event_id !== (int) $event->id) {
            return 'missing';
        }
        if (now()->greaterThanOrEqualTo($challenge->expires_at)
            || now()->greaterThanOrEqualTo($challenge->created_at->copy()->addHours(self::CHALLENGE_TTL_HOURS))) {
            return 'expired';
        }
        if ($challenge->finalized_at !== null) {
            return 'finalized';
        }
        if (! $this->isOpen($event)) {
            return 'closed';
        }
        if ($challenge->email_required !== $event->isCommonFieldRequired('email')
            || $challenge->phone_required !== $event->isCommonFieldRequired('contact_number')
            || (! $challenge->email_required && ! $challenge->phone_required)) {
            return 'changed';
        }

        return 'pending';
    }

    /** @return array<string, mixed> */
    public function channelState(Challenge $challenge, string $channel): array
    {
        $issuedAt = $challenge->getAttribute("{$channel}_issued_at");
        $expiresAt = $challenge->getAttribute("{$channel}_expires_at");
        $retryAt = $issuedAt?->addSeconds(self::RESEND_COOLDOWN_SECONDS);
        $status = match (true) {
            ! $challenge->getAttribute("{$channel}_required") => 'optional',
            $challenge->getAttribute("{$channel}_verified_at") !== null => 'verified',
            $challenge->getAttribute("{$channel}_attempts") >= self::MAX_ATTEMPTS => 'locked',
            $channel === 'email' && $challenge->email_delivery_failed => 'delivery_failed',
            $issuedAt === null => 'waiting',
            $expiresAt === null || now()->greaterThanOrEqualTo($expiresAt) => 'expired',
            default => 'sent',
        };

        return [
            'status' => $status,
            'attempts_remaining' => max(0, self::MAX_ATTEMPTS - $challenge->getAttribute("{$channel}_attempts")),
            'retry_at' => $retryAt?->toIso8601String(),
            'expires_at' => $expiresAt?->toIso8601String(),
        ];
    }

    private function issuanceError(Challenge $challenge, string $channel): ?string
    {
        if (! $challenge->getAttribute("{$channel}_required")) {
            return 'This contact does not require verification.';
        }
        if ($challenge->getAttribute("{$channel}_verified_at") !== null) {
            return 'This contact is already verified.';
        }
        if ($challenge->getAttribute("{$channel}_attempts") >= self::MAX_ATTEMPTS) {
            return 'Too many incorrect attempts. Start a new registration.';
        }
        $issuedAt = $challenge->getAttribute("{$channel}_issued_at");
        if ($issuedAt !== null && now()->lessThan($issuedAt->addSeconds(self::RESEND_COOLDOWN_SECONDS))) {
            return 'Please wait 60 seconds between codes.';
        }

        return null;
    }

    private function issue(Challenge $challenge, string $channel): string
    {
        $oldHash = $challenge->getAttribute("{$channel}_code_hash");
        do {
            $code = OtpCode::generate();
        } while ($oldHash !== null && OtpCode::matches($code, $oldHash));

        $challenge->forceFill([
            "{$channel}_code_hash" => OtpCode::hash($code),
            "{$channel}_issued_at" => now(),
            "{$channel}_expires_at" => now()->addSeconds(self::CODE_TTL_SECONDS),
        ])->save();

        // Attempt counts are cumulative per channel, including across resends.
        return $code;
    }

    private function allVerified(Challenge $challenge): bool
    {
        return ($challenge->email_required || $challenge->phone_required)
            && (! $challenge->email_required || $challenge->email_verified_at !== null)
            && (! $challenge->phone_required || $challenge->phone_verified_at !== null);
    }

    private function isOpen(Event $event): bool
    {
        return $event->getAttribute('status') === EventStatus::RegistrationOpen
            && ($event->registration_end === null || now()->lessThan($event->registration_end));
    }

    private function assertOpen(Event $event): void
    {
        if (! $this->isOpen($event)) {
            throw ValidationException::withMessages(['identity' => 'Registration for this event is closed.']);
        }
    }

    private function identityConflict(): never
    {
        throw ValidationException::withMessages([
            'verification' => 'We could not complete this registration. Please contact the event organizer for assistance.',
        ]);
    }
}
