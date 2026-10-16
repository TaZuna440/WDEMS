<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * One Discipleship program.
 *
 * A program is a course or track (Life Class, School of Leaders
 * 1-3, etc.). Each program will contain many batches -- the
 * batches() relation lands in Phase 5B alongside the
 * DiscipleshipBatch model.
 *
 * Program rows are seeded, not created through the UI. The six
 * known programs are defined in DiscipleshipProgramSeeder. If the
 * client later adds a seventh, the seeder is the change point.
 *
 * Phase 5A.
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
}
