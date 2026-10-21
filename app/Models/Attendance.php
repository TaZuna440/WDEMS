<?php

namespace App\Models;

use App\Enums\AttendanceStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

#[Fillable([
    'registration_id',
    'attendance_status',
    'attendance_time',
    'recorded_by',
    'notes',
    'verified_at',
])]
class Attendance extends Model
{
    protected static function booted(): void
    {
        static::saving(function (self $attendance): void {
            if (! Registration::query()->active()->whereKey($attendance->registration_id)->exists()) {
                throw ValidationException::withMessages(['registration' => 'This registration is not active.']);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'attendance_status' => AttendanceStatus::class,
            'attendance_time' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(Registration::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
