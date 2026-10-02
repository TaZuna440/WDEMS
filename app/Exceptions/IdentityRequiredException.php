<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown by Participant::resolveFrom() when neither an email nor a
 * normalizable phone number is provided.
 *
 * The at-least-one rule is enforced upstream by
 * PublicRegistrationRequest::withValidator(). This exception is
 * defense-in-depth — the resolver is the last gate before a
 * participant row would be created with no identity key.
 */
class IdentityRequiredException extends RuntimeException
{
}
