<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum PaymentStatus: string
{
    use HasValues;

    case Pending = 'pending';
    case Scheduled = 'scheduled';
    case Paid = 'paid';
    case Failed = 'failed';
}
