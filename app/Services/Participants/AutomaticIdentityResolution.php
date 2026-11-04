<?php

namespace App\Services\Participants;

use App\Enums\RegistrationStatus;
use App\Models\Event;
use App\Models\Participant;
use App\Models\Registration;
use App\Services\Church\RegistrationBridge;
use App\Services\RegistrationConfirmationService;
use Illuminate\Support\Facades\DB;

/** Uncertain identities are accepted separately, never merged using a similarity score. */
class AutomaticIdentityResolution
{
    public const VERSION = 'automatic-v1';

    /** @param array<string, mixed> $context */
    public function record(Registration $registration, ?string $action = null, ?string $reason = null, array $context = []): void
    {
        $registration->load('participant', 'event');
        $review = app(DuplicateReview::class);
        $identity = $review->identity($registration);
        // Optional public contact snapshots have not necessarily been verified.
        $identity['email'] = $registration->participant->email;
        $identity['contact_number'] = $registration->participant->contact_number;
        $candidates = $review->findCandidates($registration->event, $identity,
            excludeRegistrationId: $registration->id, excludeParticipantId: $registration->participant_id);
        $reused = $registration->participant->registrations()->where('id', '!=', $registration->id)->exists();
        $action ??= $reused ? 'identity_reused' : ($candidates === [] ? 'new_identity' : 'kept_separate');
        $reason ??= match ($action) {
            'identity_reused' => 'One exact identity matched. Existing participant history was preserved.',
            'kept_separate' => 'Shared contacts or similar details do not establish identity. Accepted separately.',
            default => 'No unambiguous existing identity matched. Accepted as a separate participant.',
        };
        DB::table('registration_identity_decisions')->insert([
            'event_id' => $registration->event_id,
            'registration_id' => $registration->id,
            'action' => $action,
            'rule_version' => self::VERSION,
            'reason' => $reason,
            'evidence' => json_encode([
                'participant_id' => $registration->participant_id,
                'pool' => $registration->participant->pool,
                'context' => $context,
                'status' => $registration->registration_status->value,
                'duplicate_of_registration_id' => $registration->duplicate_of_registration_id,
                'candidates' => array_map(fn (array $candidate): array => [
                    'participant_id' => $candidate['participant']->id,
                    'reasons' => $candidate['reasons'],
                ], $candidates),
            ], JSON_THROW_ON_ERROR),
            'created_at' => now(),
        ]);
    }

    /** Release legacy holds once; retain all original records and attendance history. */
    public function resolveHeld(int $registrationId): bool
    {
        $eventId = Registration::query()->whereKey($registrationId)->value('event_id');
        if ($eventId === null) {
            return false;
        }

        return DB::transaction(function () use ($eventId, $registrationId): bool {
            $event = Event::query()->whereKey($eventId)->lockForUpdate()->firstOrFail();
            Participant::lockIdentityPool($event->event_kind);
            $row = Registration::query()->lockForUpdate()->find($registrationId);
            if ($row === null || ! $row->isHeld()) {
                return false;
            }
            $row->load('participant');
            if ($row->participant->pool !== $event->event_kind) {
                throw new \DomainException('Cannot resolve an identity from a different participant pool.');
            }
            $review = app(DuplicateReview::class);
            $identity = $review->identity($row);
            $identity['email'] = $row->participant->email;
            $identity['contact_number'] = $row->participant->contact_number;
            $exact = $review->findExact($event, $identity, $row->id, trustedContactsOnly: true);
            // A legacy row with attendance is preserved as active, never silently discarded.
            $duplicate = $exact !== null && ! $exact->isHeld()
                && $exact->duplicate_of_registration_id !== $row->id && ! $row->attendance()->exists();
            $before = $row->getAttributes();
            $row->forceFill([
                'registration_status' => $duplicate ? RegistrationStatus::Duplicate : RegistrationStatus::Confirmed,
                'duplicate_of_registration_id' => $duplicate ? $exact->id : null,
                'held_candidate_registration_id' => null,
                'held_reason' => null,
                'resolved_by' => null,
                'resolved_at' => now(),
                'attendance_code' => $row->attendance_code ?? ($duplicate ? null : RegistrationBridge::code($event)),
                'resolution_action' => $duplicate ? 'automatic_duplicate' : 'automatic_separate',
            ])->saveQuietly();
            $this->record($row, $duplicate ? 'legacy_duplicate' : 'legacy_released',
                $duplicate ? 'Exact registration already exists; original held record retained as a duplicate.'
                    : 'Legacy hold released separately; uncertain identities and existing attendance were preserved.',
                ['before' => $before, 'after' => $row->getAttributes()]);
            if (! $duplicate) {
                app(RegistrationConfirmationService::class)->sendAfterCommit($row);
            }

            return true;
        }, 3);
    }
}
