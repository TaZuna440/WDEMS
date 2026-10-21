<?php

namespace App\Services\RegistrationVerification;

use App\Enums\EventStatus;
use App\Enums\RegistrationStatus;
use App\Http\Requests\PublicRegistrationRequest;
use App\Mail\RegistrationConfirmationMail;
use App\Mail\RegistrationVerificationOtpMail;
use App\Models\EmailDeviceVerification;
use App\Models\Event;
use App\Models\Participant;
use App\Models\Registration;
use App\Models\RegistrationFieldResponse;
use App\Models\RegistrationVerificationChallenge as Challenge;
use App\Services\Otp\OtpCode;
use App\Services\Participants\DuplicateReview;
use App\Support\RegistrationLegalText;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Kreait\Firebase\Contract\Auth;
use RuntimeException;

/**
 * Owns public contact verification and atomic intake. Attendance callers keep
 * their existing Participant::resolveFrom contract and never enter this flow.
 */
class RegistrationVerificationService
{
    public const CODE_TTL_SECONDS = 600;

    public const RESEND_COOLDOWN_SECONDS = 100;

    /**
     * Device trust (W5.3): a device that verified an email address skips
     * the OTP for that address on later registrations. Trust is per
     * (email, device) pair, recorded server-side; the cookie only carries
     * a random device identifier (HttpOnly, Secure, SameSite=Lax).
     */
    public const DEVICE_TRUST_COOKIE = 'wdems_email_trust';

    public const DEVICE_TRUST_TTL_DAYS = 7;

    public const MAX_ATTEMPTS = 5;

    public const CHALLENGE_TTL_HOURS = 24;

    /**
     * Alphabet for the per-registration attendance code.
     *
     * Uppercased variant of EventController::SLUG_ALPHABET. Same
     * exclusions — no I, L, O, 0, 1 — so the code reads cleanly over
     * a phone line. Uppercase so it is visually distinct from the
     * lowercase event slug at /r/{slug}.
     *
     * Duplicated from EventController deliberately: extracting the
     * constant would touch a file unrelated to this feature. If the
     * alphabet ever changes, both sites must be updated together.
     * See implementation-notes.md §3 for the slug constant record.
     */
    public const ATTENDANCE_CODE_ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    /**
     * Characters drawn from ATTENDANCE_CODE_ALPHABET before the dash
     * is inserted. Eight characters gives 31^8 ≈ 8.5×10^11 possible
     * codes per event — the same collision space as the slug.
     */
    public const ATTENDANCE_CODE_LENGTH = 8;

    /**
     * Collision-retry ceiling, matching EventController's
     * SLUG_MAX_ATTEMPTS. Collisions at this scale are effectively
     * impossible; the loop makes the failure mode explicit.
     */
    public const ATTENDANCE_CODE_MAX_ATTEMPTS = 3;

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

            // Reject early: a completed registration for this event
            // whose participant matches the submitted email or
            // normalized phone is a duplicate. Runs regardless of
            // whether the contact is required — an optional contact
            // that already belongs to a registration still counts.
            $this->assertNoDuplicateRegistration($event, $payload);
            if (! ($payload['similarity_acknowledged'] ?? false) && app(DuplicateReview::class)->hasSimilar($event, $payload)) {
                throw ValidationException::withMessages(['similarity_acknowledged' => 'Review the similar-registration warning before continuing.']);
            }

            if ($existing !== null && $existing->finalized_at === null) {
                $existing->forceFill([
                    'expires_at' => now(), 'payload' => null,
                    'email_code_hash' => null, 'phone_code_hash' => null,
                ])->save();
            }

