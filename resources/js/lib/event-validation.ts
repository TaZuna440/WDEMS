/**
 * Client-side validation for the event creation wizard.
 *
 * Mirrors app/Concerns/EventValidationRules.php. The server is still the
 * source of truth — these functions exist so the user sees format errors
 * before submitting, not so we can skip server validation.
 *
 * Each function returns a Record<fieldName, errorMessage>. An empty
 * object means the step is valid.
 */

const EVENT_TYPES = ['community_run', 'fun_run'] as const;
const DISTANCE_UNITS = ['km', 'mi'] as const;
const PARTNER_TYPES = [
    'host',
    'sponsor',
    'food',
    'beverage',
    'medical',
    'organization',
    'media',
    'logistics',
    'other',
] as const;

const MAX_PARTNERS = 20;

/**
 * Minimum event duration in minutes when end_time is provided.
 * Mirrors EventValidationRules::MIN_EVENT_DURATION_MINUTES on the server.
 * Keep both in sync.
 */
const MIN_EVENT_DURATION_MINUTES = 60;

/**
 * Maximum number of years into the future an event may be scheduled.
 * Mirrors EventValidationRules::MAX_EVENT_YEARS_AHEAD on the server.
 * Keep both in sync.
 */
const MAX_EVENT_YEARS_AHEAD = 2;

/**
 * Pattern for accepting user-pasted URLs.
 *
 * Requires:
 *   - http:// or https:// scheme
 *   - a host with at least one dot (rejects "https://fdhgdgfdgf")
 *   - a path, query, or fragment (rejects bare "https://maps.google.com")
 *   - at least one more character after that separator
 *
 * Mirrors EventValidationRules::httpUrlPattern() on the server. Keep
 * both in sync.
 */
