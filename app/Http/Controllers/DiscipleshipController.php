<?php

namespace App\Http\Controllers;

use App\Enums\DiscipleshipEnrollmentStatus;
use App\Models\DiscipleshipBatch;
use App\Models\DiscipleshipEnrollment;
use App\Models\DiscipleshipProgram;
use App\Models\Participant;
use App\Services\Discipleship\DiscipleshipProgressService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Discipleship module -- landing, per-program view (with search,
 * filter, and CSV export), and the participant search endpoint
 * used by the enrollment dialog.
 *
 * Programs are seeded, not created through the UI. There is no
 * create, update, or destroy path for programs. If the client
 * later adds a program, the change point is
 * DiscipleshipProgramSeeder.
 *
 * Phase 5A. 5B added batches. 5C added enrollments to show() and
 * the participantSearch() endpoint. 5D added sessions (separate
 * controller). 5E added attendance (separate controller). 5F
 * added progress and the overview card. 5G adds search, filter,
 * and CSV export.
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
     * Per-program view: header, batches, enrollments, overview.
     *
     * Query params:
     *   q       search across participant name/email/contact
     *   status  filter by enrollment status (or 'all')
     *   batch   filter by batch_id (or 'all'/'unassigned')
     *
     * The `counts` payload reflects the current q + batch scope,
     * not the status filter -- so the operator sees "how many of
     * each status match my current search."
     */
    public function show(Request $request, DiscipleshipProgram $program): Response
    {
        $progressService = app(DiscipleshipProgressService::class);

        $q = trim((string) $request->query('q', ''));
        $statusFilter = (string) $request->query('status', '');
        $batchFilter = (string) $request->query('batch', '');

        $batches = $program->batches()
            ->orderBy('start_date')
            ->orderBy('id')
            ->get()
            ->map(fn (DiscipleshipBatch $b) => [
                'id' => $b->id,
                'name' => $b->name,
                'start_date' => $b->start_date->toDateString(),
            ]);

        // Counts: apply q and batch, ignore status. Gives the
        // filter chips meaningful counts under the current search.
        $counts = $this->buildCounts($program, $q, $batchFilter);

        // Enrollments: apply q, status, batch.
        $enrollmentsQuery = $this->buildEnrollmentsQuery($program, $q, $statusFilter, $batchFilter);
        $enrollments = $enrollmentsQuery
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
            'counts' => $counts,
            'filters' => [
                'q' => $q,
                'status' => $statusFilter === '' ? 'all' : $statusFilter,
                'batch' => $batchFilter === '' ? 'all' : $batchFilter,
            ],
        ]);
    }

    /**
     * CSV export of the filtered enrollment list.
     *
     * Same q/status/batch filters as show(). Streamed so a large
     * program does not load the entire CSV into memory.
     */
    public function exportCsv(Request $request, DiscipleshipProgram $program): StreamedResponse
    {
        $progressService = app(DiscipleshipProgressService::class);

        $q = trim((string) $request->query('q', ''));
        $statusFilter = (string) $request->query('status', '');
        $batchFilter = (string) $request->query('batch', '');

        $enrollments = $this->buildEnrollmentsQuery($program, $q, $statusFilter, $batchFilter)
            ->with([
                'participant:id,first_name,last_name,email,contact_number',
                'batch:id,name',
                'program',
            ])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        $filename = 'discipleship-'.$program->slug.'-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($enrollments, $progressService): void {
            $out = fopen('php://output', 'w');

            fputcsv($out, [
                'Participant',
                'Contact',
                'Batch',
                'Status',
                'Enrolled At',
                'Attended',
                'Applicable',
                'Percentage',
            ]);

            foreach ($enrollments as $e) {
                $progress = $progressService->progress($e);
                fputcsv($out, [
                    $e->participant?->fullName() ?? '',
                    $e->participant?->email ?? $e->participant?->contact_number ?? '',
                    $e->batch?->name ?? '',
                    $e->status->label(),
                    $e->enrolled_at?->toDateString() ?? '',
                    (string) $progress['attended'],
                    (string) $progress['applicable'],
                    $progress['percentage'] !== null ? $progress['percentage'].'%' : '',
                ]);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
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

        $query = Participant::query()->whereNull('provisional_event_id');

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

    /**
     * Base query with q and batch filters. No status filter --
     * used by buildCounts() so statuses remain separate.
     */
    private function buildFilteredQuery(
        DiscipleshipProgram $program,
        string $q,
        string $batchFilter,
    ): Builder {
        $query = DiscipleshipEnrollment::query()
            ->where('program_id', $program->id);

        if ($q !== '') {
            $like = '%'.$q.'%';
            $query->whereHas('participant', function ($inner) use ($like): void {
                $inner->where('first_name', 'like', $like)
                    ->orWhere('last_name', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('contact_number', 'like', $like);
            });
        }

        if ($batchFilter !== '' && $batchFilter !== 'all') {
            if ($batchFilter === 'unassigned') {
                $query->whereNull('batch_id');
            } else {
                $query->where('batch_id', (int) $batchFilter);
            }
        }

        return $query;
    }

    /**
     * Enrollment query with q, status, and batch applied.
     */
    private function buildEnrollmentsQuery(
        DiscipleshipProgram $program,
        string $q,
        string $statusFilter,
        string $batchFilter,
    ): Builder {
        $query = $this->buildFilteredQuery($program, $q, $batchFilter);

        if ($statusFilter !== '' && $statusFilter !== 'all') {
            $query->where('status', $statusFilter);
        }

        return $query;
    }

    /**
     * Per-status counts under the current q and batch scope.
     *
     * @return array<string, int>
     */
    private function buildCounts(
        DiscipleshipProgram $program,
        string $q,
        string $batchFilter,
    ): array {
        $base = $this->buildFilteredQuery($program, $q, $batchFilter);

        $counts = ['all' => (clone $base)->count()];

        foreach (DiscipleshipEnrollmentStatus::cases() as $status) {
            $counts[$status->value] = (clone $base)->where('status', $status->value)->count();
        }

        return $counts;
    }
}
