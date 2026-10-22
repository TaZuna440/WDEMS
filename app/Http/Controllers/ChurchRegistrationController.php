<?php

namespace App\Http\Controllers;

use App\Concerns\ContactAndAddressQualityRules;
use App\Models\Event;
use App\Models\FellowshipEvent;
use App\Models\FellowshipGroup;
use App\Models\Participant;
use App\Models\Registration;
use App\Services\Church\RegistrationBridge;
use App\Services\Participants\DuplicateReview;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ChurchRegistrationController extends Controller
{
    use ContactAndAddressQualityRules;

    public function index(Event $event): Response
    {
        $this->ensureChurch($event);
        $groups = FellowshipEvent::query()
            ->where('registration_event_id', $event->id)
            ->first()?->groups()
            ->orderBy('display_order')->orderBy('id')->get(['id', 'name']) ?? collect();

        return Inertia::render('events/church/registrations/index', [
            'event' => [...$event->only('id', 'event_name', 'church_category', 'registration_slug'), 'church_registration_open' => (bool) $event->church_registration_open],
            'groups' => $groups->map(fn (FellowshipGroup $group): array => ['id' => $group->id, 'name' => $group->name])->values(),
            'message' => session('success'),
            'public_url' => $event->registration_slug ? route('public-registration.show', ['event' => $event->registration_slug]) : null,
            'registrations' => Registration::where('event_id', $event->id)
                ->whereHas('participant', fn ($query) => $query->where('pool', 'church'))
                ->latest()->get()->map(function (Registration $row): array {
                    $person = Participant::findOrFail($row->participant_id);

                    return [
                        'id' => $row->id,
                        'history_url' => route('church.participants.history', $person),
                        'participant' => [...$person->only('first_name', 'middle_name', 'last_name', 'suffix', 'email', 'contact_number', 'address', 'age'),
                            'email' => $row->submitted_email ?? $person->email,
                            'contact_number' => $row->submitted_contact_number ?? $person->contact_number],
                        'source' => $row->source === 'manual' ? 'Manual' : 'Link',
                        'birthday' => $person->getRawOriginal('birthday'),
                        'participant_type' => $row->participant_type,
                        'fellowship_group_id' => $row->fellowship_group_id,
                        'guardian_name' => $row->guardian_name,
                        'guardian_contact' => $row->guardian_contact,
                        'registration_status' => $row->getRawOriginal('registration_status'),
                        'attendance_code' => $row->isActive() ? $row->attendance_code : null,
                        'held_reason' => $row->held_reason,
                    ];
                }),
        ]);
    }

    public function store(Request $request, Event $event, DuplicateReview $review): RedirectResponse
    {
        $this->ensureChurch($event);
        $data = $this->validated($request);
        DB::transaction(function () use ($event, $data, $request, $review): void {
            Event::whereKey($event->id)->lockForUpdate()->firstOrFail();
            $this->assertFellowshipGroupBelongsToEvent($event, $data['fellowship_group_id'] ?? null);
            $this->rejectExact($review, $event, $data);
            $person = $this->resolve($data);
            $candidate = $review->findCandidate($event, $data);
            $event->registrations()->create([
                ...$this->registrationData($data),
                'participant_id' => $person->id,
                'registration_status' => $candidate ? 'held' : 'confirmed',
                'held_candidate_registration_id' => $candidate['registration']->id ?? null,
                'held_reason' => $candidate ? implode(', ', $candidate['reasons']) : null,
                'source' => 'manual', 'encoded_by' => $request->user()->id,
                'contact_attested_at' => now(), 'registered_at' => now(), 'registration_date' => now(),
                'attendance_code' => $this->code($event),
            ]);
        });

        return back()->with('success', 'Submission saved. Held submissions require organizer review before attendance.');
    }

    public function update(Request $request, Event $event, Registration $registration, DuplicateReview $review): RedirectResponse
    {
        $this->ensureChurch($event);
        abort_unless($registration->event_id === $event->id, 404);
        $data = $this->validated($request);
        DB::transaction(function () use ($event, $registration, $data, $review): void {
            Event::whereKey($event->id)->lockForUpdate()->firstOrFail();
            $this->assertFellowshipGroupBelongsToEvent($event, $data['fellowship_group_id'] ?? null);
            $row = Registration::whereKey($registration->id)->lockForUpdate()->firstOrFail();
            abort_unless($row->isActive(), 422, 'Use organizer review for held submissions.');
            $this->rejectExact($review, $event, $data, $row->id);
            $person = $this->resolve($data, $row);
            // Identity corrections reassign this registration, never rewrite a shared person.
            $candidate = $review->findCandidate($event, $data, $row->id);
            if ($candidate && $row->attendance()->exists()) {
                throw ValidationException::withMessages(['identity' => 'This correction needs duplicate review but attendance is already recorded. Clear attendance before correcting the identity.']);
            }
            $row->update([
                ...$this->registrationData($data), 'participant_id' => $person->id,
                'registration_status' => $candidate ? 'held' : 'confirmed',
                'held_candidate_registration_id' => $candidate['registration']->id ?? null,
                'held_reason' => $candidate ? implode(', ', $candidate['reasons']) : null,
            ]);
        });

        return back()->with('success', 'Registration updated and duplicate checks completed.');
    }

    public function destroy(Event $event, Registration $registration): RedirectResponse
    {
        $this->ensureChurch($event);
        abort_unless($registration->event_id === $event->id, 404);
        DB::transaction(function () use ($event, $registration): void {
            Event::whereKey($event->id)->lockForUpdate()->firstOrFail();
            $row = Registration::whereKey($registration->id)->lockForUpdate()->firstOrFail();
            abort_unless($row->isActive(), 422, 'Use organizer review to remove held submissions and retain their audit trail.');
            if (Registration::where('duplicate_of_registration_id', $row->id)->exists()
                || Registration::held()->where('held_candidate_registration_id', $row->id)->exists()) {
                throw ValidationException::withMessages(['identity' => 'This registration is referenced by duplicate reviews and must be retained.']);
            }
            $row->delete(); // Foreign keys remove attendance and answers, never the participant.
        });

        return back()->with('success', 'Registration removed.');
    }

    private function ensureChurch(Event $event): void
    {
        abort_unless($event->event_kind === Event::KIND_CHURCH, 404);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'first_name' => ['required', 'string', 'min:2', 'max:50', "regex:/^[\\pL\\pM '\\-]+$/u"],
            'last_name' => ['required', 'string', 'min:2', 'max:50', "regex:/^[\\pL\\pM '\\-]+$/u"],
            'middle_name' => ['nullable', 'string', 'max:50', "regex:/^[\\pL\\pM . '\\-]+$/u"],
            'suffix' => ['nullable', 'string', 'max:10'],
            'email' => ['nullable', 'email', 'max:255'],
            'contact_number' => ['nullable', 'string', 'max:20', ...$this->contactNumberQualityRules()],
            'birthday' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today', 'after_or_equal:'.now()->subYears(120)->toDateString()],
            'age' => ['nullable', 'integer', 'min:0', 'max:120'],
            'address' => ['nullable', 'string', 'max:500', ...$this->addressQualityRules()],
            'participant_type' => ['required', 'in:Member,First-Timer,Guest'],
            'guardian_name' => ['nullable', 'string', 'max:100'],
            'guardian_contact' => ['nullable', 'string', 'max:20', ...$this->contactNumberQualityRules()],
            'fellowship_group_id' => ['nullable', 'integer', 'exists:fellowship_groups,id'],
        ]);
    }

    private function assertFellowshipGroupBelongsToEvent(Event $event, mixed $groupId): void
    {
        if ($groupId === null) {
            return;
        }

        $legacyEvent = FellowshipEvent::query()->where('registration_event_id', $event->id)->first();
        if ($legacyEvent === null || ! $legacyEvent->groups()->whereKey($groupId)->exists()) {
            throw ValidationException::withMessages(['fellowship_group_id' => 'Choose a group belonging to this Fellowship event.']);
        }
    }

    /** @param array<string, mixed> $data */
    private function resolve(array $data, ?Registration $registration = null): Participant
    {
        $identity = array_intersect_key($data, array_flip(['first_name', 'middle_name', 'last_name', 'suffix', 'email', 'contact_number', 'birthday', 'age', 'address']));
        if (! empty($identity['birthday'])) {
            $identity['age'] = null;
        }
        // The church spec permits contactless staff adds with a visible warning.
        // They are never resolved by name alone or linked automatically.
        if ($registration !== null) {
            $current = Participant::findOrFail($registration->participant_id);
            $sameName = Participant::query()->matchingName('church', $data['first_name'], $data['last_name'], $data['middle_name'] ?? null, $data['suffix'] ?? null)->whereKey($current->id)->exists();
            $sameContact = ($current->email ?? '') === mb_strtolower($data['email'] ?? '')
                && ($current->contact_number ?? '') === ($data['contact_number'] ?? '');
            if ($sameName && $sameContact) {
                $current->fill($identity);
                if (! $current->isDirty()) {
                    return $current;
                }
                $shared = $current->registrations()->where('id', '!=', $registration->id)->exists();
                foreach (['members', 'first_timers', 'service_attendance_members', 'service_attendance_first_timers', 'fellowship_attendances', 'discipleship_enrollments'] as $table) {
                    $shared = $shared || DB::table($table)->where('participant_id', $current->id)->exists();
                }
                if ($shared) {
                    $current = $current->replicate();
                }
                $current->save();

                return $current;
            }
        }
        if (empty($data['email']) && Participant::normalizeContactNumber($data['contact_number'] ?? null) === null) {
            return Participant::create(['pool' => 'church', ...$identity]);
        }

        return Participant::resolveFrom('church', $data['email'] ?? null, $data['contact_number'] ?? null, $identity);
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function registrationData(array $data): array
    {
        return [
            'participant_type' => $data['participant_type'], 'guardian_name' => $data['guardian_name'] ?? null,
            'guardian_contact' => $data['guardian_contact'] ?? null,
            'fellowship_group_id' => $data['fellowship_group_id'] ?? null,
            'submitted_email' => $data['email'] ?? null, 'submitted_contact_number' => $data['contact_number'] ?? null,
        ];
    }

    /** @param array<string, mixed> $data */
    private function rejectExact(DuplicateReview $review, Event $event, array $data, ?int $exclude = null): void
    {
        if ($review->findExact($event, $data, $exclude)) {
            throw ValidationException::withMessages(['identity' => 'This person already has a submission for this event.']);
        }
    }

    private function code(Event $event): string
    {
        return RegistrationBridge::code($event);
    }
}
