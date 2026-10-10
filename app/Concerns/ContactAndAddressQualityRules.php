<?php

namespace App\Concerns;

trait ContactAndAddressQualityRules
{
    /**
     * Quality checks for the contact_number field.
     *
     * Catches keyboard mashing ("fgfdgfdgdfg") while admitting any
     * real phone number a participant might enter:
     *
     *   09171234567         PH mobile, 11 digits
     *   +63 917 123 4567    PH mobile, +63 prefix
     *   639171234567        PH mobile, 63 prefix
     *   9171234567          PH mobile, bare 9 prefix
     *   02-8123-4567        PH landline
     *   (02) 8123 4567      PH landline, grouped
     *
     * Two rules:
     *   - allowed characters: digits, spaces, + - ( ) .
     *   - digit count: 7-15 (enough for any national or international
     *     number, too few for keyboard mashing)
     *
     * Note: this checks FORMAT. It does not require that the value
     * normalize into the PH mobile identity key. Landlines are
     * legitimate input and normalize to null by design — see
     * docs/participant-identity.md §9.
     *
     * Mirrored in resources/js/lib/public-registration-validation.ts
     * as validateContactNumber(). Keep both in sync.
     *
     * @return array<int, string>
     */
    protected function contactNumberQualityRules(): array
    {
        return [
            'regex:/^[\d\s+\-().]+$/',
            'regex:/^(?=(?:\D*\d){7,15}\D*$)/',
        ];
    }

    /**
     * Quality checks for the address field.
     *
     * Catches keyboard mashing ("dfdfdsfdsfdsfd") while admitting
     * real addresses in any reasonable form:
     *
     *   123 Main St
     *   Unit 5, Tower A, BGC, Taguig
     *   5th Avenue
     *   8888 Sesame
     *
     * Five rules:
     *   - length: at least 5 characters
     *   - contains at least one letter
     *   - contains at least one vowel OR digit
     *   - contains at least one consonant OR digit
     *   - no 3+ identical LETTERS in a row
     *
     * The "OR digit" clauses admit real addresses whose only vowels
     * appear inside numbers ("5th") or whose only consonants appear
     * as repeated digits ("8888 Sesame"). Without them, a valid
     * address would be rejected as keyboard mashing.
     *
     * The last rule targets letters only. Digits repeat legitimately
     * in real addresses ("1111 Elm"), so the digit case is not
     * rejected — the digit-count rule on the phone handles the
     * analogous garbage case there.
     *
     * Mirrored in resources/js/lib/public-registration-validation.ts
     * as validateAddress(). Keep both in sync.
     *
     * @return array<int, string>
     */
    protected function addressQualityRules(): array
    {
        return [
            'min:5',
            'regex:/[A-Za-z]/',
            'regex:/[aeiouyAEIOUY0-9]/',
            'regex:/[bcdfghjklmnpqrstvwxzBCDFGHJKLMNPQRSTVWXZ0-9]/',
            'not_regex:/([A-Za-z])\1\1/',
        ];
    }
}
