<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum Role: string
{
    use HasValues;

    case Admin = 'admin';
    case Contractor = 'contractor';
    case Customer = 'customer';
}
