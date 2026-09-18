<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'event_id',
    'google_form_id',
    'google_sheet_id',
    'form_url',
    'sheet_url',
    'status',
    'created_by',
    'sheet_linked_at',
])]
class RegistrationSetup extends Model
{
    protected function casts(): array
    {
        return [
            'sheet_linked_at' => 'datetime',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function changes(): HasMany
    {
        return $this->hasMany(RegistrationSetupChange::class);
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    public function hasLinkedSheet(): bool
    {
        return $this->google_sheet_id !== null;
    }
}
