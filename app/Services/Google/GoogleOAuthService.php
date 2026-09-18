<?php

namespace App\Services\Google;

use App\Models\GoogleIntegration;
use App\Models\User;
use Google\Client as GoogleClient;
use Google\Service\Oauth2 as GoogleOauth2;
use Illuminate\Support\Facades\Log;

class GoogleOAuthService
{
    public const SCOPES = [
        'openid',
        'https://www.googleapis.com/auth/userinfo.email',
        'https://www.googleapis.com/auth/userinfo.profile',
        'https://www.googleapis.com/auth/forms.body',
        'https://www.googleapis.com/auth/forms.responses.readonly',
        'https://www.googleapis.com/auth/spreadsheets',
        'https://www.googleapis.com/auth/drive.file',
        'https://www.googleapis.com/auth/script.projects',
    ];

    public function client(): GoogleClient
    {
        $client = new GoogleClient();
        $client->setClientId((string) config('services.google.client_id'));
        $client->setClientSecret((string) config('services.google.client_secret'));
        $client->setRedirectUri((string) config('services.google.redirect'));
        $client->setScopes(self::SCOPES);
        $client->setAccessType('offline');
        $client->setPrompt('consent');
        $client->setIncludeGrantedScopes(true);

        return $client;
    }

    public function authUrl(): string
    {
        return $this->client()->createAuthUrl();
    }

    public function handleCallback(User $user, string $code): GoogleIntegration
    {
        $client = $this->client();
        $token = $client->fetchAccessTokenWithAuthCode($code);

        if (isset($token['error'])) {
            throw new \RuntimeException('Google OAuth error: '.$token['error']);
        }

        $client->setAccessToken($token);
        $oauth2 = new GoogleOauth2($client);
        $userInfo = $oauth2->userinfo->get();

        $integration = GoogleIntegration::updateOrCreate(
            ['user_id' => $user->id],
            [
                'google_account_id' => $userInfo->getId(),
                'google_email' => $userInfo->getEmail(),
                'access_token' => $token['access_token'],
                'refresh_token' => $token['refresh_token'] ?? null,
                'expires_at' => now()->addSeconds((int) ($token['expires_in'] ?? 3600)),
                'scopes' => $token['scope'] ?? self::SCOPES,
            ],
        );

        Log::info('google.connected', [
            'user_id' => $user->id,
            'google_email' => $userInfo->getEmail(),
        ]);

        return $integration;
    }

    public function disconnect(User $user): void
    {
        $integration = $user->googleIntegration;

        if ($integration === null) {
            return;
        }

        try {
            $client = $this->client();
            $client->setAccessToken($integration->access_token);
            $client->revokeToken();
        } catch (\Throwable $e) {
            Log::warning('google.revoke_failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }

        $integration->delete();

        Log::info('google.disconnected', ['user_id' => $user->id]);
    }
}
