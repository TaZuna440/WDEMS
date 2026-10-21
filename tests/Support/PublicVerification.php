<?php

use App\Mail\RegistrationVerificationOtpMail;
use App\Models\Event;
use App\Models\RegistrationVerificationChallenge;
use App\Services\RegistrationVerification\RegistrationVerificationService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/** Complete the real public verification endpoints for regression fixtures. */
function completePublicRegistration(TestCase $test, Event $event, array $payload): TestResponse
{
    Mail::fake();
    $start = $test->post('/r/'.$event->registration_slug, $payload);
    $start->assertSessionHasNoErrors()->assertRedirect('/r/'.$event->registration_slug.'/verification');
    $cookie = RegistrationVerificationService::cookieName($event);
    $test->withCookie($cookie, $start->getCookie($cookie)->getValue());
    $url = '/r/'.$event->registration_slug.'/verification';
    if ($event->isCommonFieldRequired('email')) {
        $code = Mail::sent(RegistrationVerificationOtpMail::class)->last()->code;
        $test->post($url.'/email', ['code' => $code])->assertSessionHasNoErrors();
    }
    if ($event->isCommonFieldRequired('contact_number')) {
        // without stubbing the Admin SDK. Write the verified marker
        // directly — the helper's job is to produce a finalized
        // registration, not to exercise the phone path itself.
        $challenge = RegistrationVerificationChallenge::where('event_id', $event->id)->latest('id')->firstOrFail();
        $challenge->forceFill(['phone_verified_at' => now()])->save();
    }

    return $test->post($url.'/complete');
}
