<?php

namespace App\Enums;

enum ReserveOutcome: string
{
    case Reserved = 'reserved';
    case NotSeeded = 'not_seeded';
    case DuplicateUser = 'duplicate_user';
    case InsufficientStock = 'insufficient_stock';
    case OverLimit = 'over_limit';
}
