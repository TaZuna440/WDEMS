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
        abort_unless($event->isRunningEvent(), 404);

        return $event->registrations()->with('participant')->orderBy('id')->get();
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
        return implode(' ', array_filter(array_map(fn ($key) => Participant::normalizeName($data[$key] ?? null), ['first_name', 'middle_name', 'last_name', 'suffix'])));
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

        return end($previous) <= max(1, (int) floor(max(count($a), count($b)) * 0.15));
    }

    public function reasons(array $left, array $right): array
    {
        if (! $this->similar($this->name($left), $this->name($right))) {
            return [];
        }
        $reasons = [];
        foreach (['birthday' => 'Same birthdate', 'email' => 'Same email', 'contact_number' => 'Same phone'] as $key => $label) {
            $a = $this->contactKey($key, $left[$key] ?? null);
            $b = $this->contactKey($key, $right[$key] ?? null);
            if ($a !== null && $a === $b) {
                $reasons[] = $label;
            }
        }

        return $reasons ? ['Similar name', ...$reasons] : [];
    }

    private function contactKey(string $key, ?string $value): ?string
    {
        if ($key === 'contact_number') {
            return Participant::normalizeContactNumber($value);
        }
        $value = mb_strtolower(trim($value ?? ''));

        return $value !== '' ? $value : null;
    }

    public function hasSimilar(Event $event, array $payload): bool
    {
        return $this->registrations($event)->contains(fn ($r) => $this->reasons($payload, $this->identity($r)) !== []);
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
