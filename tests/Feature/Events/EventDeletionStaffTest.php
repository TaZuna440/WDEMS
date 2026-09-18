<?php

use App\Mail\EventDeletionOtpMail;
use App\Models\Event;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();
});

function staff_event_ctx(): array
{
    $staff = User::factory()->create(['role' => 'staff']);
    $event = Event::create([
        'created_by' => $staff->id,
        'event_type' => 'community_run',
        'event_name' => 'Staff OTP Event',
        'event_date' => now()->toDateString(),
        'status' => 'draft',
    ]);

    return [$staff, $event];
}

function capture_otp_code(): string
{
    $code = null;
    Mail::assertSent(EventDeletionOtpMail::class, function (EventDeletionOtpMail $mail) use (&$code) {
        $code = $mail->code;
        return true;
    });

    if ($code === null) {
        throw new RuntimeException('No OTP email was sent.');
    }

    return $code;
}

it('lets staff request an OTP and sends the code via mail', function () {
    [$staff, $event] = staff_event_ctx();

    $response = $this->actingAs($staff)
        ->post("/events/{$event->id}/deletion/request-otp");

    $response->assertRedirect();
    Mail::assertSent(EventDeletionOtpMail::class);
});

it('rejects a second OTP request within the cooldown window', function () {
    [$staff, $event] = staff_event_ctx();

    $this->actingAs($staff)->post("/events/{$event->id}/deletion/request-otp");
    $second = $this->actingAs($staff)->post("/events/{$event->id}/deletion/request-otp");

    $second->assertSessionHasErrors('code');
});

it('deletes the event when staff verifies with the correct code', function () {
    [$staff, $event] = staff_event_ctx();

    $this->actingAs($staff)->post("/events/{$event->id}/deletion/request-otp");
    $code = capture_otp_code();

    $response = $this->actingAs($staff)
        ->post("/events/{$event->id}/deletion/verify-otp", ['code' => $code]);

    $response->assertRedirect(route('events.index'));
    expect(Event::find($event->id))->toBeNull();
});

it('does not delete the event when staff supplies the wrong code', function () {
    [$staff, $event] = staff_event_ctx();

    $this->actingAs($staff)->post("/events/{$event->id}/deletion/request-otp");

    $response = $this->actingAs($staff)
        ->post("/events/{$event->id}/deletion/verify-otp", ['code' => '000000']);

    $response->assertSessionHasErrors('code');
    expect(Event::find($event->id))->not->toBeNull();
});

it('locks after five wrong verification attempts', function () {
    [$staff, $event] = staff_event_ctx();

    $this->actingAs($staff)->post("/events/{$event->id}/deletion/request-otp");

    for ($i = 0; $i < 4; $i++) {
        $this->actingAs($staff)
            ->post("/events/{$event->id}/deletion/verify-otp", ['code' => '000000'])
            ->assertSessionHasErrors('code');
    }

    $fifth = $this->actingAs($staff)
        ->post("/events/{$event->id}/deletion/verify-otp", ['code' => '000000']);

    $fifth->assertSessionHasErrors('code');
    expect(Event::find($event->id))->not->toBeNull();
});

it('returns an error when verifying without requesting a code', function () {
    [$staff, $event] = staff_event_ctx();

    $response = $this->actingAs($staff)
        ->post("/events/{$event->id}/deletion/verify-otp", ['code' => '123456']);

    $response->assertSessionHasErrors('code');
    expect(Event::find($event->id))->not->toBeNull();
});

it('does not let staff delete directly via the destroy endpoint', function () {
    [$staff, $event] = staff_event_ctx();

    $response = $this->actingAs($staff)->delete("/events/{$event->id}");

    $response->assertForbidden();
    expect(Event::find($event->id))->not->toBeNull();
});

it('does not let admin hit the request-otp endpoint', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $event = Event::create([
        'created_by' => $admin->id,
        'event_type' => 'community_run',
        'event_name' => 'Admin Blocked',
        'event_date' => now()->toDateString(),
        'status' => 'draft',
    ]);

    $response = $this->actingAs($admin)
        ->post("/events/{$event->id}/deletion/request-otp");

    $response->assertForbidden();
});

it('does not let admin hit the verify-otp endpoint', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $event = Event::create([
        'created_by' => $admin->id,
        'event_type' => 'community_run',
        'event_name' => 'Admin Blocked',
        'event_date' => now()->toDateString(),
        'status' => 'draft',
    ]);

    $response = $this->actingAs($admin)
        ->post("/events/{$event->id}/deletion/verify-otp", ['code' => '123456']);

    $response->assertForbidden();
});

it('blocks guests from all deletion endpoints', function () {
    $staff = User::factory()->create(['role' => 'staff']);
    $event = Event::create([
        'created_by' => $staff->id,
        'event_type' => 'community_run',
        'event_name' => 'Guest Blocked',
        'event_date' => now()->toDateString(),
        'status' => 'draft',
    ]);

    $this->delete("/events/{$event->id}")->assertRedirect(route('login'));
    $this->post("/events/{$event->id}/deletion/request-otp")->assertRedirect(route('login'));
    $this->post("/events/{$event->id}/deletion/verify-otp", ['code' => '123456'])
        ->assertRedirect(route('login'));

    expect(Event::find($event->id))->not->toBeNull();
});
