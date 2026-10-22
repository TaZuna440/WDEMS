<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class ChurchSystemScopeController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('events/church/system-scope', [
            'modules' => [
                ['name' => 'Services', 'workflow' => 'Service occurrences, attendance, and registration bridge.'],
                ['name' => 'Fellowship', 'workflow' => 'Fellowship events, groups, attendance, and registration bridge.'],
                ['name' => 'Discipleship', 'workflow' => 'Programs, batches, enrollments, sessions, and attendance.'],
                ['name' => 'Youth Ministry', 'workflow' => 'Youth Camp, Huddle, Campus Ministry, and Timothy Class.'],
                ['name' => 'Kids Ministry', 'workflow' => 'Kids Service, Vacation Bible School, and the four Outreach categories.'],
                ['name' => 'Young Adult', 'workflow' => 'Young Adult Service, Seminar, GRC Care Group, and GRC Fellowship.'],
                ['name' => 'Worship & Prayer', 'workflow' => 'Outdoor Prayer Meeting, Worship Night, and Prayer & Fasting.'],
                ['name' => 'Church Administration', 'workflow' => 'Church Planning and the ministry meeting categories.'],
                ['name' => 'Special Occasions', 'workflow' => 'Church Anniversary, Thanksgiving, Appreciation, Dedication, and School of Leaders.'],
            ],
            'included' => [
                'Church event creation and management',
                'Staff registration and public registration with email verification',
                'Duplicate review and registration codes',
                'Present, Absent, and Unmarked attendance with summary counts',
                'Church participant history',
            ],
            'limitations' => [
                'The six specialist modules use the shared generic church-event workflow.',
                'Activity-specific fields and workflows are not included.',
                'The guardian fields are shared form fields and are not conditionally customized.',
                'Church CSV/print export and object-level authorization remain future work.',
            ],
        ]);
    }
}
