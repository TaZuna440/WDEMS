<?php

namespace App\Support;

use App\Models\Event;

/**
 * Fixed legal texts for the public registration wizard (Step 1).
 *
 * Decision 2026-10-19: fixed template with {event_name} injection
 * (v1). Per-event editable text deferred — when that ships, these
 * become the fallback defaults.
 *
 * The wizard renders these in Step 1 (disclaimer + privacy consent
 * checkboxes). finalize() records PRIVACY_NOTICE_VERSION on the
 * registration as the compliance trail (closes audit P5).
 *
 * Texts mirror docs: the disclaimer wording follows the JRV
 * community-run form; the privacy consent is Mark's exact text.
 */
class RegistrationLegalText
{
    /**
     * Version stamped on every registration at finalize().
     *
     * Bump when the privacy consent text changes. Old rows keep
     * their original version — that is the point of the column.
     */
    public const PRIVACY_NOTICE_VERSION = 'v1';

    public const CHURCH_PRIVACY_NOTICE_VERSION = 'v2';

    public static function privacyNoticeVersionFor(Event $event): string
    {
        return $event->isChurchEvent()
            ? self::CHURCH_PRIVACY_NOTICE_VERSION
            : self::PRIVACY_NOTICE_VERSION;
    }

    /**
     * Disclaimer paragraphs with {event_name} replaced.
     *
     * @return array<int, string>
     */
    public static function disclaimerFor(Event $event): array
    {
        $template = $event->isChurchEvent()
            ? self::churchDisclaimerTemplate()
            : self::runningDisclaimerTemplate();

        return array_map(
            fn (string $paragraph) => str_replace('{event_name}', $event->event_name, $paragraph),
            $template,
        );
    }

    /**
     * Privacy consent paragraph with {event_name} replaced.
     */
    public static function privacyConsentFor(Event $event): string
    {
        $template = $event->isChurchEvent()
            ? self::CHURCH_PRIVACY_CONSENT_TEMPLATE
            : self::RUNNING_PRIVACY_CONSENT_TEMPLATE;

        return str_replace('{event_name}', $event->event_name, $template);
    }

    /** @return array<int, string> */
    private static function runningDisclaimerTemplate(): array
    {
        return [
            'I understand that participation in the {event_name} is voluntary. I am physically capable of participating and understand that running, walking, and other physical activities may involve risks, including but not limited to fatigue, falls, accidents, injuries, or other unforeseen circumstances.',
            'I understand that the activity will proceed rain or shine, unless {event_name} organizers determine that weather or other conditions make the event unsafe. Any changes, cancellation, or rescheduling will be announced through the official channels.',
            'I agree to participate responsibly, listen to my body, and take appropriate precautions based on my own physical condition. If I have any medical condition or require regular medication, I am responsible for bringing my own necessary medication and taking appropriate precautions.',
            'Participants are encouraged to bring their own water or hydration and stay properly hydrated before, during, and after the run.',
            'There will be limited drop-bag space available. Participants are encouraged to bring only essential belongings. I understand that {event_name} is not responsible for lost, damaged, or stolen personal items.',
            'I understand that {event_name}, its organizers, volunteers, hosts, partners, and affiliated individuals cannot be held responsible for personal injury, loss, damage, or other incidents arising from my participation, except where liability cannot legally be excluded.',
            'By registering, I confirm that I have read, understood, and voluntarily agree to these terms and reminders.',
        ];
    }

    /** @return array<int, string> */
    private static function churchDisclaimerTemplate(): array
    {
        return [
            'I understand that participation in {event_name} is voluntary. I agree to participate respectfully and follow reasonable instructions from the event organizers, church staff, volunteers, and venue personnel.',
            'I will conduct myself in a way that respects the purpose of this Church Event and the safety and dignity of other participants.',
            'I am responsible for my personal belongings and for providing accurate information needed to coordinate my participation in this event.',
            'Event arrangements may change or be cancelled. Organizers will communicate changes through official channels when practicable.',
            'By registering, I confirm that I have read and agree to these event participation guidelines.',
        ];
    }

    private const RUNNING_PRIVACY_CONSENT_TEMPLATE =
        'By submitting this form, you agree that {event_name} may collect and use the information you provide for event registration, communication, and coordination related to the Community Run. Your information will be kept private and handled responsibly.';

    private const CHURCH_PRIVACY_CONSENT_TEMPLATE =
        'By submitting this form, you agree that {event_name} may collect and use the information you provide for Church Event registration, communication, coordination, and related event administration. Your information will be handled responsibly.';
}
