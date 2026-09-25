<?php

namespace App\Services\TwoFactor;

enum EmailTwoFactorResult: string
{
    case Verified = 'verified';
    case Invalid = 'invalid';
    case Expired = 'expired';
    case Locked = 'locked';
    case NotFound = 'not_found';
    case TooSoon = 'too_soon';
}
