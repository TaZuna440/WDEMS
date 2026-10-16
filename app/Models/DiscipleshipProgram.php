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
 * relation below.
 *
 * Program rows are seeded, not created through the UI. The six
 * known programs are defined in DiscipleshipProgramSeeder. If the
 * client later adds a seventh, the seeder is the change point.
 *
 * Phase 5A. batches() relation added in 5B.
 */
#[Fillable([
    'name',
    'slug',
    'description',
    'display_order',
])]
class DiscipleshipProgram extends Model
{
    protected function casts(): array
    {
        return [
            'display_order' => 'integer',
        ];
    }

    public function batches(): HasMany
    {
        return $this->hasMany(DiscipleshipBatch::class, 'program_id');
    }
}
