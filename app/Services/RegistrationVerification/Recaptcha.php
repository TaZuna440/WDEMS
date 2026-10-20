<?php

namespace App\Services\RegistrationVerification;

use Illuminate\Support\Facades\Http;

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
        try {
            $response = Http::asForm()->timeout(10)->post('https://www.google.com/recaptcha/api/siteverify', [
                'secret' => config('services.recaptcha.secret_key'), 'response' => $token,
            ]);
            if ($response->successful() && $response->json('success') === true
                && $response->json('hostname') === parse_url(config('app.url'), PHP_URL_HOST)) {
                return null;
            }
        } catch (\Throwable) {
            // Tokens and credentials must not enter application logs.
        }

        return 'Human verification failed or expired. Please try again.';
    }
}
