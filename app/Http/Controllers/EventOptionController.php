<?php

namespace App\Http\Controllers;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\EventOption;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EventOptionController extends Controller
{
    public function index(Event $event): Response
    {
        $event->load('eventOptions');

        return Inertia::render('events/options', [
            'event' => [
                'id' => $event->id,
                'event_name' => $event->event_name,
                'status' => $event->status->value,
                'status_label' => $event->status->label(),
                'can_configure' => $event->canConfigure(),
                'can_mark_configured' => $event->status === EventStatus::Draft,
            ],
            'options' => $event->eventOptions
                ->sortBy(['option_type', 'option_name'])
                ->values()
                ->map(fn (EventOption $option) => [
                    'id' => $option->id,
                    'option_type' => $option->option_type,
                    'option_name' => $option->option_name,
                    'option_value' => $option->option_value,
                    'is_required' => $option->is_required,
                    'is_available' => $option->is_available,
                ]),
        ]);
    }

    public function store(Request $request, Event $event): RedirectResponse
    {
        if (! $event->canConfigure()) {
            abort(403, 'This event can no longer be configured.');
        }

        $validated = $request->validate([
            'option_type' => ['required', 'string', 'max:50'],
            'option_name' => ['required', 'string', 'max:255'],
            'option_value' => ['nullable', 'string', 'max:255'],
            'is_required' => ['boolean'],
            'is_available' => ['boolean'],
        ]);

        $event->eventOptions()->create([
            'option_type' => $validated['option_type'],
            'option_name' => $validated['option_name'],
            'option_value' => $validated['option_value'] ?? null,
            'is_required' => $validated['is_required'] ?? false,
            'is_available' => $validated['is_available'] ?? true,
        ]);

        return redirect()->route('events.options.index', $event);
    }

    public function update(Request $request, Event $event, EventOption $option): RedirectResponse
    {
        if ($option->event_id !== $event->id) {
            abort(404);
        }

        if (! $event->canConfigure()) {
            abort(403, 'This event can no longer be configured.');
        }

        $validated = $request->validate([
            'option_type' => ['required', 'string', 'max:50'],
            'option_name' => ['required', 'string', 'max:255'],
            'option_value' => ['nullable', 'string', 'max:255'],
            'is_required' => ['boolean'],
            'is_available' => ['boolean'],
        ]);

        $option->update([
            'option_type' => $validated['option_type'],
            'option_name' => $validated['option_name'],
            'option_value' => $validated['option_value'] ?? null,
            'is_required' => $validated['is_required'] ?? false,
            'is_available' => $validated['is_available'] ?? true,
        ]);

        return redirect()->route('events.options.index', $event);
    }

    public function destroy(Event $event, EventOption $option): RedirectResponse
    {
        if ($option->event_id !== $event->id) {
            abort(404);
        }

        if (! $event->canConfigure()) {
            abort(403, 'This event can no longer be configured.');
        }

        $option->delete();

        return redirect()->route('events.options.index', $event);
    }
}