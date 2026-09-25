<?php

namespace App\Http\Requests;

use App\Concerns\EventValidationRules;
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
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    public function rules(): array
    {
        return $this->eventRules();
    }

    /**
     * Register the min-duration check that runs after the standard rules.
     */
    public function withValidator($validator): void
    {
        $this->validateEventDuration($validator);
        $this->validatePartnerDuplicates($validator);
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
