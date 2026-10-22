<?php

namespace App\Http\Requests\Discipleship;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validate a bulk attendance upsert on a batch.
 *
 * Payload shape:
 *   cells: array of {enrollment_id, session_id, status}
 *
 * Each cell is a single matrix coordinate. The controller
 * upserts on (enrollment_id, session_id). The request only
 * validates shape and enum membership -- that both ids belong
 * to the batch is the controller's guard, because it requires a
 * database lookup.
 *
 * max:500 on cells. A 20-participant × 20-session batch is 400
 * cells; 500 gives headroom without letting a runaway client
 * submit an unbounded array.
 *
 * Phase 5E.
 */
class StoreDiscipleshipAttendanceBulkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'cells' => ['required', 'array', 'min:1', 'max:500'],
            'cells.*.enrollment_id' => ['required', 'integer', 'exists:discipleship_enrollments,id'],
            'cells.*.session_id' => ['required', 'integer', 'exists:discipleship_sessions,id'],
            'cells.*.status' => ['required', Rule::in(['present', 'absent'])],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'cells.required' => 'No attendance cells submitted.',
            'cells.max' => 'Too many cells in one submission.',
            'cells.*.enrollment_id.required' => 'Each cell needs an enrollment.',
            'cells.*.enrollment_id.exists' => 'One of the enrollments does not exist.',
            'cells.*.session_id.required' => 'Each cell needs a session.',
            'cells.*.session_id.exists' => 'One of the sessions does not exist.',
            'cells.*.status.required' => 'Each cell needs a status.',
            'cells.*.status.in' => 'Attendance status must be present or absent.',
        ];
    }
}
