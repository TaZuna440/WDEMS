<?php

namespace App\Http\Controllers;

use App\Enums\ChurchCategory;
use App\Enums\ChurchEventStatus;
use App\Enums\EventStatus;
use App\Models\Attendance;
use App\Models\Event;
use App\Models\Participant;
use App\Models\Registration;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $activeSide = $request->input('side') === 'church' ? 'church' : 'running';
        $isRunning = $activeSide === 'running';
        $kind = $isRunning ? Event::KIND_RUNNING : Event::KIND_CHURCH;

        $events = Event::with('creator:id,name')
            ->where('event_kind', $kind)
            ->orderBy('event_date')
            ->orderBy('id')
            ->get();

        $actionItems = $this->buildActionItems($events);
        $upcoming = $this->buildUpcoming($events);

        $stats = $isRunning
            ? $this->getRunningStats($events)
            : $this->getChurchStats($events);

        $recentActivity = $this->buildRecentActivity($kind);

        $churchCategories = $isRunning ? [] : $this->buildChurchCategories($events);

        return Inertia::render('dashboard', [
            'activeSide' => $activeSide,
            'stats' => $stats,
            'actionItems' => $actionItems,
            'upcoming' => $upcoming,
            'recentActivity' => $recentActivity,
            'churchCategories' => $churchCategories,
        ]);
    }

    /**
     * @param  Collection<int, Event>  $events
     * @return array<int, array{label: string, value: int|string, subtext: string}>
     */
    private function getRunningStats(Collection $events): array
    {
        $openCount = $events->filter(fn ($e) => $this->lifecycle($e) === EventStatus::RegistrationOpen)->count();
        $totalRegistrations = Registration::active()->whereHas('event', fn ($q) => $q->where('event_kind', Event::KIND_RUNNING))->count();
        $todayRegistrations = Registration::active()->whereHas('event', fn ($q) => $q->where('event_kind', Event::KIND_RUNNING))->whereDate('created_at', today())->count();
        $totalAttendance = Attendance::where('attendance_status', 'present')->whereIn('registration_id', Registration::active()->whereHas('event', fn ($event) => $event->where('event_kind', Event::KIND_RUNNING))->select('id'))->count();

        $showRate = $totalRegistrations > 0 ? round(($totalAttendance / $totalRegistrations) * 100, 1) : 0;

        $inWindow = $events->filter(fn ($e) => $e->canRecordAttendanceNow())->count();

        return [
            ['label' => 'Running Events', 'value' => $events->count(), 'subtext' => "{$openCount} open for registration"],
            ['label' => 'Registrations', 'value' => $totalRegistrations, 'subtext' => "+{$todayRegistrations} today"],
            ['label' => 'Total Attendance', 'value' => $totalAttendance, 'subtext' => "{$showRate}% show rate"],
            ['label' => 'In Check-in Window', 'value' => $inWindow, 'subtext' => 'Events active now'],
        ];
    }

    /**
     * @param  Collection<int, Event>  $events
     * @return array<int, array{label: string, value: int|string, subtext: string}>
     */
    private function getChurchStats(Collection $events): array
    {
        $thisMonth = $events->filter(fn ($e) => $e->event_date && $e->event_date->isCurrentMonth())->count();
        $totalParticipants = Participant::where('pool', 'church')->count();

        return [
            ['label' => 'Events This Month', 'value' => $thisMonth, 'subtext' => '9 modules active'],
            ['label' => 'Church Participants', 'value' => $totalParticipants, 'subtext' => 'In church pool'],
            ['label' => 'Attendance Today', 'value' => '—', 'subtext' => 'Awaiting unified church attendance'],
            ['label' => 'Unmarked Remaining', 'value' => '—', 'subtext' => 'Awaiting unified church attendance'],
        ];
    }

    /**
     * @return array<int, array{id:int, type:string, description:string, time:string}>
     */
    private function buildRecentActivity(string $kind): array
    {
        $registrations = Registration::active()->with(['participant', 'event'])
            ->whereHas('event', fn ($q) => $q->where('event_kind', $kind))
            ->latest()
            ->take(5)
            ->get();

        return $registrations->map(function (Registration $reg) {
            /** @var Participant $participant */
            $participant = $reg->participant;
            /** @var Event $event */
            $event = $reg->event;

            return [
                'id' => $reg->id,
                'type' => 'registration',
                'description' => $participant->first_name.' '.$participant->last_name.' registered for '.$event->event_name,
                'time' => $reg->created_at->diffForHumans(),
            ];
        })->all();
    }

    /**
     * @param  Collection<int, Event>  $events
     * @return array<int, array{label: string, count: int}>
     */
    private function buildChurchCategories(Collection $events): array
    {
        $categories = [];
        foreach (ChurchCategory::cases() as $cat) {
            $count = $events->where('church_category', $cat->value)->count();
            $categories[] = ['label' => $cat->label(), 'count' => $count];
        }

        return $categories;
    }

    /**
     * @param  Collection<int, Event>  $events
     * @return array<int, array{id:int, event_name:string, event_date:?string, days_until:?int, status:string, status_label:string, urgency:string, reason:string, action:array{label:string, href:string}}>
     */
    private function buildActionItems(Collection $events): array
    {
        $items = [];

        foreach ($events as $event) {
            $status = $this->lifecycle($event);
            if (in_array($status, [EventStatus::Completed, EventStatus::Cancelled, ChurchEventStatus::Completed, ChurchEventStatus::Cancelled], true)) {
                continue;
            }

            $daysUntil = $event->event_date
                ? (int) now()->startOfDay()->diffInDays($event->event_date->startOfDay(), false)
                : null;

            $status = $this->lifecycle($event);
            $regCount = Registration::active()->where('event_id', $event->id)->count();

            [$urgency, $reason, $action] = match (true) {
                $daysUntil !== null && $daysUntil < 0 => [
                    'overdue', 'Event was '.abs($daysUntil).' days ago, still '.$status->label(), ['label' => 'Mark as completed', 'href' => route('events.show', $event)],
                ],
                $daysUntil !== null && $daysUntil <= 7 && $status === EventStatus::Draft => [
                    'urgent', "Event in {$daysUntil} days — still Draft", $this->nextActionFor($event),
                ],
                $status === EventStatus::Draft => [
                    'action', 'Draft — no registration form yet', ['label' => 'Configure event', 'href' => route('events.show', $event)],
                ],
                $status === EventStatus::RegistrationOpen => [
                    'waiting', "Registration open — {$regCount} registered", ['label' => 'View event', 'href' => route('events.show', $event)],
                ],
                $status === EventStatus::RegistrationClosed => [
                    'waiting', "Registration closed — {$regCount} registered", ['label' => 'View event', 'href' => route('events.show', $event)],
                ],
                $status === ChurchEventStatus::Upcoming => [
                    'waiting', 'Upcoming church event — review registrations', ['label' => 'View registrations', 'href' => route('church.registrations.index', $event)],
                ],
                in_array($status, [EventStatus::Ongoing, ChurchEventStatus::Ongoing], true) => [
                    'waiting', 'Event ongoing — mark complete when done', ['label' => 'Mark as completed', 'href' => route('events.show', $event)],
                ],
                default => [null, null, null],
            };

            if ($urgency === null) {
                continue;
            }

            $items[] = [
                'id' => $event->id,
                'event_name' => $event->event_name,
                'event_date' => $event->event_date?->format('M j, Y'),
                'days_until' => $daysUntil,
                'status' => $status->value,
                'status_label' => $status->label(),
                'urgency' => $urgency,
                'reason' => $reason,
                'action' => $action,
            ];
        }

        $order = ['overdue' => 0, 'urgent' => 1, 'action' => 2, 'waiting' => 3];
        usort($items, fn ($a, $b) => $order[$a['urgency']] <=> $order[$b['urgency']]);

        return $items;
    }

    /**
     * @param  Collection<int, Event>  $events
     * @return array<int, array{id:int, event_name:string, event_date:string, days_until:int, status:string, status_label:string}>
     */
    private function buildUpcoming(Collection $events): array
    {
        return $events
            ->filter(fn (Event $e) => $e->event_date && $e->event_date->isFuture())
            ->filter(fn (Event $e) => ! in_array($this->lifecycle($e), [EventStatus::Completed, EventStatus::Cancelled, ChurchEventStatus::Completed, ChurchEventStatus::Cancelled], true))
            ->sortBy('event_date')
            ->take(3)
            ->map(fn (Event $e) => [
                'id' => $e->id,
                'event_name' => $e->event_name,
                'event_date' => $e->event_date->format('M j, Y'),
                'days_until' => (int) now()->startOfDay()->diffInDays($e->event_date->startOfDay(), false),
                'status' => $this->lifecycle($e)->value,
                'status_label' => $e->statusLabel(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{label:string, href:string}
     */
    private function nextActionFor(Event $event): array
    {
        if ($this->lifecycle($event) === EventStatus::Draft) {
            return ['label' => 'Configure event', 'href' => route('events.show', $event)];
        }

        return ['label' => 'Open registration', 'href' => route('events.show', $event)];
    }

    private function lifecycle(Event $event): EventStatus|ChurchEventStatus
    {
        return $event->isRunningEvent()
            ? EventStatus::from($event->getRawOriginal('status'))
            : ChurchEventStatus::from($event->getRawOriginal('church_status') ?? 'upcoming');
    }
}
