<?php

use App\Enums\ChurchEventStatus;

/**
 * Pins the church-event transition matrix.
 *
 * Any change to ChurchEventStatus::allowedTransitions() that opens
 * up an illegal transition (e.g. Cancelled -> Upcoming) will fail
 * one of these tests. Any change that closes a legal one will also
 * fail. The matrix is deliberately narrow: four states, two
 * terminal.
 *
 * See docs/audit-2026-10-19.md ISSUE-014 for the reason Phase A
 * ships this guard.
 */
test('Upcoming transitions to Ongoing and Cancelled', function () {
    expect(ChurchEventStatus::Upcoming->allowedTransitions())
        ->toBe([ChurchEventStatus::Ongoing, ChurchEventStatus::Cancelled]);
});

test('Ongoing transitions to Completed and Cancelled', function () {
    expect(ChurchEventStatus::Ongoing->allowedTransitions())
        ->toBe([ChurchEventStatus::Completed, ChurchEventStatus::Cancelled]);
});

test('Completed has no outgoing transitions', function () {
    expect(ChurchEventStatus::Completed->allowedTransitions())->toBe([]);
});

test('Cancelled has no outgoing transitions', function () {
    expect(ChurchEventStatus::Cancelled->allowedTransitions())->toBe([]);
});

test('canTransitionTo accepts every legal transition', function () {
    expect(ChurchEventStatus::Upcoming->canTransitionTo(ChurchEventStatus::Ongoing))->toBeTrue();
    expect(ChurchEventStatus::Upcoming->canTransitionTo(ChurchEventStatus::Cancelled))->toBeTrue();
    expect(ChurchEventStatus::Ongoing->canTransitionTo(ChurchEventStatus::Completed))->toBeTrue();
    expect(ChurchEventStatus::Ongoing->canTransitionTo(ChurchEventStatus::Cancelled))->toBeTrue();
});

test('canTransitionTo rejects skipping a stage', function () {
    expect(ChurchEventStatus::Upcoming->canTransitionTo(ChurchEventStatus::Completed))->toBeFalse();
});

test('canTransitionTo rejects reversing a terminal state', function () {
    expect(ChurchEventStatus::Completed->canTransitionTo(ChurchEventStatus::Upcoming))->toBeFalse();
    expect(ChurchEventStatus::Completed->canTransitionTo(ChurchEventStatus::Ongoing))->toBeFalse();
    expect(ChurchEventStatus::Cancelled->canTransitionTo(ChurchEventStatus::Upcoming))->toBeFalse();
    expect(ChurchEventStatus::Cancelled->canTransitionTo(ChurchEventStatus::Ongoing))->toBeFalse();
    expect(ChurchEventStatus::Cancelled->canTransitionTo(ChurchEventStatus::Completed))->toBeFalse();
});

test('canTransitionTo rejects same-status transitions', function () {
    foreach (ChurchEventStatus::cases() as $status) {
        expect($status->canTransitionTo($status))->toBeFalse();
    }
});

test('canTransitionTo rejects every illegal pair', function () {
    $legal = [
        'upcoming->ongoing' => true,
        'upcoming->cancelled' => true,
        'ongoing->completed' => true,
        'ongoing->cancelled' => true,
    ];

    foreach (ChurchEventStatus::cases() as $from) {
        foreach (ChurchEventStatus::cases() as $to) {
            $key = "{$from->value}->{$to->value}";
            $expected = $legal[$key] ?? false;

            expect($from->canTransitionTo($to))->toBe(
                $expected,
                "Transition {$key} should be ".($expected ? 'legal' : 'illegal'),
            );
        }
    }
});

test('values() returns the four church statuses in order', function () {
    expect(ChurchEventStatus::values())
        ->toBe(['upcoming', 'ongoing', 'completed', 'cancelled']);
});
