<?php

namespace App\Http\Requests;

use App\Concerns\RegistrationFieldValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RegistrationFieldRequest extends FormRequest
{
    use RegistrationFieldValidationRules;

    /**
     * Determine if the user is authorized to make this request.
     *
     * The guard lives in the controller — canEditRegistrationForm() is
     * checked before this request's rules run.
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
        return $this->registrationFieldRules();
    }

    /**
     * Register the cross-field checks that run after the standard
     * rules. Each one reads the whole payload.
     */
    public function withValidator($validator): void
    {
        $this->validateChoiceFieldOptions($validator);
        $this->validateFieldLabelDuplicates($validator);
        $this->validateFieldLabelQuality($validator);
    }

    /**
     * Custom messages for rules whose Laravel defaults do not read well.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->registrationFieldMessages();
    }
}
