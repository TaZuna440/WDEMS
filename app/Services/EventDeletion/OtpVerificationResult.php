<?php

namespace App\Services\EventDeletion;

enum OtpVerificationResult: string
{
    case Verified = 'verified';
    case Invalid = 'invalid';
    case Expired = 'expired';
    case Locked = 'locked';
    case NotFound = 'not_found';
}
