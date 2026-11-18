<?php

namespace App\Enums;

enum MessageRole: string
{
    case BOT = 'bot';
    case CLIENT = 'client';
    case ERROR = 'error';
}