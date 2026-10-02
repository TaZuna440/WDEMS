<?php

namespace App\Models;

use App\Exceptions\IdentityRequiredException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'first_name',
    'last_name',
    'contact_number',
    'contact_number_normalized',
    'email',
    'age',
    'address',
])]
class Participant extends Model
{
    protected function casts(): array
    {
        return [
            'age' => 'integer',
        ];
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(Registration::class);
    }

    public function fullName(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
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
    public function setContactNumberAttribute(mixed $value): void
    {
        $raw = is_string($value) ? trim($value) : null;

        if ($raw === '') {
            $raw = null;
        }

        $this->attributes['contact_number'] = $raw;
        $this->attributes['contact_number_normalized'] = self::normalizeContactNumber($raw);
    }

    /**
     * Resolve a participant from identity keys.
     *
     * Email is the primary identity key and wins when both email and
     * phone are present. Normalized phone is the fallback. When neither
     * is present, the resolver throws — the at-least-one rule is
     * enforced upstream by PublicRegistrationRequest, so the throw is
     * defense-in-depth.
     *
     * On an existing match, $attributes are discarded. First-write-wins.
     * The participant record is identity, not a mutable profile — a
     * name correction on a subsequent registration does not change
     * the stored value. See docs/participant-identity.md §6.
     *
     * @param  array<string, mixed>  $attributes
     *
     * @throws IdentityRequiredException
     */
    public static function resolveFrom(
        ?string $email,
        ?string $rawPhone,
        array $attributes,
    ): self {
        $trimmedEmail = is_string($email) ? trim($email) : '';

        if ($trimmedEmail !== '') {
            return self::firstOrCreate(
                ['email' => $trimmedEmail],
                $attributes,
            );
        }

        $normalized = self::normalizeContactNumber($rawPhone);

        if ($normalized !== null) {
            return self::firstOrCreate(
                ['contact_number_normalized' => $normalized],
                $attributes,
            );
        }

        throw new IdentityRequiredException(
            'A participant must be resolved with at least one identity '
            .'key: email or contact number.',
        );
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
     * Mirrored in docs/participant-identity.md §5. Any change here
     * must update that doc.
     */
    private static function normalizeContactNumber(?string $raw): ?string
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
