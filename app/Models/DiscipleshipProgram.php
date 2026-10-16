<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One Discipleship program.
 *
 * A program is a course or track (Life Class, School of Leaders
 * 1-3, etc.). Each program contains many batches -- the batches()
 * relation below -- and many enrollments -- the enrollments()
 * relation below.
 *
 * Program rows are seeded, not created through the UI. The six
 * known programs are defined in DiscipleshipProgramSeeder. If the
 * client later adds a seventh, the seeder is the change point.
 *
 * required_sessions and extra_requirements are completion
 * criteria added in 5F. Null required_sessions means "no fixed
 * requirement" -- the progress service falls back to 100% of
 * applicable sessions. Null extra_requirements means no
 * additional checklist beyond session count.
 *
 * Phase 5A. batches() added in 5B. enrollments() added in 5C.
 * Criteria columns added in 5F.
 */
#[Fillable([
    'name',
    'slug',
    'description',
    'display_order',
    'required_sessions',
    'extra_requirements',
])]
class DiscipleshipProgram extends Model
{
    protected function casts(): array
    {
        return [
            'display_order' => 'integer',
            'required_sessions' => 'integer',
            'extra_requirements' => 'array',
        ];
    }

    public function batches(): HasMany
    {
        return $this->hasMany(DiscipleshipBatch::class, 'program_id');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(DiscipleshipEnrollment::class, 'program_id');
    }
}
