<?php

namespace App\Http\Controllers;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Services\Reporting\ReportService;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Renders the end-of-event report.
 *
 * Feature C of docs/attendance-encoding-and-reporting.md.
 *
 * Thin controller by design — SPEC-GAP-2 of the same doc requires
 * that report aggregation live behind a service. ReportService does
 * the query work; this class guards, delegates, and returns the
 * Inertia response (or a CSV stream).
 */
class ReportController extends Controller
{
    public function __construct(
        private readonly ReportService $reports,
    ) {}

    /**
     * The report is reachable when the event has attendance data to
     * report on: status is Ongoing or Completed.
     *
     * Draft, RegistrationOpen, RegistrationClosed, and Cancelled are
     * 404'd. Their state does not have a meaningful "how did it go"
     * answer:
     *   - Draft / RegistrationOpen: nothing has happened yet
     *   - RegistrationClosed: the event has not started
     *   - Cancelled: it did not happen
     *
     * 404 rather than 403 — the URL is not meaningful in those
     * states, and telling a user "you cannot see this" when the
     * resource does not conceptually exist yet is confusing.
     */
    public function show(Event $event): Response
    {
        $this->assertReportable($event);

        return Inertia::render('events/report', [
            ...$this->reports->build($event),
            'urls' => [
                'event' => route('events.show', $event),
                'attendance' => route('events.attendance', $event),
                'export' => route('events.report.export', $event),
                'registrations' => $event->getRawOriginal('status') === EventStatus::Ongoing->value
                    ? route('registration-monitor.show', $event)
                    : null,
            ],
        ]);
    }

    /**
     * CSV export of the participants section.
     *
     * Same guard as show(). Streams the response so the browser
     * downloads the file directly rather than routing through an
     * Inertia visit.
     *
     * The filename is derived from the event name, sanitized to
     * filesystem-safe characters. Same convention as
     * RegistrationMonitorController::exportCsv().
     */
    public function exportCsv(Event $event): StreamedResponse
    {
        $this->assertReportable($event);

        $slug = preg_replace(
            '/[^A-Za-z0-9]+/',
            '-',
            $event->event_name,
        ) ?? 'event';

        $filename = trim($slug, '-').'-report.csv';

        return response()->streamDownload(
            function () use ($event): void {
                echo $this->reports->toCsv($event);
            },
            $filename,
            ['Content-Type' => 'text/csv; charset=UTF-8'],
        );
    }

    /**
     * Both actions guard on the same condition. Extracted so the two
     * entry points cannot drift.
     */
    private function assertReportable(Event $event): void
    {
        if (! $event->isRunningEvent() || ! in_array($event->status, [
            EventStatus::Ongoing,
            EventStatus::Completed,
        ], true)) {
            abort(404);
        }
    }
}
