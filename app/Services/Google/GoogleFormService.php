<?php

namespace App\Services\Google;

use App\Models\Event;
use App\Models\GoogleIntegration;
use App\Models\RegistrationSetup;
use App\Models\RegistrationSetupChange;
use App\Models\User;
use Google\Client as GoogleClient;
use Google\Service\Drive as GoogleDrive;
use Google\Service\Forms as GoogleForms;
use Google\Service\Forms\BatchUpdateFormRequest;
use Google\Service\Forms\ChoiceQuestion;
use Google\Service\Forms\CreateItemRequest;
use Google\Service\Forms\DeleteItemRequest;
use Google\Service\Forms\Form;
use Google\Service\Forms\Info;
use Google\Service\Forms\Item;
use Google\Service\Forms\Location;
use Google\Service\Forms\Option;
use Google\Service\Forms\PublishSettings;
use Google\Service\Forms\PublishState;
use Google\Service\Forms\Question;
use Google\Service\Forms\QuestionItem;
use Google\Service\Forms\Request as FormsRequest;
use Google\Service\Forms\SetPublishSettingsRequest;
use Google\Service\Forms\TextQuestion;
use Google\Service\Forms\UpdateItemRequest;
use Illuminate\Support\Facades\Log;

class GoogleFormService
{
    public function __construct(
        private readonly GoogleOAuthService $oauthService,
    ) {
    }

