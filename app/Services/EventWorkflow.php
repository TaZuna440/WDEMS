<?php

namespace App\Services;

use App\Enums\EventStatus;
use App\Models\Event;
use InvalidArgumentException;

class EventWorkflow
{
    /**
     * Transition an event to a new status.
     *
     * Throws if the transition is not allowed by the workflow.
     */
    public function transition(Event $event, EventStatus $target): Event
    {
        if (! $event->isRunningEvent()) {
            throw new InvalidArgumentException('The running-event workflow cannot transition this event kind.');
        }

        $current = $event->status;

        if (! $current instanceof EventStatus) {
            $current = EventStatus::from($current);
        }

        if (! $current->canTransitionTo($target)) {
            throw new InvalidArgumentException(sprintf(
                'Cannot transition event #%d from "%s" to "%s".',
                $event->id,
                $current->value,
                $target->value,
            ));
        }

        if ($target === EventStatus::Ongoing && $current === EventStatus::RegistrationClosed && $event->registration_end === null) {
            $event->registration_end = now();
        }
        $event->status = $target;
        $event->save();

        return $event;
    }

    /**
     * Convenience helpers — each checks the transition rule internally.
     */
    public function openRegistration(Event $event): Event
    {
        return $this->transition($event, EventStatus::RegistrationOpen);
    }

    public function closeRegistration(Event $event): Event
    {
        return $this->transition($event, EventStatus::RegistrationClosed);
    }

    public function start(Event $event): Event
    {
        return $this->transition($event, EventStatus::Ongoing);
    }

    public function complete(Event $event): Event
    {
        return $this->transition($event, EventStatus::Completed);
    }

    public function cancel(Event $event): Event
    {
        return $this->transition($event, EventStatus::Cancelled);
    }

    /**
     * Check (without saving) whether a transition is allowed.
     */
    public function canTransition(Event $event, EventStatus $target): bool
    {
        if (! $event->isRunningEvent()) {
            return false;
        }

        $current = $event->status;

        if (! $current instanceof EventStatus) {
            $current = EventStatus::from($current);
        }

        return $current->canTransitionTo($target);
    }
}
