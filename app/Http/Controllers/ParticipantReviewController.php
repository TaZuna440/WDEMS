<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Participant;
use App\Models\Registration;
use App\Services\Participants\DuplicateReview;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ParticipantReviewController extends Controller
{
    public function search(Request $request, Event $event, DuplicateReview $review): JsonResponse
    {
        Participant::assertPool($event->event_kind);
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
        Participant::assertPool($event->event_kind);

        return Inertia::render('events/duplicates', [
            'event' => ['id' => $event->id, 'event_name' => $event->event_name, 'event_kind' => $event->event_kind],
            'decisions' => DB::table('registration_identity_decisions')->where('event_id', $event->id)
                ->orderByDesc('id')->paginate(50)->through(fn ($row) => [
                    'id' => $row->id, 'registration_id' => $row->registration_id,
                    'action' => $row->action, 'reason' => $row->reason,
                    'rule_version' => $row->rule_version, 'created_at' => $row->created_at,
                ]),
        ]);
    }

    public function resolve(Request $request, Event $event, Registration $registration): RedirectResponse
    {
        abort_unless($registration->event_id === $event->id, 404);
        abort(410, 'Identity checks are automatic. Manual duplicate decisions are no longer supported.');
    }
}
