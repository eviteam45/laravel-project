<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum ProjectStatus: string
{
    use HasValues;

    case Draft = 'draft';
    case Submitted = 'submitted';
    case InReview = 'in_review';
    case Approved = 'approved';
    case Installed = 'installed';
    case Closed = 'closed';
    case Rejected = 'rejected';
}
