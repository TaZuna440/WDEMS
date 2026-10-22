<?php

namespace App\Enums;

enum ChurchParticipantType: string
{
    case Member = 'Member';
    case FirstTimer = 'First-Timer';
    case Guest = 'Guest';
    case Graduate = 'Graduate';
    case NinongNinang = 'Ninong/Ninang';
}
