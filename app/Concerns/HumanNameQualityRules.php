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
     * Unicode-aware (2026-10-16): per client decision Q7, WDEMS
     * accepts non-English event names. The start check uses \p{L}
     * and \p{N} (any Unicode letter or number). The vowel and
     * consonant checks pass when the value contains either an ASCII
     * vowel/consonant OR any non-ASCII character. Pure-ASCII input
     * is still checked strictly, so keyboard mashing like
     * "dfdsfdsfd" is still rejected. Non-ASCII input bypasses the
     * vowel/consonant checks -- the trade-off is that an odd-looking
     * foreign-language string passes, which is acceptable since the
     * impact is a strange event name, not a security issue.
     *
     * @return array<int, string>
     */
    protected function humanNameQualityRules(): array
    {
        return [
            'regex:/^[\p{L}\p{N}]/u',
            'regex:/([aeiouyAEIOUY]|[^\x00-\x7F])/u',
            'regex:/([bcdfghjklmnpqrstvwxzBCDFGHJKLMNPQRSTVWXZ]|[^\x00-\x7F])/u',
            'not_regex:/(.)\1\1/u',
        ];
    }
}
