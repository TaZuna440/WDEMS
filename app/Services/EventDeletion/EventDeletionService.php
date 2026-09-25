<?php

namespace App\Services\EventDeletion;

use App\Models\Attendance;
use App\Models\Event;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EventDeletionService
{
    /**
     * Delete an event and all records that belong to it.
     *
     * Deletion is performed in an explicit order inside a transaction.
     * Children are deleted before parents so the intent is clear and
     * does not depend on cascade behavior. Once the event row is gone,
     * the CASCADE FK from registrations → events would remove
     * registrations automatically — but being explicit keeps the
     * sequence readable and immune to schema changes.
     *
     * Participants are never touched — they are shared across events.
     *
     * Note: event_options and registration_options were dropped in
     * Phase 2 of the registration plan. This service no longer
     * references them.
     */
    public function delete(Event $event, User $actor): void
    {
        $eventId = $event->id;
        $eventName = $event->event_name;
        $counts = $event->relatedRecordCounts();
        $registrationIds = $event->registrations()->pluck('id');

        DB::transaction(function () use ($event, $registrationIds) {
            // Children first, parents last — strict dependency order.
            Attendance::whereIn('registration_id', $registrationIds)->delete();
            Registration::whereIn('id', $registrationIds)->delete();

            $event->delete();
        });

        Log::info('event.deleted', [
            'event_id' => $eventId,
            'event_name' => $eventName,
            'actor_id' => $actor->id,
            'actor_role' => $actor->role,
            'related_counts' => $counts,
            'at' => now()->toIso8601String(),
        ]);
    }
}
