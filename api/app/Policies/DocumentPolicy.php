<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class DocumentPolicy
{
    public function view(User $user, Document $document): bool
    {
        $owner = $document->documentable;

        return $owner !== null && Gate::forUser($user)->allows('view', $owner);
    }
}
