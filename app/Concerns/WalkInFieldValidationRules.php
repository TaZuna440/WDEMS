<?php

namespace App\Concerns;

use Illuminate\Validation\Validator;

trait WalkInFieldValidationRules
{
    /**
     * Treat null, empty string, and empty array as absent.
     *
     * Identical logic to the private isEmpty() that lived on
     * WalkInRegistrationRequest before Phase C. Extracted so the
     * single-row and bulk walk-in requests share one implementation.
     */
    private function isEmpty(mixed $value): bool
    {
        return $value === null || $value === '' || $value === [];
    }

    /**
     * Per-type format check for a custom field response value.
     *
     * The error key is passed in by the caller so the same trait can
     * serve both single-row validation (key: "responses.{id}") and
     * bulk validation (key: "rows.{i}.responses.{id}"). The eight
     * field-type branches are unchanged from the private validateType()
     * this was extracted from.
     *
     * @param  array<int, string>  $options
     */
    private function validateResponseType(
        Validator $validator,
        string $errorKey,
        string $type,
        mixed $value,
        array $options,
    ): void {
        switch ($type) {
            case 'text':
            case 'textarea':
            case 'email':
                if (! is_string($value)) {
                    $validator->errors()->add($errorKey, 'Must be a string.');
                } elseif ($type === 'email' && ! filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $validator->errors()->add($errorKey, 'Must be a valid email address.');
                }
                break;

            case 'date':
                if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
                    $validator->errors()->add($errorKey, 'Must be a date in YYYY-MM-DD format.');
                }
                break;

            case 'number':
                if (! is_numeric($value)) {
                    $validator->errors()->add($errorKey, 'Must be a number.');
                }
                break;

            case 'select':
            case 'radio':
                if (! is_string($value) || ! in_array($value, $options, true)) {
                    $validator->errors()->add($errorKey, 'Invalid choice.');
                }
                break;

            case 'checkbox':
                if (! is_array($value)) {
                    $validator->errors()->add($errorKey, 'Must be an array of choices.');
                    break;
                }
                foreach ($value as $option) {
                    if (! is_string($option) || ! in_array($option, $options, true)) {
                        $validator->errors()->add($errorKey, 'Invalid choice.');
                        break;
                    }
                }
                break;
        }
    }
}
