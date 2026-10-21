<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Participant;
use App\Models\Registration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ChurchRegistrationController extends Controller
{
    public function index(Event $event): Response
    {
        abort_if($event->event_kind !== Event::KIND_CHURCH, 404);

        $registrations = Registration::with('participant')
            ->where('event_id', $event->id)
            ->latest()
            ->get()
            ->map(fn ($reg) => [
                'id' => $reg->id,
                'participant_id' => $reg->participant_id,
                'name' => $reg->participant->first_name.' '.$reg->participant->last_name,
                'email' => $reg->participant->email,
                'contact' => $reg->participant->contact_number,
                'participant_type' => $reg->participant_type ?? 'Member',
                'guardian_name' => $reg->guardian_name,
                'guardian_contact' => $reg->guardian_contact,
                'registration_status' => $reg->registration_status,
                'attendance_code' => $reg->attendance_code,
            ]);

        return Inertia::render('events/church/registrations/index', [
            'event' => $event->only('id', 'event_name', 'church_category', 'church_status'),
            'registrations' => $registrations,
        ]);
    }

    public function store(Request $request, Event $event)
    {
        abort_if($event->event_kind !== Event::KIND_CHURCH, 404);

        $validated = $request->validate([
            'first_name' => 'required|string|max:50',
            'last_name' => 'required|string|max:50',
            'middle_name' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'contact_number' => 'nullable|string|max:20',
            'participant_type' => 'required|string|in:Member,First-Timer,Guest',
            'guardian_name' => 'nullable|string|max:100',
            'guardian_contact' => 'nullable|string|max:20',
        ]);

        DB::transaction(function () use ($validated, $event) {
            // B-5: Resolve or create the church participant
            $participant = Participant::resolveFrom(
                'church',
                $validated['email'] ?? null,
                $validated['contact_number'] ?? null,
                $validated
            );

            // Create the registration
            $event->registrations()->create([
                'participant_id' => $participant->id,
                'registration_status' => 'confirmed', // Staff added = confirmed
                'source' => 'manual',
                'participant_type' => $validated['participant_type'],
                'guardian_name' => $validated['guardian_name'],
                'guardian_contact' => $validated['guardian_contact'],
                'attendance_code' => strtoupper(bin2hex(random_bytes(3))), // e.g. A1B2C3
            ]);
        });

        return back()->with('success', 'Participant registered successfully.');
    }

    public function update(Request $request, Registration $registration)
    {
        abort_if($registration->event->event_kind !== Event::KIND_CHURCH, 404);

        $validated = $request->validate([
            'participant_type' => 'required|string|in:Member,First-Timer,Guest',
            'guardian_name' => 'nullable|string|max:100',
            'guardian_contact' => 'nullable|string|max:20',
        ]);

        $registration->update($validated);

        return back()->with('success', 'Registration updated successfully.');
    }

    public function destroy(Registration $registration)
    {
        abort_if($registration->event->event_kind !== Event::KIND_CHURCH, 404);

        // B-5: Delete removes the registration, never the person
        $registration->delete();

        return back()->with('success', 'Registration removed.');
    }
}
