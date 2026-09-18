<?php

namespace App\Http\Controllers;

use App\Services\Google\GoogleOAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GoogleIntegrationController extends Controller
{
    public function show(Request $request): Response
    {
        $integration = $request->user()->googleIntegration;

        return Inertia::render('settings/google', [
            'connected' => $integration !== null,
            'google_email' => $integration?->google_email,
            'connected_at' => $integration?->created_at?->toDateTimeString(),
        ]);
    }

    public function redirect(Request $request): RedirectResponse
    {
        return redirect()->away(app(GoogleOAuthService::class)->authUrl());
    }

    public function callback(Request $request): RedirectResponse
    {
        if ($request->has('error')) {
            return redirect()
                ->route('settings.google')
                ->withErrors(['google' => 'Google denied the connection: '.$request->query('error')]);
        }

        $code = (string) $request->query('code');

        if ($code === '') {
            return redirect()
                ->route('settings.google')
                ->withErrors(['google' => 'Missing authorization code from Google.']);
        }

        try {
            app(GoogleOAuthService::class)->handleCallback($request->user(), $code);
        } catch (\Throwable $e) {
            return redirect()
                ->route('settings.google')
                ->withErrors(['google' => 'Failed to connect Google: '.$e->getMessage()]);
        }

        return redirect()
            ->route('settings.google')
            ->with('status', 'google-connected');
    }

    public function destroy(Request $request): RedirectResponse
    {
        app(GoogleOAuthService::class)->disconnect($request->user());

        return redirect()
            ->route('settings.google')
            ->with('status', 'google-disconnected');
    }
}
