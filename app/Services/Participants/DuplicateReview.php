<?php

namespace App\Services\Participants;

use App\Models\Event;
use App\Models\Participant;
use App\Models\Registration;
use Illuminate\Support\Collection;

/** Advisory matches never merge identities or replace the exact composite check. */
class DuplicateReview
{
    public function registrations(Event $event): Collection
    {
        Participant::assertPool($event->event_kind);

        return $event->registrations()->active()->whereHas('participant', fn ($q) => $q->where('pool', $event->event_kind))->with('participant')->orderBy('id')->get();
    }

    public function identity(Registration $registration): array
    {
        $p = $registration->participant;

        return [
            'first_name' => $p->first_name, 'middle_name' => $p->middle_name,
            'last_name' => $p->last_name, 'suffix' => $p->suffix,
            'birthday' => $p->birthday?->toDateString(),
            'email' => $registration->submitted_email ?? $p->email,
            'contact_number' => $registration->submitted_contact_number ?? $p->contact_number,
        ];
    }

    public function name(array $data): string
    {
        $parts = ['first_name', 'middle_name', 'last_name'];
        $name = array_map(fn ($key) => Participant::normalizeName($data[$key] ?? null), $parts);
        $name[] = Participant::normalizeSuffix($data['suffix'] ?? null);

        return implode(' ', array_filter($name));
    }

    public function similar(string $left, string $right): bool
    {
        if ($left === '' || $right === '') {
            return false;
        }
        // Unicode code points rather than byte distances; a single typo in a short name
        // is tolerated, with an 85% threshold for longer names.
        $a = mb_str_split($left);
        $b = mb_str_split($right);
        $previous = range(0, count($b));
        foreach ($a as $i => $char) {
            $current = [$i + 1];
            foreach ($b as $j => $other) {
                $current[] = min($current[$j] + 1, $previous[$j + 1] + 1, $previous[$j] + ($char === $other ? 0 : 1));
            }
            $previous = $current;
        }

        if (end($previous) <= max(1, (int) floor(max(count($a), count($b)) * 0.15))) {
            return true;
        }

        // Token-subset match (Amendment C / D-09):
        // E.g. "EJ Rubi Pomasin" vs "EJ Pomasin" (omitted middle name, addition/omission).
        $tokensA = array_values(array_filter(explode(' ', $left)));
        $tokensB = array_values(array_filter(explode(' ', $right)));
        if (count($tokensA) >= 2 && count($tokensB) >= 2) {
            $diffAB = array_diff($tokensA, $tokensB);
            $diffBA = array_diff($tokensB, $tokensA);
            if (empty($diffAB) || empty($diffBA)) {
                return true;
            }
        }

        return false;
    }

    public function reasons(array $left, array $right): array
    {
        $reasons = [];
        $leftName = $this->name($left);
        $rightName = $this->name($right);
        $similarName = $this->similar($leftName, $rightName);
        if ($leftName !== '' && $leftName === $rightName) {
            $reasons[] = 'Exact name';
        } elseif ($similarName) {
            $reasons[] = 'Similar name';
        }

        foreach (['email' => 'Same email', 'contact_number' => 'Same phone'] as $key => $label) {
            $a = $this->contactKey($key, $left[$key] ?? null);
            $b = $this->contactKey($key, $right[$key] ?? null);
            if ($a !== null && $a === $b) {
                $reasons[] = $label;
            }
        }
        if ($similarName
            && $this->contactKey('birthday', $left['birthday'] ?? null) !== null
            && $this->contactKey('birthday', $left['birthday'] ?? null) === $this->contactKey('birthday', $right['birthday'] ?? null)) {
            $reasons[] = 'Same birthdate';
        }

        return $reasons;
    }

    private function contactKey(string $key, ?string $value): ?string
    {
        if ($key === 'contact_number') {
            return Participant::normalizeContactNumber($value);
        }
        $value = mb_strtolower(trim($value ?? ''));

        return $value !== '' ? $value : null;
    }

    /** Exact composite checks include inactive submissions to prevent re-registration bypasses. */
    public function findExact(Event $event, array $payload, ?int $excludeId = null): ?Registration
    {
        Participant::assertPool($event->event_kind);
        $rows = $event->registrations()->whereHas('participant', fn ($q) => $q->matchingName(
            $event->event_kind, $payload['first_name'] ?? null, $payload['last_name'] ?? null,
            $payload['middle_name'] ?? null, $payload['suffix'] ?? null,
        ))->with('participant')->orderBy('id')->get();
        foreach ($rows as $row) {
            if ($row->id === $excludeId) {
                continue;
            }
            $identity = $this->identity($row);
            foreach (['email', 'contact_number'] as $key) {
                $contact = $this->contactKey($key, $payload[$key] ?? null);
                if ($contact !== null && $contact === $this->contactKey($key, $identity[$key])) {
                    return $row;
                }
            }
        }

        return null;
    }

