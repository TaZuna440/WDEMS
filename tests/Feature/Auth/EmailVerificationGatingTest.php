<?php

use App\Models\User;

// Non-admin users must verify their email before reaching the app.

test('unverified users are blocked from the dashboard', function () {
    $user = User::factory()->unverified()->create();

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertRedirect(route('verification.notice'));
});

test('unverified users are blocked from the events list', function () {
    $user = User::factory()->unverified()->create();

    $response = $this->actingAs($user)->get(route('events.index'));

    $response->assertRedirect(route('verification.notice'));
});

test('verified users can reach the dashboard', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk();
});

test('verified users can reach the events list', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('events.index'));

    $response->assertOk();
});

// Admins bypass email verification (EnsureEmailIsVerifiedOrAdmin),
// mirroring the admin exemption in EnsureDeviceIsTrusted.

test('unverified admins can reach the dashboard', function () {
    $user = User::factory()->admin()->unverified()->create();

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk();
});

test('unverified admins can reach the admin dashboard', function () {
    $user = User::factory()->admin()->unverified()->create();

    $response = $this->actingAs($user)->get(route('admin.dashboard'));

    $response->assertOk();
});
