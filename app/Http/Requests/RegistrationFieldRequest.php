<?php

namespace App\Http\Requests;

use App\Concerns\RegistrationFieldValidationRules;
use App\Support\RegistrationRichText;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

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
        return array_merge($this->registrationFieldRules(), [
            'disclaimer_html' => ['nullable', 'string', 'max:30000'],
            'tshirt_enabled' => ['sometimes', 'boolean'],
            'tshirt_label' => ['required_if:tshirt_enabled,1', 'nullable', 'string', 'min:2', 'max:50'],
            'tshirt_required' => ['sometimes', 'boolean'],
            'tshirt_choices' => ['required_if:tshirt_enabled,1', 'array', 'min:3', 'max:6'],
            'tshirt_choices.*' => ['required', 'string', 'max:50', 'distinct:ignore_case'],
            'tshirt_photos' => ['sometimes', 'array', 'max:3'],
            'tshirt_photos.*' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:10240'],
            'tshirt_existing_photos' => ['sometimes', 'array', 'max:3'],
            'tshirt_existing_photos.*' => ['string', 'distinct'],
        ]);
    }

    /**
     * Register the cross-field checks that run after the standard
     * rules. Each one reads the whole payload.
     *
     * - validateChoiceFieldOptions: choice types require options
     * - validateFieldLabelDuplicates: no two fields share a label
     * - validateFieldLabelCollisions: custom labels cannot collide
     *   with the six common fields
     * - validateFieldLabelQuality: reject keyboard mashing
     * - validateCommonFieldRequirementsIdentity: email and
     *   contact_number cannot both be toggled optional
     */
    public function withValidator($validator): void
    {
        $this->validateChoiceFieldOptions($validator);
        $this->validateFieldLabelDuplicates($validator);
        $this->validateFieldLabelCollisions($validator);
        $this->validateFieldLabelQuality($validator);
        $this->validateCommonFieldRequirementsIdentity($validator);
        $validator->after(function ($validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $existing = $this->route('event')?->registration_tshirt['photos'] ?? [];
            $paths = $this->input('tshirt_existing_photos', []);
            $uploads = $this->file('tshirt_photos', []);
            if (is_array($paths) && is_array($uploads)) {
                $known = array_column($existing, 'path');
                if (array_diff($paths, $known)) {
                    $validator->errors()->add('tshirt_existing_photos', 'Choose photos belonging to this event.');
                }
                if (count($paths) + count($uploads) > 3) {
                    $validator->errors()->add('tshirt_photos', 'Use at most three photos.');
                }
                $size = array_sum(array_map(fn ($photo) => in_array($photo['path'], $paths, true) ? $photo['size'] : 0, $existing));
                foreach ($uploads as $upload) {
                    if ($upload instanceof UploadedFile && $upload->isValid()) {
                        $size += $upload->getSize();
                    }
                }
                if ($size > 25 * 1024 * 1024) {
                    $validator->errors()->add('tshirt_photos', 'Photos must total 25 MB or less.');
                }
                if (! $this->boolean('tshirt_enabled') && (count($paths) || count($uploads))) {
                    $validator->errors()->add('tshirt_enabled', 'Remove photos before disabling the T-shirt field.');
                }
            }
            $choices = $this->input('tshirt_choices', []);
            if ($this->boolean('tshirt_enabled') && (! is_array($choices) || array_diff(['Small', 'Medium', 'Large'], $choices))) {
                $validator->errors()->add('tshirt_choices', 'Small, Medium, and Large must remain available.');
            }
            if ($this->filled('disclaimer_html') && is_string($this->input('disclaimer_html'))
                && trim(strip_tags(RegistrationRichText::sanitize($this->input('disclaimer_html')))) === '') {
                $validator->errors()->add('disclaimer_html', 'The disclaimer must contain text.');
            }
            foreach (['email', 'address'] as $field) {
                if ($this->has("common_field_requirements.$field") && ! $this->boolean("common_field_requirements.$field")) {
                    $validator->errors()->add("common_field_requirements.$field", ucfirst($field).' is required for new registration forms.');
                }
            }
        });
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
