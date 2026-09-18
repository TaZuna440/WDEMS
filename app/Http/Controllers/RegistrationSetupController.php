<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Services\Google\GoogleFormService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class RegistrationSetupController extends Controller
{
    public function show(Request $request, Event $event): Response
    {
        $event->load('registrationSetup');

        $canCreate = true;
        $reason = null;

        if ($event->status->value === 'draft') {
            $canCreate = false;
            $reason = 'Configure event options first — the registration form will be built from them.';
        } elseif ($event->registrationSetup !== null) {
            $canCreate = false;
        }

        $questions = [];
        $questionsError = null;
        $canEditQuestions = false;
        $history = [];
        $linkScript = null;

        if ($event->registrationSetup !== null) {
            try {
                $questions = app(GoogleFormService::class)
                    ->getQuestions($event->registrationSetup, $request->user());
            } catch (\Throwable $e) {
                Log::warning('registration.setup.fetch_questions_failed', [
                    'event_id' => $event->id,
                    'error' => $e->getMessage(),
                ]);

                $questionsError = 'Could not load questions from Google. Try again later.';
            }

            $canEditQuestions = $event->status->value === 'configured';

            $history = $event->registrationSetup
                ->changes()
                ->with('user:id,name')
                ->latest('id')
                ->limit(50)
                ->get()
                ->map(fn ($change) => [
                    'id' => $change->id,
                    'action' => $change->action,
                    'item_title' => $change->item_title,
                    'user_name' => $change->user?->name,
                    'google_email_used' => $change->google_email_used,
                    'changes' => $change->changes,
                    'created_at' => $change->created_at?->toIso8601String(),
                ])
                ->values();

            if ($event->registrationSetup->google_sheet_id === null) {
                $linkScript = app(GoogleFormService::class)->buildLinkScript();
            }
        }

        $setup = $event->registrationSetup;

        $setupData = $setup ? [
            'id' => $setup->id,
            'form_url' => $setup->form_url,
            'form_edit_url' => $setup->google_form_id
                ? "https://docs.google.com/forms/d/{$setup->google_form_id}/edit"
                : null,
            'status' => $setup->status,
            'created_at' => $setup->created_at?->toIso8601String(),
            'google_sheet_id' => $setup->google_sheet_id,
            'sheet_url' => $setup->sheet_url,
            'sheet_linked_at' => $setup->sheet_linked_at?->toIso8601String(),
        ] : null;

        return Inertia::render('events/registration-setup', [
            'event' => [
                'id' => $event->id,
                'event_name' => $event->event_name,
                'status' => $event->status->value,
                'status_label' => $event->status->label(),
            ],
            'setup' => $setupData,
            'questions' => $questions,
            'questions_error' => $questionsError,
            'can_edit_questions' => $canEditQuestions,
            'can_create' => $canCreate,
            'can_create_reason' => $reason,
            'history' => $history,
            'link_script' => $linkScript,
        ]);
    }

    public function store(Request $request, Event $event): RedirectResponse
    {
        if ($event->status->value === 'draft') {
            return back()->withErrors(['form' => 'Configure the event before setting up registration.']);
        }

        if ($event->registrationSetup !== null) {
            return back()->withErrors(['form' => 'A registration form already exists for this event.']);
        }

        try {
            app(GoogleFormService::class)->createForEvent($event, $request->user());
        } catch (\Throwable $e) {
            Log::error('registration.setup.create_failed', [
                'event_id' => $event->id,
                'user_id' => $request->user()->id,
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors(['form' => $e->getMessage()]);
        }

        return redirect()
            ->route('events.registration.setup', $event)
            ->with('status', 'form-created');
    }

    public function syncQuestions(Request $request, Event $event): RedirectResponse
    {
        if ($event->registrationSetup === null) {
            return back()->withErrors(['question' => 'No registration form exists for this event.']);
        }

        if ($event->status->value !== 'configured') {
            return back()->withErrors(['question' => 'Questions can only be edited while the event is in the "configured" state.']);
        }

        $validated = $request->validate([
            'questions' => ['required', 'array'],
            'questions.*.id' => ['nullable', 'string', 'max:255'],
            'questions.*.title' => ['required', 'string', 'max:255'],
            'questions.*.type' => ['required', 'string', 'in:SHORT_ANSWER,PARAGRAPH,MULTIPLE_CHOICE,CHECKBOX,DROP_DOWN'],
            'questions.*.required' => ['boolean'],
            'questions.*.options' => ['array'],
            'questions.*.options.*' => ['string', 'max:255'],
        ]);

        try {
            app(GoogleFormService::class)
                ->syncQuestions($event->registrationSetup, $validated['questions'], $request->user());
        } catch (\Throwable $e) {
            Log::error('registration.setup.sync_questions_failed', [
                'event_id' => $event->id,
                'user_id' => $request->user()->id,
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors(['question' => $e->getMessage()]);
        }

        return back()->with('status', 'questions-synced');
    }

    public function verifySheet(Request $request, Event $event): RedirectResponse
    {
        if ($event->registrationSetup === null) {
            return back()->withErrors([
                'sheet' => 'No registration form exists for this event yet.',
            ]);
        }

        if ($event->registrationSetup->google_sheet_id !== null) {
            return back()->with('status', 'sheet-already-linked');
        }

        try {
            $linked = app(GoogleFormService::class)
                ->verifySheetLink($event->registrationSetup, $request->user());
        } catch (\Throwable $e) {
            Log::error('registration.setup.verify_sheet_failed', [
                'event_id' => $event->id,
                'user_id' => $request->user()->id,
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors([
                'sheet' => 'Could not check the Sheet link: '.$e->getMessage(),
            ]);
        }

        if (! $linked) {
            return back()->withErrors([
                'sheet' => 'No Sheet is linked to the Form yet. Make sure you ran the script in the Script Editor, then try again.',
            ]);
        }

        return back()->with('status', 'sheet-linked');
    }
}
