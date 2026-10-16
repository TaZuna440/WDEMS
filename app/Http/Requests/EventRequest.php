<?php

namespace App\Http\Requests;

use App\Concerns\EventValidationRules;
use App\Enums\ChurchEventStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class EventRequest extends FormRequest
{
    use EventValidationRules;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalize the payload before validation runs.
     *
     * Two derivations:
     *
     *   1. event_kind is derived from event_type when absent. The
     *      running-event wizard sends event_type but not event_kind;
     *      the derived value keeps currentEventKind() correct inside
     *      eventRules() without requiring the frontend to know about
     *      the discriminator column.
     *
     *   2. church_status defaults to Upcoming when the event is a
     *      church event. The wizard does not ask for a lifecycle
     *      status at creation time — a new event is by definition
     *      upcoming. The value can be changed later from the event
     *      show page. Running events leave this field null; the
     *      column is null for them.
     */
    protected function prepareForValidation(): void
    {
        $eventType = $this->input('event_type');
        $eventKind = $this->input('event_kind');

        // Derive event_kind from event_type when absent.
        if (($eventKind === null || $eventKind === '') && is_string($eventType)) {
            $this->merge([
                'event_kind' => $eventType === 'church' ? 'church' : 'running',
            ]);
        }

        // Default church_status for church events when the wizard
        // does not supply one.
        $kind = $this->input('event_kind');
        $churchStatus = $this->input('church_status');

        if (
            $kind === 'church'
            && ($churchStatus === null || $churchStatus === '')
        ) {
            $this->merge([
                'church_status' => ChurchEventStatus::Upcoming->value,
            ]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    public function rules(): array
    {
        return $this->eventRules();
    }

    /**
     * Register the cross-field checks that run after the standard rules.
     *
     * - validateEventStartIsInFuture: combined date + time must be
     *   in the future (today at a past time is rejected)
     * - validateEventDuration: minimum 1 hour when end_time is present
     * - validatePartnerDuplicates: same name+type appears twice
     * - validateEventNameUnique: same user, same name, still active
     */
    public function withValidator($validator): void
    {
        $this->validateEventStartIsInFuture($validator);
        $this->validateEventDuration($validator);
        $this->validatePartnerDuplicates($validator);
        $this->validateEventNameUnique($validator);
    }

    /**
     * Custom messages for rules whose Laravel defaults do not read well.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->eventMessages();
    }
}
