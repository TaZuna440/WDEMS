<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'google_account_id',
    'google_email',
    'access_token',
    'refresh_token',
    'expires_at',
    'scopes',
])]
#[Hidden(['access_token', 'refresh_token'])]
class GoogleIntegration extends Model
{
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'scopes' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}
