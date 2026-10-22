<?php

use App\Models\User;

beforeEach(function () {
    $this->actingAs(User::factory()->create([
        'role' => 'admin',
        'email_two_factor_enabled' => false,
    ]));
});

it('serves every specialist church category placeholder with its correct label', function () {
    foreach ([
        'kids' => 'Kids Ministry',
        'young-adult' => 'Young Adult',
        'worship-prayer' => 'Worship & Prayer',
        'special-occasions' => 'Special Occasions',
        'church-administration' => 'Church Administration',
    ] as $slug => $label) {
        $this->get("/events/church/{$slug}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('events/church/placeholder')
                ->where('category', $slug)
                ->where('categoryLabel', $label));
    }
});

it('serves all Youth submodule placeholders and the live Youth Service route', function () {
    $this->get('/events/church/youth')->assertOk()
        ->assertInertia(fn ($page) => $page->component('events/church/youth/index'));

    foreach ([
        'camp' => 'Youth Camp',
        'huddle' => 'Huddle',
        'campus-ministry' => 'Campus Ministry',
        'timothy-class' => 'Timothy Class',
    ] as $slug => $label) {
        $this->get("/events/church/youth/{$slug}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('events/church/youth/placeholder')
                ->where('sub', $slug)
                ->where('subLabel', $label));
    }

    $this->get('/events/church/services/youth')->assertOk();
    $this->get('/events/church/youth/not-a-module')->assertNotFound();
});
