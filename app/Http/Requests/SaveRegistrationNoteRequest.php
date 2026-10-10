<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Save the free-form note attached to a registration.
 *
 * The note is capped at 2000 characters. An empty string clears the
 * note — the controller stores null in that case so the DB reflects
 * "no note" rather than "empty string".
 *
 * Scope per docs/registration-monitoring.md §10: one note per
 * registration. Editing replaces the previous content. No history of
 * past versions.
 */
class SaveRegistrationNoteRequest extends FormRequest
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
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'note.max' => 'Notes cannot exceed 2000 characters.',
        ];
    }
}
