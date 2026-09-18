<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'registration_setup_id',
    'user_id',
    'google_email_used',
    'action',
    'google_item_id',
    'item_title',
    'changes',
])]
class RegistrationSetupChange extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'changes' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function setup(): BelongsTo
    {
        return $this->belongsTo(RegistrationSetup::class, 'registration_setup_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
