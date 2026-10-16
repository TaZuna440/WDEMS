<?php

namespace Database\Seeders;

use App\Models\DiscipleshipProgram;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seed the six Discipleship programs.
 *
 * Idempotent: uses firstOrCreate on slug. Re-running does not
 * duplicate. If a program's name needs to change, add a dedicated
 * rename migration -- this seeder will not overwrite an existing
 * row's name.
 *
 * display_order follows the sequence in the list. Lower numbers
 * render first on the landing page.
 *
 * Slugs are auto-derived from the name via Str::slug(). The six
 * known slugs, for reference:
 *   life-class
 *   encounter-god-retreat
 *   school-of-leaders-1
 *   school-of-leaders-2
 *   school-of-leaders-3
 *   cell-convergence
 *
 * Phase 5A, per docs/church-events/07 section 3.6 (5A.1).
 */
class DiscipleshipProgramSeeder extends Seeder
{
    /**
     * The six programs, in display order.
     *
     * Each entry: [name, description].
     */
    private const PROGRAMS = [
        [
            'Life Class',
            'Foundational teaching for new believers and those exploring the faith.',
        ],
        [
            'Encounter God Retreat',
            'A weekend retreat focused on personal encounter and renewal.',
        ],
        [
            'School of Leaders 1',
            'First level of leadership formation.',
        ],
        [
            'School of Leaders 2',
            'Second level of leadership formation.',
        ],
        [
            'School of Leaders 3',
            'Third level of leadership formation.',
        ],
        [
            'Cell Convergence',
            'Gathering and equipping of cell group leaders and members.',
        ],
    ];

    public function run(): void
    {
        foreach (self::PROGRAMS as $order => [$name, $description]) {
            DiscipleshipProgram::firstOrCreate(
                ['slug' => Str::slug($name)],
                [
                    'name' => $name,
                    'description' => $description,
                    'display_order' => $order,
                ],
            );
        }
    }
}
