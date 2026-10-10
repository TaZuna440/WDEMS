<?php

namespace App\Http\Controllers;

use App\Enums\EventStatus;
use App\Http\Requests\EditParticipantRequest;
use App\Http\Requests\FlagRegistrationRequest;
use App\Http\Requests\SaveRegistrationNoteRequest;
use App\Models\Event;
use App\Models\Participant;
use App\Models\Registration;
use App\Models\RegistrationFieldResponse;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RegistrationMonitorController extends Controller
{
    /**
     * Landing: every event currently accepting registrations.
     *
     * Sorted by most recent submission first — the event that just
     * received a registration rises to the top. Events with no
     * submissions sort by registration_start descending.
     *
     * Each card carries the total registration count and the
     * rolling 60-minute rate. The per-event page carries the 15-minute
     * rate; the landing page omits it to keep the card readable.
     */
    public function index(): Response
    {
        $events = Event::query()
            ->where('status', EventStatus::RegistrationOpen)
            ->get();

        $stats = Registration::query()
            ->whereIn('event_id', $events->pluck('id'))
            ->selectRaw('event_id, MAX(created_at) as most_recent, COUNT(*) as total')
            ->groupBy('event_id')
            ->get()
            ->keyBy('event_id');

        $cutoff = CarbonImmutable::now()->subHour();

        $mapped = $events
            ->map(function (Event $event) use ($stats, $cutoff) {
                $row = $stats->get($event->id);
                $mostRecent = $row?->most_recent;

                $lastHour = Registration::where('event_id', $event->id)
                    ->where('created_at', '>=', $cutoff)
                    ->count();

                return [
                    'id' => $event->id,
                    'event_name' => $event->event_name,
                    'event_date' => $event->event_date?->toDateString(),
                    'venue' => $event->venue,
                    'status' => $event->status->value,
                    'status_label' => $event->status->label(),
                    'total' => (int) ($row?->total ?? 0),
                    'last_hour' => $lastHour,
                    'most_recent_iso' => $mostRecent,
                    'most_recent_human' => $mostRecent
                        ? CarbonImmutable::parse($mostRecent)->diffForHumans()
                        : null,
                    '_sort' => $mostRecent
                        ?? $event->registration_start?->toIso8601String()
                        ?? '',
                ];
            })
            ->sortByDesc('_sort')
            ->values()
            ->map(function (array $row) {
                unset($row['_sort']);

                return $row;
            });

        return Inertia::render('registrations/monitor/index', [
            'events' => $mapped,
        ]);
    }

    /**
     * Per-event feed: last 100 registrations, rate windows, and the
     * event's registration state.
     *
     * Reachable when the event is registration_open OR
     * registration_closed. The second case is the read-only snapshot
     * after close (§11 of the monitoring spec). Draft, ongoing,
     * completed, and cancelled return 404 — the monitor is not the
     * right surface for those.
     *
     * Adds two per-registration signals the spec calls for:
     *
     *   - `has_shared_phone` — true when this participant's RAW
     *     contact_number string matches another participant
     *     registered for the same event. The identity model enforces
     *     a UNIQUE constraint on contact_number_normalized, so two
     *     participants cannot share a normalized mobile — the
     *     detector is therefore scoped to raw strings that do not
     *     normalize (typically landlines shared by family members).
     *
     *   - `custom_fields` — array of { label, value } for the
     *     participant's custom field answers on this registration.
     *     Ordered by the field's display_order.
     *
     * Also passes per-registration `flagged_at` and `notes` so the
     * monitor can render the flagged badge and the note indicator.
     */
    public function show(Event $event): Response
    {
        if (! in_array($event->status, [
            EventStatus::RegistrationOpen,
            EventStatus::RegistrationClosed,
        ], true)) {
            abort(404);
        }

        $isOpen = $event->status === EventStatus::RegistrationOpen;

        $now = CarbonImmutable::now();
        $cutoffHour = $now->subHour();
        $cutoff15 = $now->subMinutes(15);

        $total = Registration::where('event_id', $event->id)->count();

        $lastHour = Registration::where('event_id', $event->id)
            ->where('created_at', '>=', $cutoffHour)
            ->count();

        $last15Min = Registration::where('event_id', $event->id)
            ->where('created_at', '>=', $cutoff15)
            ->count();

        $registrations = Registration::query()
            ->where('event_id', $event->id)
            ->with(['participant' => function ($q) use ($event) {
                $q->withCount([
                    'registrations as other_events_count' => function ($q) use ($event) {
                        $q->where('event_id', '!=', $event->id);
                    },
                ]);
            }])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        // -------- Same-phone detection --------
        $participantIds = $registrations->pluck('participant_id')->all();

        $sharedPhoneIds = [];

        if ($participantIds !== []) {
            $phoneGroups = Participant::query()
                ->whereIn('id', $participantIds)
                ->whereNotNull('contact_number')
                ->where('contact_number', '!=', '')
                ->select('id', 'contact_number')
                ->get()
                ->groupBy('contact_number');

            foreach ($phoneGroups as $group) {
                if ($group->count() > 1) {
                    foreach ($group as $p) {
                        $sharedPhoneIds[(int) $p->id] = true;
                    }
                }
            }
        }

        // -------- Custom field responses --------
        $responsesByRegistration = RegistrationFieldResponse::query()
            ->whereIn('registration_id', $registrations->pluck('id'))
            ->with('registrationField:id,label,display_order')
            ->get()
            ->groupBy('registration_id');

        $mapped = $registrations->map(function (Registration $r) use ($sharedPhoneIds, $responsesByRegistration) {
            $participant = $r->participant;
            $otherCount = (int) ($participant?->other_events_count ?? 0);

            $fieldResponses = $responsesByRegistration
                ->get($r->id, collect())
                ->sortBy(fn ($resp) => $resp->registrationField?->display_order ?? 0)
                ->map(function (RegistrationFieldResponse $resp) {
                    $raw = $resp->value;

                    if (is_string($raw) && str_starts_with($raw, '[')) {
                        $decoded = json_decode($raw, true);
                        if (is_array($decoded)) {
                            $raw = $decoded;
                        }
                    }

                    return [
                        'label' => $resp->registrationField?->label,
                        'value' => $raw,
                    ];
                })
                ->filter(fn (array $f) => $f['label'] !== null)
                ->values()
                ->all();

            return [
                'id' => $r->id,
                'participant' => [
                    'id' => $participant?->id,
                    'full_name' => $participant?->fullName(),
                    'first_name' => $participant?->first_name,
                    'last_name' => $participant?->last_name,
                    'email' => $participant?->email,
                    'contact_number' => $participant?->contact_number,
                    'address' => $participant?->address,
                ],
                'created_at_iso' => $r->created_at?->toIso8601String(),
                'created_at_absolute' => $r->created_at?->format('H:i:s'),
                'created_at_human' => $r->created_at?->diffForHumans(),
                'is_returning' => $otherCount > 0,
                'other_events_count' => $otherCount,
                'has_shared_phone' => $participant !== null && isset($sharedPhoneIds[(int) $participant->id]),
                'custom_fields' => $fieldResponses,
                'is_flagged' => $r->flagged_at !== null,
                'flagged_at' => $r->flagged_at?->toIso8601String(),
                'notes' => $r->notes,
            ];
        });

        return Inertia::render('registrations/monitor/show', [
            'event' => [
                'id' => $event->id,
                'event_name' => $event->event_name,
                'event_date' => $event->event_date?->toDateString(),
                'venue' => $event->venue,
                'status' => $event->status->value,
                'status_label' => $event->status->label(),
                'registration_slug' => $event->registration_slug,
                'registration_start' => $event->registration_start?->toDateTimeString(),
                'registration_end' => $event->registration_end?->toDateTimeString(),
                'is_open' => $isOpen,
                'custom_field_labels' => $event->registrationFields()
                    ->orderBy('display_order')
                    ->pluck('label')
                    ->all(),
            ],
            'stats' => [
                'total' => $total,
                'last_hour' => $lastHour,
                'last_15min' => $last15Min,
            ],
            'registrations' => $mapped,
        ]);
    }

    /**
     * Update a participant's identity and descriptive fields.
     *
     * Overrides first-write-wins deliberately — the organizer is
     * correcting a mistake, not resolving a new registration. The
     * email and phone UNIQUE constraints still apply; collisions are
     * rejected by the request.
     */
    public function editParticipant(
        EditParticipantRequest $request,
        Registration $registration,
    ): RedirectResponse {
        $this->ensureEditableRegistration($registration);

        $validated = $request->validated();

        DB::transaction(function () use ($registration, $validated): void {
            $participant = $registration->participant;

            $participant->fill([
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'email' => $validated['email'] ?? null,
                'contact_number' => $validated['contact_number'] ?? null,
                'address' => $validated['address'] ?? null,
            ]);

            $participant->save();
        });

        return back()->with('success', 'Participant updated.');
    }

    /**
     * Set or clear the flagged state on a registration.
     *
     * Accepts an explicit boolean — not a toggle. See
     * FlagRegistrationRequest for the rationale.
     */
    public function toggleFlag(
        FlagRegistrationRequest $request,
        Registration $registration,
    ): RedirectResponse {
        $this->ensureEditableRegistration($registration);

        $flagged = (bool) $request->validated()['flagged'];

        $registration->update([
            'flagged_at' => $flagged ? now() : null,
        ]);

        return back()->with(
            'success',
            $flagged ? 'Flagged for review.' : 'Flag cleared.',
        );
    }

    /**
     * Save or clear the free-form note on a registration.
     *
     * Whitespace-only input is treated as a clear — the column stores
     * null so the DB reflects "no note" rather than "empty string".
     */
    public function saveNote(
        SaveRegistrationNoteRequest $request,
        Registration $registration,
    ): RedirectResponse {
        $this->ensureEditableRegistration($registration);

        $note = $request->validated()['note'] ?? null;

        if (is_string($note)) {
            $note = trim($note);
            if ($note === '') {
                $note = null;
            }
        }

        $registration->update(['notes' => $note]);

        return back()->with(
            'success',
            $note === null ? 'Note cleared.' : 'Note saved.',
        );
    }

    /**
     * Stream all registrations for the event as a CSV.
     *
     * Columns: six common participant fields, source, registration
     * status, registered_at, then one column per custom field in
     * display_order. Checkbox values (JSON arrays) are joined with
     * ", " into a single cell.
     *
     * UTF-8 BOM is written first so Excel on Windows opens the file
     * without mangling accented characters.
     *
     * No pagination, no filter — the export is the full set. The
     * monitor's live feed caps at 100 for the UI; the export
     * deliberately does not.
     */
    public function exportCsv(Event $event): StreamedResponse
    {
        if (! in_array($event->status, [
            EventStatus::RegistrationOpen,
            EventStatus::RegistrationClosed,
        ], true)) {
            abort(404);
        }

        $fields = $event->registrationFields()
            ->orderBy('display_order')
            ->get();

        $registrations = Registration::query()
            ->where('event_id', $event->id)
            ->with(['participant', 'fieldResponses'])
            ->orderBy('id')
            ->get();

        $headers = [
            'First name',
            'Last name',
            'Email',
            'Contact number',
            'Age',
            'Address',
            'Source',
            'Status',
            'Registered at',
        ];

        foreach ($fields as $field) {
            $headers[] = $field->label;
        }

        $filename = sprintf(
            'registrations-%d-%s.csv',
            $event->id,
            now()->format('Ymd-His'),
        );

        return response()->streamDownload(function () use ($registrations, $headers, $fields): void {
            $out = fopen('php://output', 'w');

            if ($out === false) {
                return;
            }

            // UTF-8 BOM for Excel compatibility.
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, $headers);

            foreach ($registrations as $r) {
                $participant = $r->participant;

                $row = [
                    $participant?->first_name,
                    $participant?->last_name,
                    $participant?->email,
                    $participant?->contact_number,
                    $participant?->age,
                    $participant?->address,
                    $r->source,
                    $r->registration_status?->value,
                    $r->registered_at?->toDateTimeString(),
                ];

                $answersByFieldId = $r->fieldResponses->keyBy('registration_field_id');

                foreach ($fields as $field) {
                    $response = $answersByFieldId->get($field->id);
                    $value = $response?->value;

                    if (is_string($value) && str_starts_with($value, '[')) {
                        $decoded = json_decode($value, true);
                        if (is_array($decoded)) {
                            $value = implode(', ', $decoded);
                        }
                    }

                    $row[] = $value;
                }

                fputcsv($out, $row);
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Gate helper for the three action endpoints. The event must be
     * registration_open or registration_closed. Draft, ongoing,
     * completed, and cancelled reject with 403.
     */
    private function ensureEditableRegistration(Registration $registration): void
    {
        $event = $registration->event;

        if ($event === null) {
            abort(404);
        }

        if (! in_array($event->status, [
            EventStatus::RegistrationOpen,
            EventStatus::RegistrationClosed,
        ], true)) {
            abort(403, 'This registration can no longer be edited.');
        }
    }
}
