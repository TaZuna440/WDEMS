<?php

namespace App\Http\Controllers;

use App\Enums\ServiceType;
use App\Http\Requests\Services\StoreRecurringServiceRequest;
use App\Http\Requests\Services\StoreServiceOccurrenceRequest;
use App\Models\ServiceOccurrence;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Services module -- occurrence creation (manual and recurring) and deletion.
 *
 * Attendance recording is in ServiceAttendanceController.
 *
 * Phase 3, per client mockup.
 */
class ServiceController extends Controller
{
    /**
     * Create one occurrence manually.
     *
     * The service_type comes from the route. The occurrence_date,
     * times, and metadata come from the request.
     */
    public function storeOccurrence(
        StoreServiceOccurrenceRequest $request,
        string $type,
    ): RedirectResponse {
        $serviceType = ServiceType::tryFrom($type);

        if ($serviceType === null) {
            abort(404);
        }

        $validated = $request->validated();

        ServiceOccurrence::create([
            'service_type' => $serviceType->value,
            'occurrence_date' => $validated['occurrence_date'],
            'start_time' => $validated['start_time'] ?? null,
            'end_time' => $validated['end_time'] ?? null,
            'service_leader' => $validated['service_leader'] ?? null,
            'topic' => $validated['topic'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'created_by' => $request->user()->id,
        ]);

        return redirect()->route('services.show', ['type' => $serviceType->value]);
    }

    /**
     * Create a batch of occurrences from a recurring schedule.
     *
     * Weekly: every 7 days from first_date, N times.
     * Fortnightly: every 14 days from first_date, N times.
     *
     * The first_date must fall on the selected day_of_week. The
     * controller validates this; if it does not match, it adjusts
     * forward to the next matching day. The organizer sees the
     * adjustment in the flash message.
     */
    public function storeRecurring(
        StoreRecurringServiceRequest $request,
        string $type,
    ): RedirectResponse {
        $serviceType = ServiceType::tryFrom($type);

        if ($serviceType === null) {
            abort(404);
        }

        $validated = $request->validated();
        $firstDate = Carbon::parse($validated['first_date'])->startOfDay();
        $targetDow = (int) $validated['day_of_week'];

        // Adjust first_date forward to the requested day of week.
        $daysAhead = ($targetDow - $firstDate->dayOfWeek + 7) % 7;
        if ($daysAhead > 0) {
            $firstDate = $firstDate->addDays($daysAhead);
        }

        $step = $validated['pattern'] === 'fortnightly' ? 14 : 7;
        $count = (int) $validated['weeks'];
        $lastDate = $firstDate->copy()->addDays($step * ($count - 1));
        if ($lastDate->gt(now()->addYears(2)->endOfDay())) {
            throw ValidationException::withMessages([
                'first_date' => 'The generated service dates cannot extend more than 2 years from today.',
            ]);
        }

        $userId = $request->user()->id;
        $startTime = $validated['start_time'] ?? null;
        $endTime = $validated['end_time'] ?? null;
        $leader = $validated['service_leader'] ?? null;

        $created = 0;

        for ($i = 0; $i < $count; $i++) {
            $date = $firstDate->copy()->addDays($step * $i);

            // Skip duplicates. If an occurrence already exists for
            // this type + date, do not create a second one.
            $exists = ServiceOccurrence::where('service_type', $serviceType->value)
                ->whereDate('occurrence_date', $date->toDateString())
                ->exists();

            if ($exists) {
                continue;
            }

            ServiceOccurrence::create([
                'service_type' => $serviceType->value,
                'occurrence_date' => $date->toDateString(),
                'start_time' => $startTime,
                'end_time' => $endTime,
                'service_leader' => $leader,
                'notes' => null,
                'created_by' => $userId,
            ]);

            $created++;
        }

        return redirect()
            ->route('services.show', ['type' => $serviceType->value])
            ->with('success', $created === 1
                ? 'Created 1 service occurrence.'
                : "Created {$created} service occurrences.");
    }

    /**
     * Delete one occurrence. Attendance rows cascade.
     */
    public function destroyOccurrence(ServiceOccurrence $occurrence): RedirectResponse
    {
        $type = $occurrence->service_type->value;
        DB::transaction(function () use ($occurrence): void {
            $row = ServiceOccurrence::query()->whereKey($occurrence->id)->lockForUpdate()->firstOrFail();
            abort_if($row->getAttribute('registration_event_id') !== null, 409, 'Delete the activated church event instead.');
            $row->delete();
        });

        return redirect()
            ->route('services.show', ['type' => $type])
            ->with('success', 'Service occurrence deleted.');
    }
}
