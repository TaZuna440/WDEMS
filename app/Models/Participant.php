<?php

namespace App\Models;

use App\Exceptions\IdentityRequiredException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'provisional_event_id',
    'pool',
    'first_name',
    'first_name_normalized',
    'last_name',
    'last_name_normalized',
    'middle_name',
    'suffix',
    'contact_number',
    'contact_number_normalized',
    'email',
    'age',
    'birthday',
    'address',
])]
class Participant extends Model
{
    public static function assertPool(string $pool): void
    {
        if (! in_array($pool, ['running', 'church'], true)) {
            throw new \InvalidArgumentException('An explicit running or church participant pool is required.');
        }
    }

    protected static function booted(): void
    {
        static::saving(function (self $participant): void {
            self::assertPool((string) $participant->pool);
        });
    }

    protected function casts(): array
    {
        return [
            'age' => 'integer',
            'birthday' => 'date',
        ];
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(Registration::class);
    }

    /**
     * Church membership record, if any.
     *
     * One per participant -- the members table holds a UNIQUE
     * constraint on participant_id. Returns null when the participant
     * has no member record.
     *
     * Per docs/church-events/06 sub-decision Q2a (2026-10-16).
     */
    public function member(): HasOne
    {
        return $this->hasOne(Member::class);
    }

    /**
     * First-visit records for this participant.
     *
     * Zero or more over time -- a participant may attend a first
     * service, lapse, and later return. Each row records one
     * first-visit event. Promoted records are retained for history.
     *
     * Per docs/church-events/06 sub-decisions Q3a + Q3b (2026-10-16).
     */
    public function firstTimers(): HasMany
    {
        return $this->hasMany(FirstTimer::class);
    }

    /**
     * Attendance records for this participant on the Members track.
     *
     * One row per service occurrence the participant was marked on.
     * The First-Timers track is a separate relation -- a participant
     * appears on at most one of the two for any given occurrence.
     *
     * Phase 3, per client mockup.
     */
    public function serviceAttendanceMembers(): HasMany
    {
        return $this->hasMany(ServiceAttendanceMember::class);
    }

    /**
     * Attendance records for this participant on the First-Timers
     * track.
     *
     * Populated while the participant is a first-timer. After they
     * are promoted to membership, new attendance goes through
     * serviceAttendanceMembers() instead. Historical first-timer
     * records are retained.
     *
     * Phase 3, per client mockup.
     */
    public function serviceAttendanceFirstTimers(): HasMany
    {
        return $this->hasMany(ServiceAttendanceFirstTimer::class);
    }

    /**
     * Attendance records for this participant at Fellowship events.
     *
     * One row per Fellowship event the participant was marked on.
     * Unlike Services, Fellowship has a single unified attendance
     * track; the optional group assignment lives on the row itself
     * (fellowship_group_id) rather than as a separate relation.
     *
     * Phase 4.
     */
    public function fellowshipAttendances(): HasMany
    {
        return $this->hasMany(FellowshipAttendance::class);
    }

    /**
     * Enrollment records for this participant at Discipleship
     * programs.
     *
     * Zero or more. A participant can be enrolled in multiple
     * programs (Life Class, School of Leaders 1, etc.) but only
     * once per program -- enforced by the total unique on
     * (participant_id, program_id) in the enrollments table.
     *
     * Phase 5C.
     */
    public function discipleshipEnrollments(): HasMany
    {
        return $this->hasMany(DiscipleshipEnrollment::class);
    }

    public function fullName(): string
    {
        return implode(' ', array_filter([$this->first_name, $this->middle_name, $this->last_name, $this->suffix], fn ($part) => $part !== null && $part !== ''));
    }

    /**
     * Computed age derived from birthday.
     *
     * When `birthday` is present, age is recomputed from it on every
     * read. This makes `birthday` the source of truth for the
     * participant's age: after a birthday passes, `$participant->age`
     * returns the new value without any write.
     *
     * When `birthday` is null, the stored `age` column is returned
     * as-is. Two cases reach this branch:
     *
     *   1. Legacy participants created before the birthdate swap
     *      (2026-10-19), which have `age` but no `birthday`.
     *   2. Church-event participants created through the Services or
     *      Fellowship attendance flows, which still collect `age`
     *      directly and do not set `birthday`.
     *
     * `$value` is the post-cast value of the raw `age` attribute. The
     * `integer` cast has already run, so null-or-int is what arrives
     * here.
     *
     * See docs/participant-identity.md §6 and D1=B of the birthdate
     * swap plan.
     */
    public function getAgeAttribute(mixed $value): ?int
    {
        if ($this->birthday !== null) {
            return $this->birthday->age;
        }

        return $value === null ? null : (int) $value;
    }

