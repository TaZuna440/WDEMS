<?php

use App\Models\Event;
use App\Models\User;
use Illuminate\Http\Testing\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    $this->staff = User::factory()->create(['role' => 'staff', 'email_verified_at' => now(), 'email_two_factor_enabled' => false]);
    $this->event = Event::create(['created_by' => $this->staff->id, 'event_name' => 'Form Redesign', 'event_type' => 'community_run', 'status' => 'draft']);
    $this->actingAs($this->staff);
});

function redesignPayload(array $overrides = []): array
{
    return array_merge([
        'fields' => [], 'tshirt_enabled' => true, 'tshirt_label' => 'T-shirt Size', 'tshirt_required' => true,
        'tshirt_choices' => ['XSmall', 'Small', 'Medium', 'Large'],
    ], $overrides);
}

function redesignPhoto(string $name): File
{
    return UploadedFile::fake()->createWithContent($name, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aC1sAAAAASUVORK5CYII='));
}

test('the builder stores formatted disclaimers without executable content', function () {
    $this->put(route('events.registration-form.update', $this->event), [
        'fields' => [], 'disclaimer_html' => '<h1 onclick="alert(1)">Agreement</h1><p><strong>Read</strong> <em>carefully</em><script>alert(1)</script><img src=x onerror=alert(1)></p>',
    ])->assertSessionHasNoErrors();
    expect($this->event->fresh()->registration_disclaimer_html)
        ->toBe('<h1>Agreement</h1><p><strong>Read</strong> <em>carefully</em></p>');
});

test('T-shirt photos and choices are stored independently of five custom fields', function () {
    $fields = array_map(fn ($label) => ['label' => $label, 'field_type' => 'text', 'is_required' => false], ['Nickname', 'Medical notes', 'Dietary needs', 'Club name', 'Preferred wave']);
    $this->post(route('events.registration-form.update', $this->event), redesignPayload([
        '_method' => 'put', 'fields' => $fields, 'tshirt_photos' => [redesignPhoto('shirt.png')],
    ]))->assertSessionHasNoErrors()->assertRedirect(route('events.show', $this->event));

    $event = $this->event->fresh();
    expect($event->registrationFields()->count())->toBe(5)
        ->and($event->registration_tshirt['choices'])->toBe(['XSmall', 'Small', 'Medium', 'Large']);
    Storage::disk('public')->assertExists($event->registration_tshirt['photos'][0]['path']);
});

test('the builder rejects missing default choices and excessive total photo size', function () {
    $this->put(route('events.registration-form.update', $this->event), redesignPayload(['tshirt_choices' => ['Small', 'Medium', 'XSmall']]))
        ->assertSessionHasErrors('tshirt_choices');
    $this->put(route('events.registration-form.update', $this->event), redesignPayload([
        'tshirt_photos' => [
            redesignPhoto('one.png')->size(9000),
            redesignPhoto('two.png')->size(9000),
            redesignPhoto('three.png')->size(9000),
        ],
    ]))->assertSessionHasErrors('tshirt_photos');
    expect($this->event->fresh()->registration_form_saved_at)->toBeNull();
});

test('the builder rejects an individual T-shirt photo larger than 10 MB', function () {
    $this->put(route('events.registration-form.update', $this->event), redesignPayload([
        'tshirt_photos' => [redesignPhoto('large.png')->size(10241)],
    ]))->assertSessionHasErrors('tshirt_photos.0');

    expect($this->event->fresh()->registration_form_saved_at)->toBeNull();
});

test('the builder rejects a photo path belonging to another event', function () {
    $this->put(route('events.registration-form.update', $this->event), redesignPayload(['tshirt_existing_photos' => ['registration-tshirts/999/other.png']]))
        ->assertSessionHasErrors('tshirt_existing_photos');
});
