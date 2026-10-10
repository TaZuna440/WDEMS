<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Toggle the `flagged_at` state on a registration.
 *
 * Accepts an explicit `flagged` boolean rather than a blind toggle.
 * Reasons:
 *
 *   1. Idempotent. Retrying "flag" over a slow connection is a
 *      no-op if already flagged. A toggle would flip the state on
 *      retry, silently un-flagging the row.
 *
 *   2. Race-safe. Two devices flagging simultaneously converge on
 *      the same value with a boolean. A toggle would cancel out.
 *
 *   3. Clear UI. The frontend holds the current state and sends the
 *      desired value, so what the operator sees is what they get.
 */
class FlagRegistrationRequest extends FormRequest
{
    /**
     * Route middleware is the gate (auth + verified.or.admin +
     * device.trusted). The controller checks that the registration
     * belongs to an event that is open or closed.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'flagged' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'flagged.required' => 'A flag state is required.',
            'flagged.boolean' => 'The flag state must be true or false.',
        ];
    }
}