    /**
     * Mutator for contact_number.
     *
     * Writes both the raw value (for display) and the normalized form
     * (for identity resolution). The normalizer is the sole source of
     * truth for what "the same number" means.
     *
     * Called by Eloquent on any write to contact_number — including
     * create() and update() through fill(). Not called when the
     * attribute is absent from the fill payload.
     */
    /**
     * Normalize a raw name into the canonical form for identity matching.
     *
     * Canonical form: trim, collapse internal whitespace runs to a single
     * space, mb_strtolower (Unicode-aware). "  ANA   Maria " becomes
     * "ana maria". Returns null for null/blank input.
     *
     * Mirrored in docs/participant-identity.md §5. Any change here
     * must update that doc.
     */
    public static function normalizeName(?string $raw): ?string
    {
        if (! is_string($raw)) {
            return null;
        }

        $collapsed = preg_replace('/\s+/u', ' ', trim($raw));

        if (! is_string($collapsed) || $collapsed === '') {
            return null;
        }

        return mb_strtolower($collapsed, 'UTF-8');
    }

    public static function normalizeSuffix(?string $raw): ?string
    {
        $normalized = self::normalizeName($raw);

        return $normalized === null ? null : str_replace('.', '', $normalized);
    }

    /**
     * Keep first_name_normalized in sync on every write.
     *
     * Mirrors setContactNumberAttribute: the raw column is the display
     * truth, the normalized column is what identity resolution queries
     * against.
     */
    public function setFirstNameAttribute(mixed $value): void
    {
        $raw = is_string($value) ? trim($value) : null;

        if ($raw === '') {
            $raw = null;
        }

        $this->attributes['first_name'] = $raw;
        $this->attributes['first_name_normalized'] = self::normalizeName($raw);
    }

    /**
     * Keep last_name_normalized in sync on every write.
     */
    public function setLastNameAttribute(mixed $value): void
    {
        $raw = is_string($value) ? trim($value) : null;

        if ($raw === '') {
            $raw = null;
        }

        $this->attributes['last_name'] = $raw;
        $this->attributes['last_name_normalized'] = self::normalizeName($raw);
    }

    public function setContactNumberAttribute(mixed $value): void
    {
        $raw = is_string($value) ? trim($value) : null;

        if ($raw === '') {
            $raw = null;
        }

        $this->attributes['contact_number'] = $raw;
        $this->attributes['contact_number_normalized'] = self::normalizeContactNumber($raw);
    }

    public function setMiddleNameAttribute(mixed $value): void
    {
        $this->attributes['middle_name'] = is_string($value) && trim($value) !== '' ? trim($value) : null;
        $this->attributes['middle_name_normalized'] = self::normalizeName($value) ?? '';
    }

    public function setSuffixAttribute(mixed $value): void
    {
        $this->attributes['suffix'] = is_string($value) && trim($value) !== '' ? trim($value) : null;
        $this->attributes['suffix_normalized'] = self::normalizeSuffix($value) ?? '';
    }

    public function scopeMatchingName(Builder $query, string $pool, ?string $first, ?string $last, ?string $middle = null, ?string $suffix = null): Builder
    {
        $q = $query->whereNull('provisional_event_id')->where('first_name_normalized', self::normalizeName($first))
            ->where('last_name_normalized', self::normalizeName($last))
            ->where('middle_name_normalized', self::normalizeName($middle) ?? '')
            ->where('suffix_normalized', self::normalizeSuffix($suffix) ?? '');

        self::assertPool($pool);
        $q->where('pool', $pool);

        return $q;
    }

    /**
     * Find a participant by composite identity key.
     *
     * Person key: normalized first + last name + contact (email OR
     * normalized phone). Returns the matched row or null. Does not
     * create.
     *
     * This is the sole sanctioned lookup for participant identity
     * outside of resolveFrom(). The composite rule lives here. Do not
     * reimplement contact-only lookups in call sites: under the
     * non-unique email / normalized phone rule, `where('email', ...)
     * ->first()` returns an arbitrary row when a family shares a
     * contact.
     *
     * Matching rule: name must match AND one of (email OR normalized
     * phone). When both contact fields are provided, matching either
     * is sufficient. The cross-contact conflict case (email matches
     * participant A, phone matches participant B) is a separate open
     * issue -- see ISSUE-020 / docs/composite-identity.md §7.3. This
     * method does not enforce it.
     *
     * Returns null when the name is incomplete after normalization
     * (blank first or last name) -- a participant cannot be
     * identified without a full name. Returns null when neither
     * contact field is provided -- there is nothing to match on.
     */
    public static function findByComposite(
        string $pool,
        ?string $email,
        ?string $rawPhone,
        ?string $firstName,
        ?string $lastName,
        ?string $middleName = null,
        ?string $suffix = null,
    ): ?self {
        self::assertPool($pool);
        $normalizedFirst = self::normalizeName($firstName);
        $normalizedLast = self::normalizeName($lastName);

        if ($normalizedFirst === null || $normalizedLast === null) {
            return null;
        }

        $trimmedEmail = is_string($email) ? trim($email) : '';
        $normalizedPhone = self::normalizeContactNumber($rawPhone);

        if ($trimmedEmail === '' && $normalizedPhone === null) {
            return null;
        }

        $query = self::query()->matchingName($pool, $firstName, $lastName, $middleName, $suffix);

        if ($trimmedEmail !== '' && $normalizedPhone !== null) {
            $query->where(function ($q) use ($trimmedEmail, $normalizedPhone): void {
                $q->where('email', $trimmedEmail)
                    ->orWhere('contact_number_normalized', $normalizedPhone);
            });
        } elseif ($trimmedEmail !== '') {
            $query->where('email', $trimmedEmail);
        } else {
            $query->where('contact_number_normalized', $normalizedPhone);
        }

        return $query->first();
    }

