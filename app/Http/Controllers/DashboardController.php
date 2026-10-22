<?php

namespace App\Http\Controllers;

use App\Enums\ChurchCategory;
use App\Enums\ChurchEventStatus;
use App\Enums\EventStatus;
use App\Models\Attendance;
use App\Models\Event;
use App\Models\Participant;
use App\Models\Registration;
use App\Services\Church\ParticipantHistoryService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $requestedSide = $request->query('side');
        $activeSide = in_array($requestedSide, ['running', 'church'], true)
            ? $requestedSide
            : ($request->user()->dashboard_side ?? 'running');
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
        $churchParticipantHistory = $isRunning
            ? []
            : app(ParticipantHistoryService::class)->topAttendees(5);
        $eventStatistics = $isRunning ? $this->getRunningEventStatistics() : null;

        return Inertia::render('dashboard', [
            'activeSide' => $activeSide,
            'stats' => $stats,
            'actionItems' => $actionItems,
            'upcoming' => $upcoming,
            'recentActivity' => $recentActivity,
            'churchCategories' => $churchCategories,
            'churchParticipantHistory' => $churchParticipantHistory,
            'eventStatistics' => $eventStatistics,
            'calendarEvents' => $events->filter(fn (Event $event) => $event->event_date !== null)
                ->map(fn (Event $event) => [
                    'id' => $event->id,
                    'event_name' => $event->event_name,
                    'event_date' => $event->event_date->toDateString(),
                    'church_category' => $event->church_category,
                ])->values()->all(),
        ]);
    }

    /**
     * @param  Collection<int, Event>  $events
     * @return array<int, array{label: string, value: int|string, subtext: string, href: string}>
     */
    private function getRunningStats(Collection $events): array
    {
        $openCount = $events->filter(fn ($e) => $this->lifecycle($e) === EventStatus::RegistrationOpen)->count();
        $totalRegistrations = Registration::active()->whereHas('event', fn ($q) => $q->where('event_kind', Event::KIND_RUNNING))->count();
        $todayRegistrations = Registration::active()->whereHas('event', fn ($q) => $q->where('event_kind', Event::KIND_RUNNING))->whereDate('created_at', today())->count();
        $totalAttendance = Attendance::where('attendance_status', 'present')->whereIn('registration_id', Registration::active()->whereHas('event', fn ($event) => $event->where('event_kind', Event::KIND_RUNNING))->select('id'))->count();

        $showRate = $totalRegistrations > 0 ? round(($totalAttendance / $totalRegistrations) * 100, 1) : 0;

        $windowEvents = $events->filter(fn ($e) => $e->canRecordAttendanceNow());
        $inWindow = $windowEvents->count();
        $nextWindowEvent = $windowEvents->sortBy('start_time')->first();
        $windowSubtext = $nextWindowEvent
            ? $nextWindowEvent->event_name.' · opens now'
            : 'Events active now';

        return [
            ['label' => 'Running Events', 'value' => $events->count(), 'subtext' => "{$openCount} open for registration", 'href' => '/events?kind=running'],
            ['label' => 'Registrations', 'value' => $totalRegistrations, 'subtext' => "+{$todayRegistrations} today", 'href' => '/registrations/monitor'],
            ['label' => 'Total Attendance', 'value' => $totalAttendance, 'subtext' => "{$showRate}% show rate", 'href' => '/attendance'],
            ['label' => 'In Check-in Window', 'value' => $inWindow, 'subtext' => $windowSubtext, 'href' => '/attendance'],
        ];
    }

    /** @return array{recent_runs:array<int, array{id:int, event_name:string, event_date:?string, registrations:int, show_rate:float}>, average_show_rate:float, paper_encoded_walk_ins:int, first_time_runners_this_month:int} */
    private function getRunningEventStatistics(): array
    {
        $recentRuns = Event::query()
            ->where('event_kind', Event::KIND_RUNNING)
            ->where('event_type', 'community_run')
            ->where('status', EventStatus::Completed)
            ->whereDate('event_date', '<=', today())
            ->orderByDesc('event_date')
            ->orderByDesc('id')
            ->take(4)
            ->get()
            ->map(function (Event $event): array {
                $registrations = Registration::active()->where('event_id', $event->id)->count();
                $present = Attendance::query()
                    ->where('attendance_status', 'present')
                    ->whereIn('registration_id', Registration::active()->where('event_id', $event->id)->select('id'))
                    ->count();

                return [
                    'id' => $event->id,
                    'event_name' => $event->event_name,
                    'event_date' => $event->event_date?->toDateString(),
                    'registrations' => $registrations,
                    'show_rate' => $registrations > 0 ? round(($present / $registrations) * 100, 1) : 0.0,
                ];
            })
            ->values();

        $averageShowRate = $recentRuns->isNotEmpty()
            ? round((float) $recentRuns->avg('show_rate'), 1)
            : 0.0;
        $paperEncodedWalkIns = Registration::active()
            ->where('source', 'paper')
            ->whereHas('event', fn ($query) => $query->where('event_kind', Event::KIND_RUNNING)->where('event_type', 'community_run'))
            ->count();
        $firstTimeRunners = Registration::active()
            ->where('participant_type', 'First-Timer')
            ->whereDate('created_at', '>=', now()->startOfMonth()->toDateString())
            ->whereHas('participant', fn ($query) => $query->where('pool', 'running')->whereNull('provisional_event_id'))
            ->whereHas('event', fn ($query) => $query->where('event_kind', Event::KIND_RUNNING)->where('event_type', 'community_run'))
            ->distinct('participant_id')
            ->count('participant_id');

        return [
            'recent_runs' => $recentRuns->all(),
            'average_show_rate' => $averageShowRate,
            'paper_encoded_walk_ins' => $paperEncodedWalkIns,
            'first_time_runners_this_month' => $firstTimeRunners,
        ];
    }

    /**
     * @param  Collection<int, Event>  $events
     * @return array<int, array{label: string, value: int|string, subtext: string, href: string}>
     */
    private function getChurchStats(Collection $events): array
    {
        $thisMonthEvents = $events->filter(fn ($e) => $e->event_date && $e->event_date->isCurrentMonth());
        $thisMonth = $thisMonthEvents->count();
        $activeModules = $thisMonthEvents->pluck('church_category')->filter()->unique()->count();
        $totalParticipants = Participant::where('pool', 'church')->whereNull('provisional_event_id')->count();
        $todayEventIds = $events->filter(fn (Event $event) => $event->event_date?->isToday())->modelKeys();
        $todayRegistrations = Registration::active()->whereIn('event_id', $todayEventIds)
            ->whereHas('participant', fn ($query) => $query->where('pool', 'church')->whereNull('provisional_event_id'))
            ->with('attendance')->get();
        $todayAttendance = Attendance::query()->whereIn('registration_id', $todayRegistrations->pluck('id'))
            ->whereIn('attendance_status', ['present', 'absent'])->count();
        $encodedTodayEvents = Registration::active()->whereIn('event_id', $todayEventIds)
            ->whereHas('participant', fn ($query) => $query->where('pool', 'church')->whereNull('provisional_event_id'))
            ->whereHas('attendance', fn ($query) => $query->whereIn('attendance_status', ['present', 'absent']))
            ->distinct('event_id')->count('event_id');
        $unmarked = $todayRegistrations->filter(fn (Registration $registration) => $registration->attendance === null)->count();
        $largestUnmarkedEvent = $todayRegistrations->filter(fn (Registration $registration) => $registration->attendance === null)
            ->groupBy('event_id')->sortByDesc(fn ($rows) => $rows->count())->keys()->first();
        $largestUnmarkedName = $largestUnmarkedEvent === null ? null : $events->firstWhere('id', $largestUnmarkedEvent)?->event_name;

        $weeklyParticipants = Participant::where('pool', 'church')->whereNull('provisional_event_id')
            ->where('created_at', '>=', now()->startOfWeek())->count();

        $unmarkedHref = $largestUnmarkedEvent === null
            ? '/events?kind=church&date='.today()->toDateString()
            : route('church.attendance', ['event' => $largestUnmarkedEvent, 'filter' => 'unmarked']);

        return [
            ['label' => 'Events This Month', 'value' => $thisMonth, 'subtext' => "{$activeModules} modules active", 'href' => '/events?kind=church&month='.now()->format('Y-m')],
            ['label' => 'Church Participants', 'value' => $totalParticipants, 'subtext' => "+{$weeklyParticipants} this week", 'href' => '/events?kind=church'],
            ['label' => 'Attendance Recorded Today', 'value' => $todayAttendance, 'subtext' => $encodedTodayEvents.' church events encoded', 'href' => '/events?kind=church&date='.today()->toDateString()],
            ['label' => 'Unmarked Remaining', 'value' => $unmarked, 'subtext' => $largestUnmarkedName ?? 'Across today’s events', 'href' => $unmarkedHref],
        ];
    }

    /**
     * @return array<int, array{id:int, type:string, description:string, time:string}>
     */
    private function buildRecentActivity(string $kind): array
    {
        $pool = $kind === Event::KIND_CHURCH ? 'church' : 'running';
        $registrations = Registration::query()->with(['participant', 'event'])
            ->whereIn('registration_status', ['confirmed', 'held'])
            ->whereHas('participant', fn ($query) => $query->where('pool', $pool)->whereNull('provisional_event_id'))
            ->whereHas('event', fn ($q) => $q->where('event_kind', $kind))
            ->latest()
            ->orderByDesc('id')
            ->take(10)
            ->get();

        $registrationActivity = $registrations->map(function (Registration $reg) {
            /** @var Participant $participant */
            $participant = $reg->participant;
            /** @var Event $event */
            $event = $reg->event;

            return [
                'id' => 'registration-'.$reg->id,
                'type' => $reg->isHeld() ? 'review' : 'registration',
                'description' => $reg->isHeld()
                    ? 'A registration was held for organizer review at '.$event->event_name
                    : $participant->fullName().' registered for '.$event->event_name,
                'time' => $reg->created_at->diffForHumans(),
                'timestamp' => $reg->created_at,
            ];
        });
        $attendanceActivity = Attendance::query()->with(['registration.participant', 'registration.event'])
            ->whereHas('registration', fn ($query) => $query->active()
                ->whereHas('participant', fn ($participant) => $participant->where('pool', $pool))
                ->whereHas('event', fn ($event) => $event->where('event_kind', $kind)))
            ->latest('updated_at')->take(10)->get()
            ->map(function (Attendance $attendance) {
                $registration = $attendance->registration;
                $participant = $registration->participant;
                $event = $registration->event;

                return [
                    'id' => 'attendance-'.$attendance->id,
                    'type' => 'attendance',
                    'description' => 'Attendance recorded for '.$participant->fullName().' at '.$event->event_name,
                    'time' => $attendance->updated_at->diffForHumans(),
                    'timestamp' => $attendance->updated_at,
                ];
            });

        $eventActivity = Event::query()->where('event_kind', $kind)->latest()->take(10)->get()
            ->map(fn (Event $event) => [
                'id' => 'event-'.$event->id,
                'type' => 'event',
                'description' => ($kind === Event::KIND_CHURCH ? 'Church event created: ' : 'Event created: ').$event->event_name,
                'time' => $event->created_at->diffForHumans(),
                'timestamp' => $event->created_at,
            ]);

        $reviewActivity = DB::table('registration_review_resolutions as resolutions')
            ->join('registrations', 'registrations.id', '=', 'resolutions.registration_id')
            ->join('events', 'events.id', '=', 'resolutions.event_id')
            ->join('participants', 'participants.id', '=', 'registrations.participant_id')
            ->where('events.event_kind', $kind)
            ->where('participants.pool', $pool)
            ->latest('resolutions.created_at')
            ->take(10)
            ->get(['resolutions.id', 'resolutions.action', 'resolutions.created_at', 'events.event_name', 'participants.first_name', 'participants.last_name'])
            ->map(fn (object $row) => [
                'id' => 'review-resolution-'.$row->id,
                'type' => 'review',
                'description' => 'Organizer '.str_replace('_', ' ', $row->action).' for '.$row->first_name.' '.$row->last_name.' at '.$row->event_name,
                'time' => Carbon::parse($row->created_at)->diffForHumans(),
                'timestamp' => $row->created_at,
            ]);

        return $reviewActivity->concat($registrationActivity)->concat($attendanceActivity)->concat($eventActivity)
            ->sortByDesc('timestamp')->take(5)
            ->map(fn (array $activity) => collect($activity)->except('timestamp')->all())
            ->values()->all();
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
            $categories[] = [
                'label' => $cat->label(),
                'count' => $count,
                'href' => '/events?kind=church&church_category='.urlencode($cat->value),
            ];
        }

        return $categories;
    }

    /**
     * @param  Collection<int, Event>  $events
     * @return array<int, array{id:int|string, event_name:string, event_date:?string, days_until:?int, status:string, status_label:string, urgency:string, reason:string, action:array{label:string, href:string}}>
     */
    private function buildActionItems(Collection $events): array
    {
        $items = [];

        foreach ($events as $event) {
            $status = $this->lifecycle($event);
            $pool = $event->isChurchEvent() ? 'church' : 'running';
            $heldCount = Registration::held()->where('event_id', $event->id)
                ->whereHas('participant', fn ($query) => $query->where('pool', $pool)->whereNull('provisional_event_id'))
                ->count();
            if ($heldCount > 0) {
                $items[] = [
                    'id' => 'review-'.$event->id,
                    'event_name' => $event->event_name,
                    'event_date' => $event->event_date?->format('M j, Y'),
                    'days_until' => $event->event_date ? (int) now()->startOfDay()->diffInDays($event->event_date->startOfDay(), false) : null,
                    'status' => $status->value,
                    'status_label' => $status->label(),
                    'urgency' => 'needs_review',
                    'reason' => $heldCount.' held '.($heldCount === 1 ? 'registration' : 'registrations').' awaiting review',
                    'action' => ['label' => 'Review queue', 'href' => route('events.possible-duplicates', $event)],
                ];
            }

            if (in_array($status, [EventStatus::Completed, EventStatus::Cancelled, ChurchEventStatus::Completed, ChurchEventStatus::Cancelled], true)) {
                continue;
            }

            $daysUntil = $event->event_date
                ? (int) now()->startOfDay()->diffInDays($event->event_date->startOfDay(), false)
                : null;

            $status = $this->lifecycle($event);
            $regCount = Registration::active()->where('event_id', $event->id)->count();

            $hasChurchUnmarked = false;
            if ($event->isChurchEvent() && $event->event_date?->isToday()) {
                $unmarkedCount = Registration::active()->where('event_id', $event->id)
                    ->whereHas('participant', fn ($query) => $query->where('pool', 'church')->whereNull('provisional_event_id'))
                    ->whereDoesntHave('attendance')->count();
                if ($unmarkedCount > 0) {
                    $hasChurchUnmarked = true;
                    $items[] = [
                        'id' => 'unmarked-'.$event->id,
                        'event_name' => $event->event_name,
                        'event_date' => $event->event_date->format('M j, Y'),
                        'days_until' => 0,
                        'status' => $status->value,
                        'status_label' => $status->label(),
                        'urgency' => 'urgent',
                        'reason' => "Today’s event — {$unmarkedCount} ".($unmarkedCount === 1 ? 'participant' : 'participants').' still unmarked',
                        'action' => ['label' => 'Record attendance', 'href' => route('church.attendance', ['event' => $event->id, 'filter' => 'unmarked'])],
                    ];
                }
            }

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
                $hasChurchUnmarked => [null, null, null],
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

        $order = ['needs_review' => 0, 'overdue' => 1, 'urgent' => 2, 'action' => 3, 'waiting' => 4];
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
                'church_category' => $e->church_category,
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
