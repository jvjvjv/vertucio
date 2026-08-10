<?php

namespace App\Search\Enums;

enum SponsorshipStatus: string
{
    case Scheduled = 'scheduled';
    case Active = 'active';
    case Expired = 'expired';
}