    /**
     * Resolve a participant from the composite identity key.
     *
     * A person is (normalized name + verified contact). The normalized
     * first / last names come from $attributes. The contact is the
     * email, the normalized phone, or both -- matching either is
     * sufficient when both are provided. Both name parts are required.
     *
     * Delegates matching to findByComposite(). On no match, creates a
     * new participant row. On match, $attributes are discarded --
     * first-write-wins. The participant record is identity, not a
     * mutable profile.
     *
     * Matching is global: the same person registering for a different
     * event resolves to the same row. The per-event duplicate check
     * lives in RegistrationVerificationService::assertNoDuplicateRegistration().
     *
     * When neither email nor phone is present, or when either name
     * part is blank, the resolver throws -- the at-least-one rule is
     * enforced upstream by PublicRegistrationRequest, so the throw is
     * defense-in-depth. See docs/composite-identity.md §5.3.
     *
     * @param  array<string, mixed>  $attributes
     *
     * @throws IdentityRequiredException
     */
    public static function resolveFrom(
        string $pool,
        ?string $email,
        ?string $rawPhone,
        array $attributes,
    ): self {
        $existing = self::findByComposite(
            $pool,
            $email,
            $rawPhone,
            $attributes['first_name'] ?? null,
            $attributes['last_name'] ?? null,
            $attributes['middle_name'] ?? null,
            $attributes['suffix'] ?? null,
        );

        if ($existing !== null) {
            return $existing;
        }

        // Defense-in-depth. findByComposite() returns null when the
        // name is incomplete or no contact is present. resolveFrom()
        // cannot create a row in either of those cases -- turn the
        // null into a real error with a specific message.
        $normalizedFirst = self::normalizeName($attributes['first_name'] ?? null);
        $normalizedLast = self::normalizeName($attributes['last_name'] ?? null);

        if ($normalizedFirst === null || $normalizedLast === null) {
            throw new IdentityRequiredException(
                'A participant must be resolved with a first and last name.',
            );
        }

        $trimmedEmail = is_string($email) ? trim($email) : '';
        $normalizedPhone = self::normalizeContactNumber($rawPhone);

        if ($trimmedEmail === '' && $normalizedPhone === null) {
            throw new IdentityRequiredException(
                'A participant must be resolved with at least one identity '
                .'key: email or contact number.',
            );
        }

        // Include the identity contact in the new row. The callers pass
        // email / phone as separate args (not in $attributes) because
        // the old firstOrCreate() used them as the match criteria.
        if ($trimmedEmail !== '' && ! isset($attributes['email'])) {
            $attributes['email'] = $trimmedEmail;
        }
        if ($normalizedPhone !== null && ! isset($attributes['contact_number'])) {
            $attributes['contact_number'] = $rawPhone;
        }
        $attributes['pool'] = $pool;

        return self::create($attributes);
    }

    /**
     * Normalize a raw phone string into the canonical PH mobile form.
     *
     * Accepted inputs and their outputs — all produce 09171234567:
     *   09171234567     11 digits, leading 0
     *   +639171234567   12 digits, leading +63
     *   639171234567    12 digits, leading 63
     *   9171234567      10 digits, leading 9
     *
     * Returns null for anything else — including empty input,
     * non-PH-mobile formats (landlines, international numbers), and
     * strings whose digit count does not match a pattern above.
     *
     * Public so validation code (PublicRegistrationRequest) can call
     * it to decide whether a submitted phone counts as an identity
     * key. A submitted phone that normalizes to null is treated as
     * "no phone" for the at-least-one rule.
     *
     * Mirrored in docs/participant-identity.md §5. Any change here
     * must update that doc.
     */
    public static function normalizeContactNumber(?string $raw): ?string
    {
        if (! is_string($raw)) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $raw);

        if (! is_string($digits) || $digits === '') {
            return null;
        }

        if (str_starts_with($digits, '63') && strlen($digits) === 12) {
            return '0'.substr($digits, 2);
        }

        if (str_starts_with($digits, '9') && strlen($digits) === 10) {
            return '0'.$digits;
        }

        if (str_starts_with($digits, '09') && strlen($digits) === 11) {
            return $digits;
        }

        return null;
    }
}