const HTTP_URL_PATTERN =
    /^https?:\/\/[a-z0-9-]+(\.[a-z0-9-]+)+[\/?#]\S+$/i;

function asString(value: unknown): string {
    return typeof value === 'string' ? value : String(value ?? '');
}

function asNumber(value: unknown): number | null {
    if (value === null || value === undefined || value === '') return null;
    const num = typeof value === 'string' ? parseFloat(value) : Number(value);
    return isNaN(num) ? null : num;
}

function timeToMinutes(value: string): number {
    const [h, m] = value.split(':').map(Number);
    return h * 60 + m;
}

/**
 * Convert a "YYYY-MM-DD" string to a Date at local midnight. Using the
 * date-only constructor would parse as UTC and shift the day in some
 * timezones.
 */
function isoToLocalMidnight(iso: string): Date {
    const [y, m, d] = iso.split('-').map(Number);
    return new Date(y, m - 1, d);
}

/**
 * Shared quality checks for every free-text "name" field.
 *
 * Catches keyboard mashing. Returns null when the value passes, or an
 * error message when it does not.
 *
 * Used by validateBasics (event_name) and validateExtras (partner
 * names). Mirrors EventValidationRules::humanNameQualityRules() on the
 * server. Keep both in sync.
 *
 * @param value  trimmed, non-empty string to check
 * @param label  human-readable field label used in error messages,
 *               e.g. "Event name" or "Partner name"
 */
function humanNameQualityError(value: string, label: string): string | null {
    if (!/^[A-Za-z0-9]/.test(value)) {
        return `${label} must start with a letter or number.`;
    }
    if (!/[aeiouyAEIOUY]/.test(value)) {
        return `Please enter a valid ${label.toLowerCase()}.`;
    }
    if (!/[bcdfghjklmnpqrstvwxzBCDFGHJKLMNPQRSTVWXZ]/.test(value)) {
        return `Please enter a valid ${label.toLowerCase()}.`;
    }
    if (/(.)\1\1/.test(value)) {
        return `Please enter a valid ${label.toLowerCase()}.`;
    }
    return null;
}

export function validateBasics(
    data: Record<string, unknown>,
): Record<string, string> {
    const errors: Record<string, string> = {};
    const eventType = asString(data.event_type).trim();
    const eventName = asString(data.event_name).trim();
    const description = asString(data.description);

    if (!eventType) {
        errors.event_type = 'Event type is required.';
    } else if (!EVENT_TYPES.includes(eventType as (typeof EVENT_TYPES)[number])) {
        errors.event_type = 'Invalid event type.';
    }

    if (!eventName) {
        errors.event_name = 'Event name is required.';
    } else if (eventName.length < 3) {
        errors.event_name = 'Event name must be at least 3 characters.';
    } else if (eventName.length > 100) {
        errors.event_name = 'Event name must not exceed 100 characters.';
    } else {
        const quality = humanNameQualityError(eventName, 'Event name');
        if (quality) {
            errors.event_name = quality;
        }
    }

    if (description.length > 2000) {
        errors.description = 'Description must not exceed 2000 characters.';
    }

    return errors;
}

export function validateSchedule(
    data: Record<string, unknown>,
): Record<string, string> {
    const errors: Record<string, string> = {};
    const eventDate = asString(data.event_date).trim();
    const startTime = asString(data.start_time).trim();
    const endTime = asString(data.end_time).trim();
    const distanceValue = asNumber(data.distance_value);
    const distanceUnit = asString(data.distance_unit).trim();
    const courseUrl = asString(data.course_url).trim();

    if (!eventDate) {
        errors.event_date = 'Event date is required.';
    } else if (!/^\d{4}-\d{2}-\d{2}$/.test(eventDate)) {
        errors.event_date = 'Event date must be a valid date.';
    } else {
        const selected = isoToLocalMidnight(eventDate);
        const today = new Date();
        today.setHours(0, 0, 0, 0);

        const maxDate = new Date(today);
        maxDate.setFullYear(today.getFullYear() + MAX_EVENT_YEARS_AHEAD);

        if (isNaN(selected.getTime())) {
            errors.event_date = 'Event date must be a valid date.';
        } else if (selected < today) {
            errors.event_date = 'Event date cannot be in the past.';
        } else if (selected > maxDate) {
            errors.event_date =
                'Event date cannot be more than 2 years from today.';
        }
    }

    if (!startTime) {
        errors.start_time = 'Start time is required.';
    } else if (!/^([01]\d|2[0-3]):[0-5]\d$/.test(startTime)) {
        errors.start_time = 'Start time must be a valid time.';
    }

    if (endTime) {
        if (!/^([01]\d|2[0-3]):[0-5]\d$/.test(endTime)) {
            errors.end_time = 'End time must be a valid time.';
        } else if (
            !errors.start_time &&
            /^([01]\d|2[0-3]):[0-5]\d$/.test(startTime)
        ) {
            const startMin = timeToMinutes(startTime);
            const endMin = timeToMinutes(endTime);
            const duration = endMin - startMin;

            if (duration <= 0) {
                errors.end_time = 'End time must be after start time.';
            } else if (duration < MIN_EVENT_DURATION_MINUTES) {
                errors.end_time =
                    'End time must be at least 1 hour after start time.';
            }
        }
    }

    if (distanceValue === null) {
        errors.distance_value = 'Distance is required.';
    } else if (distanceValue < 0.01 || distanceValue > 9999.99) {
        errors.distance_value =
            'Distance must be between 0.01 and 9999.99.';
    }

    if (!distanceUnit) {
        errors.distance_unit = 'Distance unit is required.';
    } else if (
        !DISTANCE_UNITS.includes(distanceUnit as (typeof DISTANCE_UNITS)[number])
    ) {
        errors.distance_unit = 'Distance unit must be KM or Miles.';
    }

    if (courseUrl) {
        if (courseUrl.length > 500) {
            errors.course_url = 'Course link must not exceed 500 characters.';
        } else if (!HTTP_URL_PATTERN.test(courseUrl)) {
            errors.course_url =
                'Course link must point to a specific location, like https://maps.app.goo.gl/abc123.';
        }
    }

    return errors;
}

export function validateVenue(
    data: Record<string, unknown>,
): Record<string, string> {
    const errors: Record<string, string> = {};
    const venue = asString(data.venue).trim();
    const venueAddress = asString(data.venue_address).trim();
    const venueMapUrl = asString(data.venue_map_url).trim();
    const lat = asNumber(data.venue_latitude);
    const lng = asNumber(data.venue_longitude);

    if (!venue) {
        errors.venue = 'Venue name is required.';
    } else if (venue.length < 3) {
        errors.venue = 'Venue name must be at least 3 characters.';
    } else if (venue.length > 255) {
        errors.venue = 'Venue name must not exceed 255 characters.';
    }

    if (!venueAddress) {
        errors.venue_address = 'Full address is required.';
    } else if (venueAddress.length < 5) {
        errors.venue_address = 'Full address must be at least 5 characters.';
    } else if (venueAddress.length > 500) {
        errors.venue_address = 'Full address must not exceed 500 characters.';
    }

    if (venueMapUrl) {
        if (venueMapUrl.length > 500) {
            errors.venue_map_url =
                'Map link must not exceed 500 characters.';
        } else if (!HTTP_URL_PATTERN.test(venueMapUrl)) {
            errors.venue_map_url =
                'Map link must point to a specific location, like https://maps.app.goo.gl/abc123.';
        }
    }

    if (lat !== null) {
        if (lat < -90 || lat > 90) {
            errors.venue_latitude = 'Latitude must be between -90 and 90.';
        }
    }

    if (lng !== null) {
        if (lng < -180 || lng > 180) {
            errors.venue_longitude = 'Longitude must be between -180 and 180.';
        }
    }

    return errors;
}

/**
 * Normalize a partner field for duplicate comparison.
 *
 * Mirrors EventValidationRules::validatePartnerDuplicates() on the
 * server. Both sides must agree on this normalization or the client and
 * server will disagree about what counts as a duplicate. Keep in sync.
 */
function normalizePartnerPart(value: unknown): string {
    return asString(value).trim().toLowerCase();
}

export function validateExtras(
    data: Record<string, unknown>,
): Record<string, string> {
    const errors: Record<string, string> = {};
    const partners = Array.isArray(data.partners) ? data.partners : [];

    if (partners.length > MAX_PARTNERS) {
        errors.partners = `You can add up to ${MAX_PARTNERS} partners.`;
        return errors;
    }

    partners.forEach((partner, index) => {
        const p = (partner ?? {}) as Record<string, unknown>;
        const name = asString(p.name).trim();
        const type = asString(p.type).trim();

        if (!name) {
            errors[`partners.${index}.name`] = 'Partner name is required.';
        } else if (name.length < 3) {
            errors[`partners.${index}.name`] =
                'Partner name must be at least 3 characters.';
        } else if (name.length > 255) {
            errors[`partners.${index}.name`] =
                'Partner name must not exceed 255 characters.';
        } else {
            const quality = humanNameQualityError(name, 'Partner name');
            if (quality) {
                errors[`partners.${index}.name`] = quality;
            }
        }

        if (!type) {
            errors[`partners.${index}.type`] = 'Partner type is required.';
        } else if (
            !PARTNER_TYPES.includes(type as (typeof PARTNER_TYPES)[number])
        ) {
            errors[`partners.${index}.type`] = 'Invalid partner type.';
        }
    });

    // Duplicate detection. A partner is a duplicate iff BOTH the name
    // and the type match a partner that appears earlier in the list,
    // after normalization. Same name with different type is allowed —
    // an entity can play two roles at one event.
    const seen = new Map<string, number>();
    let duplicateCount = 0;

    partners.forEach((partner, index) => {
        const p = (partner ?? {}) as Record<string, unknown>;
        const name = normalizePartnerPart(p.name);
        const type = normalizePartnerPart(p.type);

        // Rows with missing name or type have their own errors above.
        // Skip them here so we do not double-report.
        if (!name || !type) return;

        const key = `${name}|${type}`;
        const firstIndex = seen.get(key);

        if (firstIndex !== undefined) {
            const display = asString(p.name).trim();
            errors[`partners.${index}.name`] =
                `Duplicate of row ${firstIndex + 1} — same name and type.`;
            duplicateCount += 1;
        } else {
            seen.set(key, index);
        }
    });

    if (duplicateCount > 0 && !errors.partners) {
        errors.partners =
            duplicateCount === 1
                ? 'One partner has the same name and type as another row.'
                : `${duplicateCount} partners have the same name and type as other rows.`;
    }

    return errors;
}
