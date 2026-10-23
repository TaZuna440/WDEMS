<?php

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
});

it('allows only admins to access user management', function () {
    $staff = User::factory()->create(['role' => 'staff']);
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($staff)->get('/admin/users')->assertForbidden();
    $this->actingAs($admin)->get('/admin/users')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('admin/users/index'));
});

it('creates an unverified user and sends a verification notification', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->post('/admin/users', [
        'name' => 'New Staff Member',
        'email' => 'new-staff@example.test',
        'role' => 'staff',
        'password' => 'A-secure-password-123',
        'password_confirmation' => 'A-secure-password-123',
    ])->assertRedirect(route('admin.users.index'))
        ->assertSessionHas('success', 'User created. A verification email has been sent to them.');

    $user = User::where('email', 'new-staff@example.test')->firstOrFail();

    expect(Hash::check('A-secure-password-123', $user->password))->toBeTrue()
        ->and($user->email_verified_at)->toBeNull();

    Notification::assertSentTo($user, VerifyEmail::class);

    expect(AuditLog::query()
        ->where('user_id', $admin->id)
        ->where('action', 'user.registered')
        ->where('target_type', User::class)
        ->where('target_id', $user->id)
        ->exists())->toBeTrue();
});
