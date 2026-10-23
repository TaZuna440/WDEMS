<?php

use App\Models\AuditLog;
use App\Models\Event;
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

it('rejects a weak password', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->post('/admin/users', [
        'name' => 'John Doe',
        'email' => 'weak-password@example.test',
        'role' => 'staff',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertSessionHasErrors('password');

    expect(User::where('email', 'weak-password@example.test')->exists())->toBeFalse();
});

it('accepts a strong password', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->post('/admin/users', [
        'name' => 'John Doe',
        'email' => 'strong-password@example.test',
        'role' => 'staff',
        'password' => 'TaZuna@440',
        'password_confirmation' => 'TaZuna@440',
    ])->assertRedirect(route('admin.users.index'));

    expect(User::where('email', 'strong-password@example.test')->exists())->toBeTrue();
});

it('rejects a keyboard-mashed user name', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->post('/admin/users', [
        'name' => 'gfdgfggdfdgdfgfd',
        'email' => 'invalid-name@example.test',
        'role' => 'staff',
        'password' => 'TaZuna@440',
        'password_confirmation' => 'TaZuna@440',
    ])->assertSessionHasErrors('name');

    expect(User::where('email', 'invalid-name@example.test')->exists())->toBeFalse();
});

it('allows an admin to edit a user name, email, and role', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $user = User::factory()->create([
        'name' => 'Old Name',
        'email' => 'old-name@example.test',
        'role' => 'staff',
    ]);

    $this->actingAs($admin)->get(route('admin.users.edit', $user))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/users/edit')
            ->where('user.name', 'Old Name')
            ->where('user.email', 'old-name@example.test'));

    $this->put(route('admin.users.update', $user), [
        'name' => 'Updated Name',
        'email' => 'updated-name@example.test',
        'role' => 'admin',
        'password' => '',
        'password_confirmation' => '',
    ])->assertRedirect(route('admin.users.index'));

    $user->refresh();
    expect($user->name)->toBe('Updated Name')
        ->and($user->email)->toBe('updated-name@example.test')
        ->and($user->role)->toBe('admin')
        ->and($user->email_verified_at)->toBeNull();
});

it('allows saving a user without changing their email', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $user = User::factory()->create([
        'name' => 'Existing User',
        'email' => 'existing-user@example.test',
        'role' => 'staff',
    ]);

    $this->actingAs($admin)->put(route('admin.users.update', $user), [
        'name' => $user->name,
        'email' => $user->email,
        'role' => $user->role,
        'password' => '',
        'password_confirmation' => '',
    ])->assertRedirect(route('admin.users.index'));
});

it('rejects an email already used by another user when editing', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $user = User::factory()->create(['email' => 'user@example.test']);
    User::factory()->create(['email' => 'admin@wdems.test']);

    $this->actingAs($admin)->put(route('admin.users.update', $user), [
        'name' => $user->name,
        'email' => 'admin@wdems.test',
        'role' => $user->role,
        'password' => '',
        'password_confirmation' => '',
    ])->assertSessionHasErrors('email');

    expect($user->fresh()->email)->toBe('user@example.test');
});

it('allows an admin to change a user password', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $user = User::factory()->create([
        'name' => 'Existing User',
        'password' => 'Initial-pass-123!',
    ]);

    $this->actingAs($admin)->put(route('admin.users.update', $user), [
        'name' => $user->name,
        'email' => $user->email,
        'role' => $user->role,
        'password' => 'New-secure-Password-456!',
        'password_confirmation' => 'New-secure-Password-456!',
    ])->assertRedirect(route('admin.users.index'));

    expect(Hash::check('New-secure-Password-456!', $user->fresh()->password))->toBeTrue();
});

it('allows an admin to delete a user without created events', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $user = User::factory()->create(['role' => 'staff']);

    $this->actingAs($admin)->delete(route('admin.users.destroy', $user))
        ->assertRedirect(route('admin.users.index'));

    expect(User::query()->whereKey($user->id)->exists())->toBeFalse();
});

it('prevents deleting a user who created events', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $user = User::factory()->create(['role' => 'staff']);
    Event::create([
        'created_by' => $user->id,
        'event_kind' => 'running',
        'event_type' => 'community_run',
        'event_name' => 'Owned event',
    ]);

    $this->actingAs($admin)->delete(route('admin.users.destroy', $user))
        ->assertSessionHasErrors([
            'user' => 'This user cannot be deleted because they have created events. You must reassign or delete their events first.',
        ]);

    expect($user->fresh())->not->toBeNull();
});

it('prevents an admin from deleting their own account', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->delete(route('admin.users.destroy', $admin))
        ->assertSessionHasErrors('user');

    expect($admin->fresh())->not->toBeNull();
});

it('prevents an admin from demoting their own account', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->put(route('admin.users.update', $admin), [
        'name' => $admin->name,
        'email' => $admin->email,
        'role' => 'staff',
        'password' => '',
        'password_confirmation' => '',
    ])->assertSessionHasErrors('role');

    expect($admin->fresh()->role)->toBe('admin');
});
