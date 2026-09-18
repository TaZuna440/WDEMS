<?php

namespace App\Services\EventDeletion;

use App\Models\Attendance;
use App\Models\Event;
use App\Models\EventOption;
use App\Models\Registration;
use App\Models\RegistrationOption;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EventDeletionService
{
    /**
     * Delete an event and all records that belong to it.
     *
     * Deletion is performed in an explicit order inside a transaction
     * rather than relying on database cascade behavior. The FK
     * registration_options.event_option_id is RESTRICT, which gives
     * MySQL two concurrent cascade paths to event_options; explicit
     * ordering avoids a possible FK constraint violation.
     *
     * Participants are never touched — they are shared across events.
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
            RegistrationOption::whereIn('registration_id', $registrationIds)->delete();
            Registration::whereIn('id', $registrationIds)->delete();
            EventOption::where('event_id', $event->id)->delete();

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