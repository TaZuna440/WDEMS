<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Services\Participants\DuplicateReview;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ParticipantReviewController extends Controller
{
    public function search(Request $request, Event $event, DuplicateReview $review): JsonResponse
    {
        abort_unless($event->isRunningEvent(), 404);
        $data = $request->validate(['q' => ['required', 'string', 'min:2', 'max:160']]);
        $query = mb_strtolower(trim($data['q']));
        $rows = $review->registrations($event)->filter(function ($r) use ($query, $review) {
            $name = $review->name($review->identity($r));

            return str_contains($name, $query) || $review->similar($name, $query);
        })->take(10)->map(fn ($r) => $review->staffRow($r))->values();

        return response()->json(['matches' => $rows]);
    }

    public function audit(Event $event, DuplicateReview $review): Response
    {
        abort_unless($event->isRunningEvent(), 404);

        return Inertia::render('events/duplicates', [
            'event' => ['id' => $event->id, 'event_name' => $event->event_name],
            'pairs' => $review->audit($event),
        ]);
    }
}
