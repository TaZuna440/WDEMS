<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'registration_id',
    'registration_field_id',
    'value',
])]
class RegistrationFieldResponse extends Model
{
    /**
     * The registration this response belongs to.
     *
     * Cascade deletes at the schema level — deleting a registration
     * removes its responses.
     */
    public function registration(): BelongsTo
    {
        return $this->belongsTo(Registration::class);
    }

    /**
     * The custom field this response answers.
     *
     * RESTRICT at the schema level — a field with responses cannot be
     * silently deleted. In practice this cannot happen because the
     * form locks at Open Registration (D6).
     */
    public function registrationField(): BelongsTo
    {
        return $this->belongsTo(RegistrationField::class);
    }
}
