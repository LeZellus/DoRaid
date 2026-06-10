<?php

namespace App\Entity;

enum RaidParticipantStatus: string
{
    case Pending  = 'pending';
    case Accepted = 'accepted';
}
