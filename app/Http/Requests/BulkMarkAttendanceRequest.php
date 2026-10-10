<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Bulk mark endpoint payload.
 *
 * Two accepted shapes, mutually exclusive:
 *
 *   1. Explicit list:
 *        { registration_ids: [1, 2, 3] }
 *      Marks exactly these registrations.
 *
 *   2. Mark-all-visible:
 *        { mark_all_visible: true, filter: 'unmarked', q: 'maria' }
 *      Marks every registration matching the current filter and
 *      search. Scope is the filter+search, not the current page —
 *      pagination is a view concern, not a marking concern. See
 *      D10 in docs/attendance-redesign.md.
 *
 * The at-least-one rule lives in withValidator(). Standard rules
 * only express the shape.
 */
class BulkMarkAttendanceRequest extends FormRequest
{
    /**
     * Route middleware is the gate (auth + verified.or.admin +
     * device.trusted). The controller checks the event-specific
     * window gate.
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
            'registration_ids' => ['nullable', 'array', 'max:500'],
            'registration_ids.*' => ['integer', 'exists:registrations,id'],

            'mark_all_visible' => ['nullable', 'boolean'],

            'filter' => [
                'nullable',
                'string',
                Rule::in(['all', 'unmarked', 'present', 'absent', 'late', 'excused']),
            ],
            'q' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Two after-checks:
     *
     *   1. At-least-one shape — either a non-empty registration_ids
     *      array OR mark_all_visible === true.
     *   2. Mutually exclusive — the caller must not send both.
     *
     * Both errors land under `shape` so the frontend has a single
     * error key to render.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $ids = $this->input('registration_ids');
            $hasIds = is_array($ids) && count($ids) > 0;
            $markAll = $this->boolean('mark_all_visible');

            if (! $hasIds && ! $markAll) {
                $v->errors()->add(
                    'shape',
                    'Provide either registration_ids or mark_all_visible.',
                );

                return;
            }

            if ($hasIds && $markAll) {
                $v->errors()->add(
                    'shape',
                    'Provide either registration_ids or mark_all_visible, not both.',
                );
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'registration_ids.*.exists' => 'One or more registrations do not exist.',
        ];
    }
}
