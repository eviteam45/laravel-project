<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum ApplicationStatus: string
{
    use HasValues;

    case Started = 'started';
    case InProgress = 'in_progress';
    case Submitted = 'submitted';
    case UnderReview = 'under_review';
    case Reserved = 'reserved';
    case Paid = 'paid';
    case Rejected = 'rejected';
    case Withdrawn = 'withdrawn';

    /**
     * Statuses at which an application is locked from further wizard edits.
     *
     * @return array<int, self>
     */
    public static function locked(): array
    {
        return [self::Submitted, self::UnderReview, self::Reserved, self::Paid];
    }
}
