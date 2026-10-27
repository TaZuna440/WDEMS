<?php

namespace App\Http\Controllers;

use App\Enums\RegistrationStatus;
use App\Models\Event;
use App\Models\Participant;
use App\Models\Registration;
use App\Services\Participants\DuplicateReview;
use App\Services\RegistrationConfirmationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ParticipantReviewController extends Controller
{
    private function formatBirthday(mixed $birthday): ?string
    {
        if ($birthday === null) {
            return null;
        }

        if ($birthday instanceof \DateTimeInterface) {
            return $birthday->format('Y-m-d');
        }

        if (is_string($birthday)) {
            return Carbon::parse($birthday)->toDateString();
        }

        return null;
    }

    public function search(Request $request, Event $event, DuplicateReview $review): JsonResponse
    {
        Participant::assertPool($event->event_kind);
        $data = $request->validate(['q' => ['required', 'string', 'min:2', 'max:160']]);
        $query = mb_strtolower(trim($data['q']));
        $rows = $review->registrations($event)->filter(function ($r) use ($query, $review) {
            $name = $review->name($review->identity($r));

            return str_contains($name, $query) || $review->similar($name, $query);
        })->take(10)->map(fn ($r) => $review->staffRow($r))->values();

        return response()->json(['matches' => $rows]);
    }

    public function audit(Event $event, DuplicateReview $review): Response
    {
        Participant::assertPool($event->event_kind);

        $held = Registration::query()
            ->where('event_id', $event->id)
            ->where('registration_status', RegistrationStatus::Held)
            ->whereHas('participant', fn ($query) => $query->where('pool', $event->event_kind))
            ->with(['participant', 'heldCandidate.participant'])
            ->orderByDesc('id')
            ->get()
            ->map(function (Registration $r) use ($event, $review): array {
                $heldCandidate = $r->heldCandidate;
                $candidates = $review->findCandidates(
                    $event,
                    $review->identity($r),
                    excludeRegistrationId: $r->id,
                    excludeParticipantId: $r->participant_id,
                );

                return [
                    'id' => $r->id,
                    'created_at' => $r->created_at?->toIso8601String(),
                    'held_reason' => $r->held_reason,
                    'participant' => [
                        'id' => $r->participant?->id,
                        'first_name' => $r->participant?->first_name,
                        'middle_name' => $r->participant?->middle_name,
                        'last_name' => $r->participant?->last_name,
                        'suffix' => $r->participant?->suffix,
                        'email' => $r->submitted_email ?? $r->participant?->email,
                        'contact_number' => $r->submitted_contact_number ?? $r->participant?->contact_number,
                        'birthday' => $this->formatBirthday($r->participant?->birthday),
                    ],
                    'participant_type' => $r->participant_type,
                    'guardian_name' => $r->guardian_name,
                    'guardian_contact' => $r->guardian_contact,
                    'candidates' => array_map(fn (array $candidate): array => [
                        'participant' => [
                            'id' => $candidate['participant']->id,
                            'first_name' => $candidate['participant']->first_name,
                            'middle_name' => $candidate['participant']->middle_name,
                            'last_name' => $candidate['participant']->last_name,
                            'suffix' => $candidate['participant']->suffix,
                            'email' => $candidate['participant']->email,
                            'contact_number' => $candidate['participant']->contact_number,
                            'birthday' => $this->formatBirthday($candidate['participant']->birthday),
                        ],
                        'registration_id' => $candidate['registration']?->id,
                        'reasons' => $candidate['reasons'],
                        'score' => $candidate['score'],
                        'strong' => $candidate['strong'],
                    ], $candidates),
                    'candidate' => $heldCandidate ? [
                        'id' => $heldCandidate->id,
                        'created_at' => $heldCandidate->created_at?->toIso8601String(),
                        'participant' => [
                            'id' => $heldCandidate->participant?->id,
                            'first_name' => $heldCandidate->participant?->first_name,
                            'middle_name' => $heldCandidate->participant?->middle_name,
                            'last_name' => $heldCandidate->participant?->last_name,
                            'suffix' => $heldCandidate->participant?->suffix,
                            'email' => $heldCandidate->submitted_email ?? $heldCandidate->participant?->email,
                            'contact_number' => $heldCandidate->submitted_contact_number ?? $heldCandidate->participant?->contact_number,
                            'birthday' => $this->formatBirthday($heldCandidate->participant?->birthday),
                        ],
                        'participant_type' => $heldCandidate->participant_type,
                        'guardian_name' => $heldCandidate->guardian_name,
                        'guardian_contact' => $heldCandidate->guardian_contact,
                    ] : null,
                ];
            });

        return Inertia::render('events/duplicates', [
            'event' => ['id' => $event->id, 'event_name' => $event->event_name, 'event_kind' => $event->event_kind],
            'pairs' => $review->audit($event),
            'held_registrations' => $held,
            'review_status' => session('status'),
        ]);
    }

    public function resolve(Request $request, Event $event, Registration $registration, DuplicateReview $review): RedirectResponse
    {
        abort_unless($registration->event_id === $event->id, 404);
        $data = $request->validate([
            'action' => ['required', 'in:confirm_duplicate,confirm_separate,correct_details,link_candidate,remove'],
            'candidate_participant_id' => ['required_if:action,link_candidate', 'nullable', 'integer'],
            'reason' => ['nullable', 'string', 'max:1000'],
            'first_name' => ['required_if:action,correct_details', 'string', 'min:2', 'max:50'],
            'last_name' => ['required_if:action,correct_details', 'string', 'min:2', 'max:50'],
            'middle_name' => ['nullable', 'string', 'max:50'],
            'suffix' => ['nullable', 'string', 'max:10'],
            'email' => ['nullable', 'email', 'max:255'],
            'contact_number' => ['nullable', 'regex:/\\A(?:09|\\+639)[0-9]{9}\\z/'],
            'birthday' => ['nullable', 'date', 'before_or_equal:today'],
        ]);
        $message = DB::transaction(function () use ($event, $registration, $request, $data, $review) {
            Event::whereKey($event->id)->lockForUpdate()->firstOrFail();
            /** @var Registration $row */
            $row = Registration::whereKey($registration->id)->lockForUpdate()->firstOrFail();
            abort_unless($row->isHeld(), 422, 'Only held submissions can be resolved.');
            abort_unless($row->participant->pool === $event->event_kind, 422, 'Participant belongs to the wrong pool.');
            $snapshot = fn ($r) => ['registration' => $r->attributesToArray(), 'participant' => $r->participant->attributesToArray()];
            $before = $snapshot($row);
            $action = $data['action'];
            $reason = $data['reason'] ?? str_replace('_', ' ', $action);
            $message = 'Registration review updated.';
            if ($action === 'correct_details') {
                // A correction to this held submission must not rewrite another event's person.
                $person = $row->participant;
                if ($person->registrations()->where('id', '!=', $row->id)->exists()) {
                    $person = $person->replicate();
                    $person->save();
                    $row->participant_id = $person->id;
                }
                $person->fill(collect($data)->only(['first_name', 'last_name', 'middle_name', 'suffix', 'email', 'contact_number', 'birthday'])->all())->save();
                $row->setRelation('participant', $person);
                if (array_key_exists('email', $data)) {
                    $row->submitted_email = $data['email'];
                }
                if (array_key_exists('contact_number', $data)) {
                    $row->submitted_contact_number = $data['contact_number'];
                }
            }
            if ($action === 'link_candidate') {
                $candidates = $review->findCandidates(
                    $event,
                    $review->identity($row),
                    excludeRegistrationId: $row->id,
                    excludeParticipantId: $row->participant_id,
                );
                $selected = collect($candidates)->first(
                    fn (array $candidate): bool => $candidate['participant']->id === (int) $data['candidate_participant_id'],
                );
                abort_unless($selected !== null, 422, 'Choose one of the listed participant candidates.');

                $person = Participant::query()->where('pool', $event->event_kind)
                    ->lockForUpdate()->findOrFail((int) $data['candidate_participant_id']);
                $existing = Registration::query()->where('event_id', $event->id)
                    ->where('participant_id', $person->id)
                    ->whereKeyNot($row->id)
                    ->lockForUpdate()
                    ->first();
                abort_unless($existing === null || $existing->isActive(), 422, 'The selected participant already has a registration that is not available for linking.');
                if ($existing !== null) {
                    $row->registration_status = RegistrationStatus::Duplicate;
                    $row->duplicate_of_registration_id = $existing->id;
                    $row->held_candidate_registration_id = $existing->id;
                    $row->held_reason = implode(', ', $selected['reasons']);
                    $message = "Submission marked as a duplicate of the selected participant's registration.";
                } else {
                    $row->participant_id = $person->id;
                    $row->setRelation('participant', $person);
                    $row->registration_status = RegistrationStatus::Confirmed;
                    $row->duplicate_of_registration_id = null;
                    $row->held_candidate_registration_id = null;
                    $row->held_reason = null;
                    $message = 'Submission linked to the selected participant and released.';
                }
            } elseif (in_array($action, ['confirm_separate', 'correct_details'], true)) {
                $identity = $review->identity($row);
                $exact = $review->findExact($event, $identity, $row->id);
                $candidates = $review->findCandidates(
                    $event,
                    $identity,
                    excludeRegistrationId: $row->id,
                    excludeParticipantId: $row->participant_id,
                );
                $primaryCandidate = $candidates[0] ?? null;
                if ($exact || ($action === 'correct_details' && $review->shouldHoldCandidates($candidates))) {
                    $row->held_candidate_registration_id = $exact?->id ?? $primaryCandidate['registration']?->id;
                    $row->held_reason = $exact ? 'Exact identity already registered' : implode(', ', $primaryCandidate['reasons']);
                    $reason .= ': still held — '.$row->held_reason;
                    $message = 'Submission remains held: '.$row->held_reason.'.';
                } else {
                    $row->registration_status = RegistrationStatus::Confirmed;
                    $row->held_candidate_registration_id = null;
                    $row->held_reason = null;
                    // No attendance row means Unmarked. Do not invent a check-in.
                }
            } elseif ($action === 'confirm_duplicate') {
                /** @var Registration|null $master */
                $master = Registration::active()->where('event_id', $event->id)->whereKey($row->held_candidate_registration_id)->lockForUpdate()->first();
                abort_unless($master && $master->participant->pool === $event->event_kind, 422, 'An active master registration is required.');
                $row->registration_status = RegistrationStatus::Duplicate;
                $row->duplicate_of_registration_id = $master->id;
            } else {
                $row->registration_status = RegistrationStatus::Removed;
            }
            $row->resolved_by = $request->user()->id;
            $row->resolved_at = now();
            $row->resolution_action = $action;
            $row->save();
            DB::table('registration_review_resolutions')->insert([
                'registration_id' => $row->id, 'event_id' => $event->id, 'actor_id' => $request->user()->id,
                'action' => $action, 'reason' => $reason, 'created_at' => now(),
                'before_snapshot' => json_encode($before, JSON_THROW_ON_ERROR),
                'after_snapshot' => json_encode($snapshot($row), JSON_THROW_ON_ERROR),
            ]);

            if ($row->isActive()) {
                app(RegistrationConfirmationService::class)->sendAfterCommit($row);
            }

            return $message;
        });

        return back()->with('status', $message);
    }
}
