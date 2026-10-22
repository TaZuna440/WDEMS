<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\FellowshipEvent;
use App\Models\ServiceOccurrence;
use App\Services\Church\RegistrationBridge;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ChurchRegistrationBridgeController extends Controller
{
    public function service(ServiceOccurrence $occurrence, RegistrationBridge $bridge): RedirectResponse
    {
        return redirect()->route('church.registrations.index', $bridge->activate($occurrence));
    }

    public function fellowship(FellowshipEvent $event, RegistrationBridge $bridge): RedirectResponse
    {
        return redirect()->route('church.registrations.index', $bridge->activate($event));
    }

    public function publish(Request $request, Event $event): RedirectResponse
    {
        abort_unless($event->isChurchEvent(), 404);
        $data = $request->validate(['open' => ['required', 'boolean']]);
        DB::transaction(function () use ($event, $data): void {
            $row = Event::whereKey($event->id)->lockForUpdate()->firstOrFail();
            $row->forceFill([
                'church_registration_open' => $data['open'],
                'registration_slug' => $row->registration_slug ?? Str::random(32),
                'registration_form_version' => 2,
                'registration_form_saved_at' => $row->registration_form_saved_at ?? now(),
                'registration_common_field_requirements' => ['email' => true, 'contact_number' => false, 'address' => true],
            ])->save();
        });

        return back()->with('success', $data['open'] ? 'Public registration link opened.' : 'New public submissions closed. Existing verified sessions may finish.');
    }
}