    /**
     * Find and rank every participant sharing a contact with this submission.
     *
     * @param  array<string, mixed>  $payload
     * @return list<array{participant: Participant, registration: Registration|null, reasons: list<string>, score: int, strong: bool}>
     */
    public function findCandidates(
        Event $event,
        array $payload,
        ?int $excludeRegistrationId = null,
        ?int $excludeParticipantId = null,
    ): array {
        Participant::assertPool($event->event_kind);
        $email = $this->contactKey('email', $payload['email'] ?? null);
        $phone = Participant::normalizeContactNumber($payload['contact_number'] ?? null);
        if ($email === null && $phone === null) {
            return [];
        }

        $participants = Participant::query()
            ->where('pool', $event->event_kind)
            ->where(function ($query) use ($email, $phone): void {
                if ($email !== null) {
                    $query->whereRaw('LOWER(email) = ?', [$email]);
                }
                if ($phone !== null) {
                    if ($email !== null) {
                        $query->orWhere('contact_number_normalized', $phone);
                    } else {
                        $query->where('contact_number_normalized', $phone);
                    }
                }
            })
            ->when($excludeParticipantId !== null, fn ($query) => $query->where('id', '!=', $excludeParticipantId))
            ->orderBy('id')
            ->get();

        $eventRegistrations = $this->registrations($event);
        $participantsById = $participants->keyBy('id');
        foreach ($eventRegistrations as $registration) {
            if ($registration->id === $excludeRegistrationId
                || $registration->participant_id === $excludeParticipantId) {
                continue;
            }
            $identity = $this->identity($registration);
            $sameEmail = $email !== null && $email === $this->contactKey('email', $identity['email']);
            $samePhone = $phone !== null && $phone === Participant::normalizeContactNumber($identity['contact_number']);
            if (($sameEmail || $samePhone) && $registration->participant !== null) {
                $participantsById->put($registration->participant_id, $registration->participant);
            }
        }

        $candidates = [];
        foreach ($participantsById as $participant) {
            $registration = $eventRegistrations->firstWhere('participant_id', $participant->id);
            if ($registration?->id === $excludeRegistrationId) {
                $registration = null;
            }
            $identity = $registration !== null
                ? $this->identity($registration)
                : [
                    'first_name' => $participant->first_name,
                    'middle_name' => $participant->middle_name,
                    'last_name' => $participant->last_name,
                    'suffix' => $participant->suffix,
                    'birthday' => $participant->birthday?->toDateString(),
                    'email' => $participant->email,
                    'contact_number' => $participant->contact_number,
                ];
            $similarName = $this->similar($this->name($payload), $this->name($identity));
            $sameEmail = $email !== null && $email === $this->contactKey('email', $identity['email']);
            $samePhone = $phone !== null && $phone === Participant::normalizeContactNumber($identity['contact_number']);
            $sameBirthday = $this->contactKey('birthday', $payload['birthday'] ?? null) !== null
                && $this->contactKey('birthday', $payload['birthday'] ?? null) === $this->contactKey('birthday', $identity['birthday']);

            if (! $sameEmail && ! $samePhone) {
                continue;
            }

            $reasons = [];
            $leftName = $this->name($payload);
            $rightName = $this->name($identity);
            if ($leftName !== '' && $leftName === $rightName) {
                $reasons[] = 'Exact name';
            } elseif ($similarName) {
                $reasons[] = 'Similar name';
            }
            if ($sameBirthday) {
                $reasons[] = 'Same birthdate';
            }
            if ($sameEmail) {
                $reasons[] = 'Same email';
            }
            if ($samePhone) {
                $reasons[] = 'Same phone';
            }

            $score = ($sameEmail ? 45 : 0)
                + ($samePhone ? 45 : 0)
                + ($sameBirthday ? 25 : 0)
                + ($leftName !== '' && $leftName === $rightName ? 35 : ($similarName ? 25 : 0));
            $candidates[] = [
                'participant' => $participant,
                'registration' => $registration,
                'reasons' => $reasons,
                'score' => $score,
                'strong' => $score >= 70,
            ];
        }

        usort($candidates, fn (array $left, array $right): int => $right['score'] <=> $left['score']
            ?: $left['participant']->id <=> $right['participant']->id);

        return $candidates;
    }

    /**
     * Strong identity matches or multiple contact-sharing candidates are held
     * for organizer review; one weak contact-only match remains advisory.
     *
     * @param  list<array{participant: Participant, registration: Registration|null, reasons: list<string>, score: int, strong: bool}>  $candidates
     */
    public function shouldHoldCandidates(array $candidates): bool
    {
        return count($candidates) > 1
            || collect($candidates)->contains(fn (array $candidate): bool => $candidate['strong']);
    }

    public function hasSimilar(Event $event, array $payload): bool
    {
        return $this->registrations($event)->contains(function ($registration) use ($payload): bool {
            $identity = $this->identity($registration);

            return $this->similar($this->name($payload), $this->name($identity))
                && $this->reasons($payload, $identity) !== [];
        });
    }

    public function audit(Event $event): array
    {
        $buckets = [];
        $pairs = [];
        $records = [];
        foreach ($this->registrations($event) as $r) {
            $identity = $this->identity($r);
            $candidates = [];
            foreach (['birthday', 'email', 'contact_number'] as $key) {
                $value = $this->contactKey($key, $identity[$key] ?? null);
                if ($value === null) {
                    continue;
                }
                $bucket = $key.':'.$value;
                foreach ($buckets[$bucket] ?? [] as $id) {
                    $candidates[$id] = true;
                }
                $buckets[$bucket][] = $r->id;
            }
            foreach (array_keys($candidates) as $id) {
                [$prior, $priorIdentity] = $records[$id];
                $reasons = $this->reasons($identity, $priorIdentity);
                if ($reasons) {
                    $pairs[] = ['left' => $this->staffRow($prior), 'right' => $this->staffRow($r), 'reasons' => $reasons];
                }
            }
            $records[$r->id] = [$r, $identity];
        }

        return $pairs;
    }

    public function staffRow(Registration $r): array
    {
        $identity = $this->identity($r);

        return [
            'id' => $r->id,
            'name' => implode(' ', array_filter([$identity['first_name'], $identity['middle_name'], $identity['last_name'], $identity['suffix']])),
            'email' => $identity['email'], 'phone' => $identity['contact_number'],
            'birthday' => $identity['birthday'], 'provisional' => $r->participant->provisional_event_id !== null,
        ];
    }
}