    public function createForEvent(Event $event, User $actor): RegistrationSetup
    {
        [$owner, $integration] = $this->resolveGoogleAccount($event, $actor);
        $client = $this->buildClientWithToken($integration);
        $service = new GoogleForms($client);

        $form = null;

        try {
            $form = $this->createEmptyForm($service, $event);
            $formId = $form->getFormId();

            if (! $formId) {
                throw new \RuntimeException('Google did not return a form ID.');
            }

            $this->addQuestionsToForm($service, $formId, $event);
            $this->publishForm($service, $formId);

            $setup = RegistrationSetup::create([
                'event_id' => $event->id,
                'google_form_id' => $formId,
                'form_url' => $form->getResponderUri(),
                'status' => 'published',
                'created_by' => $actor->id,
            ]);

            $this->logChange($setup, $actor, $integration, 'form_created', null, null, [
                'form_id' => $formId,
                'form_url' => $form->getResponderUri(),
                'created_for_owner' => $owner->id,
            ]);

            return $setup;
        } catch (\Throwable $e) {
            if ($form && $form->getFormId()) {
                $this->bestEffortDeleteForm($client, $form->getFormId());
            }

            Log::error('google.form_create_failed', [
                'event_id' => $event->id,
                'actor_id' => $actor->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function getQuestions(RegistrationSetup $setup, User $actor): array
    {
        $owner = $setup->creator ?? $actor;
        [, $integration] = $this->resolveGoogleAccountForOwner($owner, $actor);
        $client = $this->buildClientWithToken($integration);
        $service = new GoogleForms($client);

        $form = $service->forms->get($setup->google_form_id);
        $items = $form->getItems() ?? [];

        $questions = [];

        foreach ($items as $item) {
            $questionItem = $item->getQuestionItem();

            if ($questionItem === null) {
                continue;
            }

            $question = $questionItem->getQuestion();

            if ($question === null) {
                continue;
            }

            $type = $this->detectQuestionType($question);

            $questions[] = [
                'id' => (string) $item->getItemId(),
                'title' => (string) ($item->getTitle() ?? ''),
                'type' => $type,
                'type_label' => $this->labelForType($type),
                'required' => (bool) $question->getRequired(),
                'options' => $this->extractOptions($question),
            ];
        }

        return $questions;
    }

    public function syncQuestions(RegistrationSetup $setup, array $desired, User $actor): array
    {
        $owner = $setup->creator ?? $actor;
        [, $integration] = $this->resolveGoogleAccountForOwner($owner, $actor);
        $client = $this->buildClientWithToken($integration);
        $service = new GoogleForms($client);

        $form = $service->forms->get($setup->google_form_id);
        $serverItems = $form->getItems() ?? [];

        $serverById = [];
        foreach ($serverItems as $i => $item) {
            $serverById[(string) $item->getItemId()] = $i;
        }

        $desiredIds = [];
        foreach ($desired as $q) {
            if (! empty($q['id'])) {
                $desiredIds[] = (string) $q['id'];
            }
        }

        $deleteIndices = [];
        foreach ($serverById as $id => $idx) {
            if (! in_array($id, $desiredIds, true)) {
                $deleteIndices[] = $idx;
            }
        }
        rsort($deleteIndices);

        $postDeleteIndex = [];
        $counter = 0;
        foreach ($serverById as $id => $idx) {
            if (in_array($idx, $deleteIndices, true)) {
                continue;
            }
            $postDeleteIndex[$id] = $counter++;
        }

        $requests = [];

        foreach ($deleteIndices as $idx) {
            $requests[] = new FormsRequest([
                'deleteItem' => new DeleteItemRequest([
                    'location' => new Location(['index' => $idx]),
                ]),
            ]);
        }

        foreach ($desired as $q) {
            $id = $q['id'] ?? null;
            if (! $id || ! isset($postDeleteIndex[$id])) {
                continue;
            }

            $item = new Item();
            $item->setTitle($q['title']);
            $item->setQuestionItem(new QuestionItem([
                'question' => $this->buildQuestion($q),
            ]));

            $requests[] = new FormsRequest([
                'updateItem' => new UpdateItemRequest([
                    'item' => $item,
                    'location' => new Location(['index' => $postDeleteIndex[$id]]),
                    'updateMask' => 'title,questionItem',
                ]),
            ]);
        }

        $nextIndex = count($serverById) - count($deleteIndices);
        foreach ($desired as $q) {
            if (! empty($q['id'])) {
                continue;
            }
            $requests[] = $this->buildCreateItemRequest($q, $nextIndex);
            $nextIndex++;
        }

        if (! empty($requests)) {
            $batch = new BatchUpdateFormRequest(['requests' => $requests]);
            $service->forms->batchUpdate($setup->google_form_id, $batch);
        }

        $this->logChange(
            $setup,
            $actor,
            $integration,
            'questions_synced',
            null,
            null,
            [
                'deleted' => count($deleteIndices),
                'updated' => count(array_filter($desired, fn ($q) => ! empty($q['id']))),
                'created' => count(array_filter($desired, fn ($q) => empty($q['id']))),
            ],
        );

        return $this->getQuestions($setup, $actor);
    }

    /**
     * Ask Google whether the Form is now linked to a Sheet.
     * If yes, save the Sheet ID + construct its URL directly.
     *
     * Note: we deliberately do NOT call the Drive API here. The `drive.file`
     * OAuth scope only grants access to files created by this OAuth client.
     * Sheets created by the user's Apps Script fall outside that scope, so
     * drive.files.get() would return 404 even though the Sheet exists.
     *
     * @return bool true if a link was found (and saved); false if not yet linked
     */
    public function verifySheetLink(RegistrationSetup $setup, User $actor): bool
    {
        $owner = $setup->creator ?? $actor;
        [, $integration] = $this->resolveGoogleAccountForOwner($owner, $actor);
        $client = $this->buildClientWithToken($integration);

        $formsService = new GoogleForms($client);
        $form = $formsService->forms->get($setup->google_form_id);
        $sheetId = $form->getLinkedSheetId();

        if (! $sheetId) {
            return false;
        }

        // Standard Google Sheets URL pattern — no Drive API call required.
        $sheetUrl = "https://docs.google.com/spreadsheets/d/{$sheetId}/edit";

        // Derive the expected Sheet name to match the Apps Script convention.
        $eventName = $setup->event?->event_name ?? 'Registration';
        $sheetName = $eventName.' — Registration (Responses)';

        $setup->update([
            'google_sheet_id' => $sheetId,
            'sheet_url' => $sheetUrl,
            'sheet_linked_at' => now(),
        ]);

        $this->logChange(
            $setup,
            $actor,
            $integration,
            'sheet_linked',
            null,
            $sheetName,
            [
                'sheet_id' => $sheetId,
                'sheet_url' => $sheetUrl,
                'sheet_name' => $sheetName,
            ],
        );

        return true;
    }

    /**
     * Return the Apps Script snippet the organizer pastes into the Form's
     * Script Editor to create + link a response Sheet.
     */
    public function buildLinkScript(): string
    {
        return <<<'JS'
function linkRegistrationSheet() {
  const form = FormApp.getActiveForm();

  let alreadyLinked = false;
  try {
    const existing = form.getDestinationId();
    alreadyLinked = existing && existing !== '';
  } catch (e) {
    // Throws when no destination exists — that's what we want.
    alreadyLinked = false;
  }

  if (alreadyLinked) {
    Logger.log('A Sheet is already linked to this Form.');
    return;
  }

  const sheetName = form.getTitle() + ' (Responses)';
  const sheet = SpreadsheetApp.create(sheetName);

  form.setDestination(
    FormApp.DestinationType.SPREADSHEET,
    sheet.getId()
  );

  Logger.log('Sheet linked: ' + sheet.getUrl());
}
JS;
    }

    private function resolveGoogleAccount(Event $event, User $actor): array
    {
        return $this->resolveGoogleAccountForOwner($event->creator ?? $actor, $actor);
    }

    private function resolveGoogleAccountForOwner(User $owner, User $actor): array
    {
        if ($actor->googleIntegration !== null) {
            return [$actor, $actor->googleIntegration];
        }

        if ($owner->googleIntegration !== null) {
            return [$owner, $owner->googleIntegration];
        }

        throw new \RuntimeException(
            'No Google account is connected. Go to Settings → Google and connect one first.'
        );
    }

    private function buildClientWithToken(GoogleIntegration $integration): GoogleClient
    {
        $client = $this->oauthService->client();

        $client->setHttpClient(new \GuzzleHttp\Client([
            'timeout' => 25,
            'connect_timeout' => 10,
        ]));

        $remainingSeconds = $integration->expires_at
            ? max(0, $integration->expires_at->getTimestamp() - now()->getTimestamp())
            : 3600;

        $client->setAccessToken([
            'access_token' => $integration->access_token,
            'refresh_token' => $integration->refresh_token,
            'expires_in' => $remainingSeconds,
            'created' => now()->getTimestamp(),
        ]);

        if ($client->isAccessTokenExpired() && $integration->refresh_token) {
            $newToken = $client->fetchAccessTokenWithRefreshToken($integration->refresh_token);

            if (isset($newToken['error'])) {
                throw new \RuntimeException('Google token refresh failed: '.$newToken['error']);
            }

            $integration->update([
                'access_token' => $newToken['access_token'],
                'expires_at' => now()->addSeconds((int) ($newToken['expires_in'] ?? 3600)),
            ]);

            $client->setAccessToken($newToken);
        }

        return $client;
    }

    private function createEmptyForm(GoogleForms $service, Event $event): Form
    {
        $form = new Form();
        $form->setInfo(new Info(['title' => $event->event_name.' — Registration']));

        return $service->forms->create($form);
    }

    private function addQuestionsToForm(GoogleForms $service, string $formId, Event $event): void
    {
        $requests = [];
        $index = 0;

        foreach ($this->defaultQuestions() as $question) {
            $requests[] = $this->buildCreateItemRequest($question, $index);
            $index++;
        }

        $event->loadMissing('eventOptions');
        $grouped = $event->eventOptions->groupBy('option_type');

        foreach ($grouped as $type => $options) {
            $choices = $options->pluck('option_name')->filter()->values()->all();

            if (empty($choices)) {
                continue;
            }

            $requests[] = $this->buildCreateItemRequest([
                'title' => $this->humanize($type),
                'type' => 'MULTIPLE_CHOICE',
                'required' => true,
                'options' => $choices,
            ], $index);

            $index++;
        }

        if (! empty($requests)) {
            $batch = new BatchUpdateFormRequest(['requests' => $requests]);
            $service->forms->batchUpdate($formId, $batch);
        }
    }

    private function defaultQuestions(): array
    {
        return [
            ['title' => 'First name', 'type' => 'SHORT_ANSWER', 'required' => true],
            ['title' => 'Last name', 'type' => 'SHORT_ANSWER', 'required' => true],
            ['title' => 'Contact number', 'type' => 'SHORT_ANSWER', 'required' => true],
            ['title' => 'Email address', 'type' => 'SHORT_ANSWER', 'required' => true],
            ['title' => 'Age', 'type' => 'SHORT_ANSWER', 'required' => true],
            ['title' => 'Address', 'type' => 'PARAGRAPH', 'required' => true],
        ];
    }

    private function buildCreateItemRequest(array $data, int $index): FormsRequest
    {
        $item = new Item();
        $item->setTitle($data['title']);
        $item->setQuestionItem(new QuestionItem([
            'question' => $this->buildQuestion($data),
        ]));

        return new FormsRequest([
            'createItem' => new CreateItemRequest([
                'item' => $item,
                'location' => new Location(['index' => $index]),
            ]),
        ]);
    }

    private function buildQuestion(array $data): Question
    {
        $question = new Question();
        $question->setRequired((bool) ($data['required'] ?? false));

        switch ($data['type'] ?? 'SHORT_ANSWER') {
            case 'PARAGRAPH':
                $question->setTextQuestion(new TextQuestion(['paragraph' => true]));
                break;

            case 'MULTIPLE_CHOICE':
            case 'CHECKBOX':
            case 'DROP_DOWN':
                $choiceType = match ($data['type']) {
                    'CHECKBOX' => 'CHECKBOX',
                    'DROP_DOWN' => 'DROP_DOWN',
                    default => 'RADIO',
                };
                $choice = new ChoiceQuestion();
                $choice->setType($choiceType);
                $choice->setOptions(array_map(
                    fn (string $label) => new Option(['value' => $label]),
                    $data['options'] ?? [],
                ));
                $question->setChoiceQuestion($choice);
                break;

            default:
                $question->setTextQuestion(new TextQuestion(['paragraph' => false]));
                break;
        }

        return $question;
    }

    private function publishForm(GoogleForms $service, string $formId): void
    {
        $publishState = new PublishState();
        $publishState->setIsPublished(true);
        $publishState->setIsAcceptingResponses(true);

        $publishSettings = new PublishSettings();
        $publishSettings->setPublishState($publishState);

        $request = new SetPublishSettingsRequest();
        $request->setPublishSettings($publishSettings);

        $service->forms->setPublishSettings($formId, $request);
    }

    private function bestEffortDeleteForm(GoogleClient $client, string $formId): void
    {
        try {
            $drive = new GoogleDrive($client);
            $drive->files->delete($formId);
        } catch (\Throwable) {
        }
    }

    private function logChange(
        RegistrationSetup $setup,
        User $actor,
        GoogleIntegration $integration,
        string $action,
        ?string $itemId = null,
        ?string $itemTitle = null,
        ?array $changes = null,
    ): void {
        RegistrationSetupChange::create([
            'registration_setup_id' => $setup->id,
            'user_id' => $actor->id,
            'google_email_used' => $integration->google_email,
            'action' => $action,
            'google_item_id' => $itemId,
            'item_title' => $itemTitle,
            'changes' => $changes,
        ]);
    }

    private function humanize(string $value): string
    {
        return ucwords(str_replace('_', ' ', $value));
    }

    private function detectQuestionType(Question $question): string
    {
        $text = $question->getTextQuestion();
        if ($text !== null) {
            return $text->getParagraph() ? 'PARAGRAPH' : 'SHORT_ANSWER';
        }

        $choice = $question->getChoiceQuestion();
        if ($choice !== null) {
            return strtoupper((string) $choice->getType());
        }

        if ($question->getDateQuestion() !== null) return 'DATE';
        if ($question->getTimeQuestion() !== null) return 'TIME';
        if ($question->getScaleQuestion() !== null) return 'SCALE';

        return 'UNKNOWN';
    }

    private function labelForType(string $type): string
    {
        return match ($type) {
            'SHORT_ANSWER' => 'Short answer',
            'PARAGRAPH' => 'Paragraph',
            'RADIO', 'MULTIPLE_CHOICE' => 'Multiple choice',
            'CHECKBOX' => 'Checkbox',
            'DROP_DOWN' => 'Dropdown',
            'DATE' => 'Date',
            'TIME' => 'Time',
            'SCALE' => 'Linear scale',
            default => ucfirst(strtolower(str_replace('_', ' ', $type))),
        };
    }

    private function extractOptions(Question $question): array
    {
        $choice = $question->getChoiceQuestion();
        if ($choice === null) {
            return [];
        }

        $options = $choice->getOptions() ?? [];

        return array_values(array_filter(array_map(
            fn ($option) => $option->getValue(),
            $options,
        )));
    }
}
