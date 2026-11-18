<?php

namespace App\Enums;

enum ConversationStatus: string
{
    case ACTIVE = 'active';
    case CLOSED = 'closed';
}