            $token = bin2hex(random_bytes(32));
            $emailRequired = $event->isCommonFieldRequired('email');
            $phoneRequired = ! $event->usesEmailOnlyRegistration() && $event->isCommonFieldRequired('contact_number');
            Challenge::forceCreate([
                'event_id' => $event->id,
                'token_hash' => hash('sha256', $token),
                'payload' => $payload,
                'email_required' => $emailRequired,
                'phone_required' => $phoneRequired,
                // Stricter channel choice (W5): a single required channel
                // is chosen automatically — no choice screen. Both
                // required → the participant picks one via
                // chooseChannel(); null until then.
                'chosen_channel' => $emailRequired !== $phoneRequired
                    ? ($emailRequired ? 'email' : 'phone')
                    : null,
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

    /**
     * Public-facing state of a challenge.
     *
     * The 'finalized' state carries the registration's attendance_code
     * until the participant acknowledges saving it, so the verification
     * page can display it and the participant can save it for event day.
     * The code is a bearer lookup key, not a
     * secret — showing it to the participant who owns the challenge is
     * the point.
     *
     * The code may be null if the registration was deleted after
     * finalization (the challenge's registration_id FK is
     * nullOnDelete). The participant can still acknowledge the
     * finalized challenge; no code is exposed in that case.
     *
     * @return array<string, mixed>
     */
    /**
     * Record the participant's chosen verification channel.
     *
     * Only reachable when both email and phone are required — a single
     * required channel is chosen automatically at begin(). The choice
     * is final: verify()/sendEmail()/verifyPhoneWithFirebase() reject
     * the non-chosen channel afterwards.
     *
     * @return string|null Error message, or null on success.
     */
    public function chooseChannel(Event $event, #[\SensitiveParameter] ?string $token, string $channel): ?string
    {
        if (! in_array($channel, ['email', 'phone'], true)) {
            return 'Choose email or phone verification.';
        }

        return DB::transaction(function () use ($event, $token, $channel): ?string {
            [, $challenge] = $this->lockPending($event, $token);
            if (! $challenge->getAttribute("{$channel}_required")) {
                return 'This contact does not require verification.';
            }
            if ($challenge->chosen_channel !== null) {
                return 'A verification channel is already chosen.';
            }
            if (! ($challenge->email_required && $challenge->phone_required)) {
                return 'No channel choice is needed for this registration.';
            }

            $challenge->forceFill(['chosen_channel' => $channel])->save();

            return null;
        }, 3);
    }

    public function publicState(Event $event, #[\SensitiveParameter] ?string $token): array
    {
        $challenge = $this->findChallenge($event, $token);
        if ($challenge === null) {
            return ['status' => 'missing'];
        }

        $status = $this->challengeState($challenge, $event);

        if ($status === 'finalized') {
            if ($challenge->code_acknowledged_at !== null) {
                return ['status' => 'acknowledged'];
            }

            $registration = $challenge->registration_id !== null
                ? Registration::find($challenge->registration_id)
                : null;

            return [
                'status' => 'finalized',
                'is_held' => $registration?->isHeld() ?? false,
                'is_inactive' => $registration !== null && ! $registration->isActive(),
                'attendance_code' => $registration?->isActive() ? $registration->attendance_code : null,
            ];
        }

        if ($status !== 'pending') {
            return ['status' => $status];
        }

        return [
            'status' => 'pending',
            'expires_at' => $challenge->expires_at->toIso8601String(),
            'submitted_phone' => $challenge->payload['contact_number'] ?? null,
            'email' => $this->channelState($challenge, 'email'),
            'phone' => $this->channelState($challenge, 'phone'),
            'chosen_channel' => $challenge->chosen_channel,
            'needs_channel_choice' => $challenge->chosen_channel === null
                && $challenge->email_required && $challenge->phone_required
                && $challenge->email_verified_at === null
                && $challenge->phone_verified_at === null,
            'ready' => $this->allVerified($challenge),
        ];
    }

    /**
     * Persist the participant's acknowledgement while holding the challenge
     * row lock. Repeated requests with the original cookie are idempotent.
     */
    public function acknowledgeCode(Event $event, #[\SensitiveParameter] ?string $token): void
    {
        DB::transaction(function () use ($event, $token): void {
            $challenge = $this->findChallenge($event, $token, true);

            if ($challenge === null || $this->challengeState($challenge, $event) !== 'finalized') {
                throw ValidationException::withMessages([
                    'verification' => 'This registration confirmation is unavailable or expired. Start a new registration if you still need help.',
                ]);
            }

            if ($challenge->code_acknowledged_at === null) {
                $challenge->forceFill(['code_acknowledged_at' => now()])->save();
            }
        }, 3);
    }

    /** Send synchronously through the existing Resend transport; never the log mailer. */
    /**
     * Read the device identifier from the trust cookie, generating one
     * when absent. The raw token never touches the database — only its
     * SHA-256 hash is stored.
     */
    public static function deviceToken(Request $request): string
    {
        $token = $request->cookie(self::DEVICE_TRUST_COOKIE);

        return is_string($token) && $token !== '' ? $token : Str::random(64);
    }

    public function sendEmail(
        Event $event,
        #[\SensitiveParameter] ?string $token,
        #[\SensitiveParameter] ?string $deviceToken = null,
        #[\SensitiveParameter] ?string $recaptchaToken = null,
    ): ?string {
        if ($event->usesEmailOnlyRegistration() && ($error = app(Recaptcha::class)->error($recaptchaToken))) {
            return $error;
        }

        // No automatic transaction retry around external mail delivery.
        return DB::transaction(function () use ($event, $token, $deviceToken): ?string {
            [$event, $challenge] = $this->lockPending($event, $token);

            if ($event->usesEmailOnlyRegistration() && $this->emailLocked($challenge)) {
                return 'Too many attempts. Please wait 2 hours to try again.';
            }

            // Device trust: this device verified this email within the
            // trust window — skip the code, mark verified, and adopt
            // email as the chosen channel when no choice was made.
            // Respects the channel choice: no auto-verification when
            // the participant chose phone.
            $email = $challenge->payload['email'] ?? null;
            if ($deviceToken !== null
                && $challenge->getAttribute('email_required')
                && $challenge->chosen_channel !== 'phone'
                && $challenge->getAttribute('email_verified_at') === null
                && is_string($email) && $email !== ''
                && $this->hasEmailDeviceTrust($email, $deviceToken)) {
                $updates = ['email_verified_at' => now()];
                if ($challenge->chosen_channel === null) {
                    $updates['chosen_channel'] = 'email';
                }
                $challenge->forceFill($updates)->save();

                return null;
            }

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

                return 'We could not send your email code. Wait 100 seconds and try again, or contact the organizer.';
            }

            return null;
        });
    }

    public function verify(
        Event $event,
        #[\SensitiveParameter] ?string $token,
        string $channel,
        #[\SensitiveParameter] string $code,
        #[\SensitiveParameter] ?string $deviceToken = null,
    ): ?string {
        abort_unless($channel === 'email', 404);

        return DB::transaction(function () use ($event, $token, $channel, $code, $deviceToken): ?string {
            [, $challenge] = $this->lockPending($event, $token);
            if ($event->usesEmailOnlyRegistration() && $this->emailLocked($challenge)) {
                return 'Too many attempts. Please wait 2 hours to try again.';
            }
            if (! $challenge->getAttribute("{$channel}_required")) {
                return 'This contact does not require verification.';
            }
            if ($challenge->chosen_channel !== null && $challenge->chosen_channel !== $channel) {
                return 'You chose to verify by '.$challenge->chosen_channel.'.';
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

                if ($event->usesEmailOnlyRegistration()) {
                    $key = $this->emailAttemptKey($challenge);
                    $total = RateLimiter::hit($key, 7200);
                    if ($total >= self::MAX_ATTEMPTS) {
                        Cache::put($key.':locked', true, now()->addHours(2));

                        return 'Too many attempts. Please wait 2 hours to try again.';
                    }

                    return 'The verification code is incorrect. You have '.(self::MAX_ATTEMPTS - $total).' attempts left.';
                }

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

            // Email OTP succeeded on this device - remember it so later
            // registrations with the same address skip the code.
            if ($deviceToken !== null) {
                $email = $challenge->payload['email'] ?? null;
                if (is_string($email) && $email !== '') {
                    $this->recordEmailDeviceTrust($email, $deviceToken);
                }
            }

            return null;
        }, 3);
    }

    /**
     * A trust record is valid while its expiry is in the future.
     * Expired rows are pruned by the model's MassPrunable schedule.
     */
    private function hasEmailDeviceTrust(string $email, string $deviceToken): bool
    {
        return EmailDeviceVerification::where('email', $email)
            ->where('device_token_hash', hash('sha256', $deviceToken))
            ->where('expires_at', '>', now())
            ->exists();
    }

    private function recordEmailDeviceTrust(string $email, string $deviceToken): void
    {
        EmailDeviceVerification::updateOrCreate(
            [
                'email' => $email,
                'device_token_hash' => hash('sha256', $deviceToken),
            ],
            [
                'verified_at' => now(),
                'expires_at' => now()->addDays(self::DEVICE_TRUST_TTL_DAYS),
            ],
        );
    }

    /**
     * Exactly one transaction owns the Participant, Registration, custom answers,
     * and finalized marker. Unique constraints also arbitrate other intake paths.
     *
     * Also issues the per-registration attendance code. The code is only set on
     * 'form' registrations — walk-ins are created by AttendanceController with a
     * NULL attendance_code, because their presence is a fact of their encoding,
     * not a claim the system needs to verify.
     *
     * The confirmation email is dispatched via DB::afterCommit() so a transport
     * failure never rolls back the registration. The participant has the code
     * on the confirmation page immediately; the email is a durable second copy
     * for the case where they close the tab before saving it.
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
                    $this->assertNoDuplicateRegistration($event, $payload);
                    // The verified (chosen) channel is the identity
                    // contact. A required-but-unverified contact is a
                    // snapshot only — stored in submitted_* below, never
                    // used for identity.
                    if ($challenge->chosen_channel !== null) {
                        $chosen = $challenge->chosen_channel;
                        $email = $chosen === 'email' ? ($payload['email'] ?? null) : null;
                        $phone = $chosen === 'phone' ? ($payload['contact_number'] ?? null) : null;
                    } else {
                        // Legacy challenges (no choice made): both required
                        // contacts participate in identity, as before W5.2.
                        $email = $challenge->email_required ? ($payload['email'] ?? null) : null;
                        $phone = $challenge->phone_required ? ($payload['contact_number'] ?? null) : null;
                    }
                    $normalized = Participant::normalizeContactNumber($phone);
                    if ($email === null && $normalized === null) {
                        $this->identityConflict();
                    }

                    $matches = Participant::query()->matchingName('running',
                        $payload['first_name'], $payload['last_name'],
                        $payload['middle_name'] ?? null, $payload['suffix'] ?? null,
                    )->where(function ($query) use ($email, $normalized) {
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
                    $participant = $matches->first() ?? Participant::create(['pool' => 'running',
                        'first_name' => $payload['first_name'],
                        'last_name' => $payload['last_name'],
                        'middle_name' => $payload['middle_name'] ?? null,
                        'suffix' => $payload['suffix'] ?? null,
                        // Demographic write: prefer `birthday` (new
                        // shape, bounded to age 3-120 by
                        // PublicRegistrationRequest). Fall back to
                        // `age` for legacy payloads — pre-swap
                        // challenges, direct tooling, or tests that
                        // still POST `age`.
                        //
                        // Exactly one is guaranteed present by the
                        // request's withValidator() DOB check. If both
                        // are present, birthday wins on write; the
                        // accessor prefers it on read anyway, so
                        // writing both would leave stale age data on
                        // the row.
                        ...(isset($payload['birthday']) && $payload['birthday'] !== null && $payload['birthday'] !== ''
                            ? ['birthday' => $payload['birthday']]
                            : ['age' => (int) $payload['age']]),
                        'address' => $payload['address'] ?? null,
                        'email' => $email,
                        'contact_number' => $phone,
                    ]);

                    if (Registration::query()->where('event_id', $event->id)
                        ->where('participant_id', $participant->id)->exists()) {
                        $this->identityConflict();
                    }

                    $candidateMatch = (new DuplicateReview)->findCandidate($event, [
                        'first_name' => $payload['first_name'] ?? null,
                        'middle_name' => $payload['middle_name'] ?? null,
                        'last_name' => $payload['last_name'] ?? null,
                        'suffix' => $payload['suffix'] ?? null,
                        'email' => $email,
                        'contact_number' => $payload['contact_number'] ?? null,
                        'birthday' => $payload['birthday'] ?? null,
                    ]);

                    $isHeld = $candidateMatch !== null;
                    $status = $isHeld ? RegistrationStatus::Held : RegistrationStatus::Confirmed;
                    $heldReason = $isHeld ? implode(', ', $candidateMatch['reasons']) : null;
                    $heldCandidateId = $isHeld ? $candidateMatch['registration']->id : null;

                    $registration = Registration::create([
                        'event_id' => $event->id,
                        'participant_id' => $participant->id,
                        'registration_date' => now(),
                        'registration_status' => $status,
                        'held_reason' => $heldReason,
                        'held_candidate_registration_id' => $heldCandidateId,
                        'source' => 'form',
                        'registered_at' => now(),
                        'submitted_email' => $payload['email'] ?? null,
                        'submitted_contact_number' => $payload['contact_number'] ?? null,
                        'attendance_code' => $this->generateAttendanceCode($event),
                        // P5 compliance trail: the wizard's Step 1
                        // disclaimer + privacy consent were accepted to reach this point.
                        'consent_accepted_at' => now(),
                        'privacy_notice_version' => RegistrationLegalText::PRIVACY_NOTICE_VERSION,
                        'emergency_contact_name' => $event->usesEmailOnlyRegistration()
                            ? $payload['emergency_contact_first_name'].' '.$payload['emergency_contact_last_name']
                            : $payload['emergency_contact_name'],
                        'emergency_contact_first_name' => $payload['emergency_contact_first_name'] ?? null,
                        'tshirt_choice' => $payload['tshirt_choice'] ?? null,
                        'emergency_contact_last_name' => $payload['emergency_contact_last_name'] ?? null,
                        'emergency_contact_relationship_other' => $payload['emergency_contact_relationship_other'] ?? null,
                        'emergency_contact_relationship' => $payload['emergency_contact_relationship'],
                        'emergency_contact_number' => $payload['emergency_contact_number'],
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

                    // Capture the values the confirmation email needs.
                    // The closure runs after the outer transaction
                    // commits — so a transport failure cannot roll back
                    // the registration. A failure is swallowed: the
                    // participant has the code on the confirmation page,
                    // and the email is a durable second copy, not a
                    // required channel.
                    $submittedEmail = $registration->submitted_email;
                    $attendanceCode = $registration->attendance_code;

                    if (! $isHeld && $submittedEmail !== null && $attendanceCode !== null) {
                        DB::afterCommit(function () use ($submittedEmail, $attendanceCode, $event): void {
                            try {
                                Mail::mailer('resend')->to($submittedEmail)->send(
                                    new RegistrationConfirmationMail(
                                        $attendanceCode,
                                        $event->event_name,
                                    ),
                                );
                            } catch (\Throwable) {
                                // Transport messages may include contact values.
                                // Do not log or report the exception.
                            }
                        });
                    }
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

    /**
     * Build a unique attendance code for this event.
     *
     * Scoped to the event, not globally: the same code may exist on
     * registrations for two different events without confusion. The
     * workspace looks up by (event_id, attendance_code).
     *
     * Caller holds the event row lock (finalize() takes it at the
     * top of the transaction), so concurrent finalizations for the
     * same event are serialized. The unique index on
     * (event_id, attendance_code) is a defense-in-depth backstop.
     *
     * @throws RuntimeException When three consecutive candidates all collide.
     */
    private function generateAttendanceCode(Event $event): string
    {
        for ($attempt = 0; $attempt < self::ATTENDANCE_CODE_MAX_ATTEMPTS; $attempt++) {
            $candidate = $this->randomAttendanceCode();

            $exists = Registration::query()
                ->where('event_id', $event->id)
                ->where('attendance_code', $candidate)
                ->exists();

            if (! $exists) {
                return $candidate;
            }
        }

        throw new RuntimeException(
            'Could not generate a unique attendance code after '
            .self::ATTENDANCE_CODE_MAX_ATTEMPTS.' attempts.',
        );
    }

    /**
     * Draw ATTENDANCE_CODE_LENGTH characters from the alphabet and
     * insert a dash between the two groups of four. KM4XP2N7 → KM4X-P2N7.
     *
     * Uniqueness is checked by the caller, not here.
     */
    private function randomAttendanceCode(): string
    {
        $max = strlen(self::ATTENDANCE_CODE_ALPHABET) - 1;
        $raw = '';

        for ($i = 0; $i < self::ATTENDANCE_CODE_LENGTH; $i++) {
            $raw .= self::ATTENDANCE_CODE_ALPHABET[random_int(0, $max)];
        }

        return substr($raw, 0, 4).'-'.substr($raw, 4);
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

    /**
     * Reject early: a completed registration for this event whose
     * participant matches the submitted email or normalized phone.
     *
     * Runs regardless of whether the contact is required by the
     * event — an optional contact that already belongs to a
     * registration still counts as a duplicate.
     *
     * A match on a DIFFERENT event is not a duplicate. Participant
     * identity is cross-event; registration is per-event.
     *
     * @param  array<string, mixed>  $payload
     */
    /**
     * Reject a duplicate registration under the composite rule.
     *
     * A duplicate is (same normalized name + same contact) on the same
     * event. Different names sharing one email/phone are allowed — that
     * is the family-registration case the composite rule exists for.
     * Same name with a different contact is also allowed (name alone is
     * not identity).
     *
     * The message is deliberately non-revealing: it does not say whether
     * the email, the phone, or the name collided.
     */
    private function assertNoDuplicateRegistration(Event $event, #[\SensitiveParameter] array $payload): void
    {
        $exists = app(DuplicateReview::class)->findExact($event, $payload) !== null;

        if ($exists) {
            throw ValidationException::withMessages([
                'identity' => 'This registration cannot be started. If you believe this is an error, contact the event organizer.',
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
        // Existing challenges retain their original 24-hour lifetime after closure.
        // Cancelled/completed events still reject completion; new intake uses assertOpen.
        if (! $this->isOpen($event) && ! ($event->isRunningEvent() && in_array($event->status, [EventStatus::RegistrationClosed, EventStatus::Ongoing], true))) {
            return 'closed';
        }
        if ($challenge->email_required !== $event->isCommonFieldRequired('email')
            || $challenge->phone_required !== (! $event->usesEmailOnlyRegistration() && $event->isCommonFieldRequired('contact_number'))
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

        $remaining = max(0, self::MAX_ATTEMPTS - $challenge->getAttribute("{$channel}_attempts"));
        if ($channel === 'email' && $challenge->event?->usesEmailOnlyRegistration()) {
            $remaining = $this->emailLocked($challenge) ? 0 : min($remaining, max(0, self::MAX_ATTEMPTS - RateLimiter::attempts($this->emailAttemptKey($challenge))));
            if ($remaining === 0 && $status !== 'verified') {
                $status = 'locked';
            }
        }

        return [
            'status' => $status,
            'attempts_remaining' => $remaining,
            'retry_at' => $retryAt?->toIso8601String(),
            'expires_at' => $expiresAt?->toIso8601String(),
        ];
    }

    private function issuanceError(Challenge $challenge, string $channel): ?string
    {
        if (! $challenge->getAttribute("{$channel}_required")) {
            return 'This contact does not require verification.';
        }
        if ($challenge->chosen_channel !== null && $challenge->chosen_channel !== $channel) {
            return 'You chose to verify by '.$challenge->chosen_channel.'.';
        }
        if ($challenge->getAttribute("{$channel}_verified_at") !== null) {
            return 'This contact is already verified.';
        }
        if ($challenge->getAttribute("{$channel}_attempts") >= self::MAX_ATTEMPTS) {
            return 'Too many incorrect attempts. Start a new registration.';
        }
        $issuedAt = $challenge->getAttribute("{$channel}_issued_at");
        if ($issuedAt !== null && now()->lessThan($issuedAt->addSeconds(self::RESEND_COOLDOWN_SECONDS))) {
            return 'Please wait 100 seconds between codes.';
        }

        return null;
    }

    private function emailAttemptKey(Challenge $challenge): string
    {
        return 'registration-email-attempts:'.hash('sha256', mb_strtolower(trim($challenge->payload['email'] ?? '')));
    }

    private function emailLocked(Challenge $challenge): bool
    {
        return Cache::has($this->emailAttemptKey($challenge).':locked');
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
        // Stricter channel choice (W5): the participant verifies ONE
        // channel — their pick among the required ones. The chosen
        // channel's verification is what counts.
        $chosen = $challenge->chosen_channel;
        if ($chosen !== null) {
            return $challenge->getAttribute("{$chosen}_verified_at") !== null;
        }

        // Legacy challenges (created before W5.2, both required, no
        // choice made) keep the old every-required-channel rule so
        // in-flight verifications are not stranded.
        return ($challenge->email_required || $challenge->phone_required)
            && (! $challenge->email_required || $challenge->email_verified_at !== null)
            && (! $challenge->phone_required || $challenge->phone_verified_at !== null);
    }

    private function isOpen(Event $event): bool
    {
        return $event->acceptsPublicRegistration();
    }

    private function assertOpen(Event $event): void
    {
        if (! $this->isOpen($event)) {
            throw ValidationException::withMessages(['identity' => 'Registration for this event is closed.']);
        }
    }

    public function verifyPhoneWithFirebase(Event $event, #[\SensitiveParameter] ?string $token, #[\SensitiveParameter] string $idToken): ?string
    {
        return DB::transaction(function () use ($event, $token, $idToken): ?string {
            [, $challenge] = $this->lockPending($event, $token);
            if (! $challenge->phone_required) {
                return 'This contact does not require verification.';
            }
            if ($challenge->chosen_channel !== null && $challenge->chosen_channel !== 'phone') {
                return 'You chose to verify by email.';
            }
            if ($challenge->phone_verified_at !== null) {
                return 'This contact is already verified.';
            }

            try {
                $verified = app(Auth::class)->verifyIdToken($idToken);
            } catch (\Throwable) {
                $attempts = $challenge->phone_attempts + 1;
                $challenge->forceFill(['phone_attempts' => $attempts])->save();

                return $attempts >= self::MAX_ATTEMPTS
                    ? 'Too many incorrect attempts. Start a new registration.'
                    : 'We could not verify that token. Please try again.';
            }

            $verifiedPhone = $verified->claims()->get('phone_number');
            if (! is_string($verifiedPhone)) {
                $challenge->forceFill(['phone_attempts' => $challenge->phone_attempts + 1])->save();

                return 'The verified phone number is missing from the token.';
            }

            $submittedPhone = $challenge->payload['contact_number'] ?? null;
            $normalizedVerified = Participant::normalizeContactNumber($verifiedPhone);
            $normalizedSubmitted = is_string($submittedPhone)
                ? Participant::normalizeContactNumber($submittedPhone)
                : null;

            if ($normalizedVerified === null || $normalizedSubmitted === null
                || $normalizedVerified !== $normalizedSubmitted) {
                $challenge->forceFill(['phone_attempts' => $challenge->phone_attempts + 1])->save();

                return 'The verified phone number does not match the one you submitted.';
            }

            $challenge->forceFill([
                'phone_verified_at' => now(),
                'phone_code_hash' => null,
                'phone_expires_at' => null,
            ])->save();

            return null;
        }, 3);
    }

    private function identityConflict(): never
    {
        throw ValidationException::withMessages([
            'verification' => 'You already have a registration for this event, or your verified details conflict with an existing participant. If you believe this is an error, contact the event organizer.',
        ]);
    }
}
