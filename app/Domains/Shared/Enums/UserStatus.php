<?php

namespace App\Domains\Shared\Enums;

enum UserStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
}
