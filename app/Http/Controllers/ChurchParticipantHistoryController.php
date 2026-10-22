<?php

namespace App\Http\Controllers;

use App\Models\Participant;
use App\Services\Church\ParticipantHistoryService;
use Inertia\Inertia;
use Inertia\Response;

class ChurchParticipantHistoryController extends Controller
{
    public function show(Participant $participant, ParticipantHistoryService $history): Response
    {
        abort_unless($participant->pool === 'church' && $participant->provisional_event_id === null, 404);

        return Inertia::render('events/church/participants/history', $history->build($participant));
    }
}
