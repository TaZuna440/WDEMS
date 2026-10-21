import test from 'node:test';
import assert from 'node:assert/strict';
import { formatBirthdateInput, birthdateInputError, birthdateToIso, phMobileDigits } from '../../resources/js/lib/participant-input.ts';

test('birthdate progresses through month, day and year in one input', () => {
    assert.equal(formatBirthdateInput('03'), '03');
    assert.equal(formatBirthdateInput('0323'), '03/23');
    assert.equal(formatBirthdateInput('03232006'), '03/23/2006');
    assert.equal(birthdateToIso('03/23/2006'), '2006-03-23');
    assert.equal(birthdateToIso('03/23/20'), '');
});

test('invalid month, calendar days and leap days cannot become a submitted date', () => {
    for (const value of ['13/23/2006', '00/23/2006', '04/31/2006', '02/29/2006', '03/00/2006']) {
        assert.ok(birthdateInputError(value));
        assert.equal(birthdateToIso(value), '');
    }
    assert.equal(birthdateToIso('02/29/2004'), '2004-02-29');
});

test('local, international and national mobile input share the fixed +63 prefix', () => {
    for (const value of ['09295501812', '+63 929 550 1812', '639295501812', '9295501812']) {
        assert.equal('+63' + phMobileDigits(value), '+639295501812');
    }
    assert.equal(phMobileDigits(''), '');
});
