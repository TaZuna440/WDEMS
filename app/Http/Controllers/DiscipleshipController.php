<?php

namespace App\Http\Controllers;

use App\Models\DiscipleshipBatch;
use App\Models\DiscipleshipEnrollment;
use App\Models\DiscipleshipProgram;
use App\Models\Participant;
use App\Services\Discipleship\DiscipleshipProgressService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Discipleship module -- landing, per-program view, and the
 * participant search endpoint used by the enrollment dialog.
 *
 * Programs are seeded, not created through the UI. There is no
 * create, update, or destroy path for programs. If the client
 * later adds a program, the change point is
 * DiscipleshipProgramSeeder.
 *
 * Phase 5A. 5B added batches. 5C added enrollments to show() and
 * the participantSearch() endpoint. 5D added sessions on the
 * batch detail page (separate controller). 5E added attendance
 * (separate controller). 5F adds progress and the program
 * overview card.
 */
class DiscipleshipController extends Controller
{
    public function index(): Response
    {
        $programs = DiscipleshipProgram::query()
            ->withCount(['batches', 'enrollments'])
            ->orderBy('display_order')
            ->orderBy('id')
            ->get()
            ->map(fn (DiscipleshipProgram $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'slug' => $p->slug,
                'description' => $p->description,
                'batches_count' => $p->batches_count,
                'enrollments_count' => $p->enrollments_count,
            ]);

        return Inertia::render('discipleship/index', [
            'programs' => $programs,
        ]);
    }

    /**
     * Per-program view: header, batches, enrollments, and the
     * program overview card.
     *
     * Batches ordered by start_date ascending (chronological).
     * Enrollments ordered by created_at descending.
     *
     * Each enrollment row carries a `progress` sub-object from
     * DiscipleshipProgressService. The program payload carries
     * `overview` -- status counts plus completion rate.
     *
     * Phase 5G adds search, filter, and pagination on the
     * enrollment list. At 5F the whole list is loaded.
     */
    public function show(DiscipleshipProgram $program): Response
    {
        $progressService = app(DiscipleshipProgressService::class);

        $batches = $program->batches()
            ->orderBy('start_date')
            ->orderBy('id')
            ->get()
            ->map(fn (DiscipleshipBatch $b) => [
                'id' => $b->id,
                'name' => $b->name,
                'start_date' => $b->start_date->toDateString(),
            ]);

        $enrollments = $program->enrollments()
            ->with([
                'participant:id,first_name,last_name,email,contact_number',
                'batch:id,name',
                'program',
            ])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (DiscipleshipEnrollment $e) => [
                'id' => $e->id,
                'participant_id' => $e->participant_id,
                'full_name' => $e->participant?->fullName(),
                'email' => $e->participant?->email,
                'contact_number' => $e->participant?->contact_number,
                'batch_id' => $e->batch_id,
                'batch_name' => $e->batch?->name,
                'status' => $e->status->value,
                'status_label' => $e->status->label(),
                'enrolled_at' => $e->enrolled_at?->toDateString(),
                'notes' => $e->notes,
                'progress' => $progressService->progress($e),
            ]);

        return Inertia::render('discipleship/show', [
            'program' => [
                'id' => $program->id,
                'name' => $program->name,
                'slug' => $program->slug,
                'description' => $program->description,
            ],
            'batches' => $batches,
            'batches_count' => $batches->count(),
            'enrollments' => $enrollments,
            'enrollments_count' => $enrollments->count(),
            'overview' => $progressService->programOverview($program),
        ]);
    }

    /**
     * Participant search for the enrollment picker.
     *
     * Returns up to 20 participants matching the query. Matches
     * on first name, last name, email, or contact number. Empty
     * query returns the first 20 by last name.
     *
     * Each result carries `already_enrolled: true|false` so the
     * UI can grey out participants already in this program.
     *
     * JSON response, not Inertia. The picker is a debounced
     * type-ahead; it cannot use the partial-reload pattern.
     */
    public function participantSearch(
        Request $request,
        DiscipleshipProgram $program,
    ): JsonResponse {
        $q = trim((string) $request->query('q', ''));

        $query = Participant::query();

        if ($q !== '') {
            $like = '%'.$q.'%';
            $query->where(function ($inner) use ($like): void {
                $inner->where('first_name', 'like', $like)
                    ->orWhere('last_name', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('contact_number', 'like', $like);
            });
        }

        $alreadyEnrolledIds = DiscipleshipEnrollment::query()
            ->where('program_id', $program->id)
            ->pluck('participant_id')
            ->all();

        $participants = $query
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->limit(20)
            ->get()
            ->map(fn (Participant $p) => [
                'id' => $p->id,
                'full_name' => $p->fullName(),
                'email' => $p->email,
                'contact_number' => $p->contact_number,
                'already_enrolled' => in_array($p->id, $alreadyEnrolledIds, true),
            ]);

        return response()->json([
            'participants' => $participants,
        ]);
    }
}
