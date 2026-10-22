<?php

namespace App\Services\RegistrationVerification;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class Recaptcha
{
    public function error(?string $token): ?string
    {
        if (! config('services.recaptcha.site_key') || ! config('services.recaptcha.secret_key')) {
            return 'Human verification is not configured. Please contact the organizer.';
        }
        if (! is_string($token) || $token === '' || strlen($token) > 4096) {
            return 'Complete the human verification before sending email.';
        }
        $expectedHost = request()->getHost() ?: parse_url(config('app.url'), PHP_URL_HOST);

        try {
            $response = Http::asForm()->timeout(10)->post('https://www.google.com/recaptcha/api/siteverify', [
                'secret' => config('services.recaptcha.secret_key'), 'response' => $token,
            ]);
            $responseHost = $response->json('hostname');
            if ($response->successful() && $response->json('success') === true
                && is_string($expectedHost)
                && is_string($responseHost)
                && hash_equals(strtolower($expectedHost), strtolower($responseHost))) {
                return null;
            }

            Log::warning('registration.recaptcha.verification_failed', [
                'status' => $response->status(),
                'google_response' => $response->body(),
            ]);
        } catch (ConnectionException $exception) {
            Log::warning('registration.recaptcha.request_failed', [
                'exception' => $exception::class,
            ]);
        }

        return 'Human verification failed or expired. Please try again.';
    }
}
