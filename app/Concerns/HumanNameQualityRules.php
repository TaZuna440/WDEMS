<?php

namespace App\Concerns;

trait HumanNameQualityRules
{
    /**
     * Quality checks shared by every free-text "name" field.
     *
     * Catches keyboard mashing:
     *  - doesn't start with a letter or number
     *  - has no vowels (e.g. "dfdsfdsfd")
     *  - has no consonants (e.g. "aeiou")
     *  - 3+ identical characters in a row (e.g. "aaaa")
     *
     * Used by event_name, partners.*.name, and the registration form
     * builder's label field. Mirrored in
     * resources/js/lib/event-validation.ts as humanNameQualityError().
     * Keep both in sync.
     *
     * @return array<int, string>
     */
    protected function humanNameQualityRules(): array
    {
        return [
            'regex:/^[A-Za-z0-9]/',
            'regex:/[aeiouyAEIOUY]/',
            'regex:/[bcdfghjklmnpqrstvwxzBCDFGHJKLMNPQRSTVWXZ]/',
            'not_regex:/(.)\1\1/',
        ];
    }
}
