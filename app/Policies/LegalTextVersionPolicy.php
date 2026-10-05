<?php

namespace App\Policies;

use App\Models\LegalTextVersion;
use App\Models\User;

class LegalTextVersionPolicy
{
    public function approve(User $user, LegalTextVersion $version): bool
    {
        return $user->id === $version->legalText->user_id
            && $version->isAwaitingApproval();
    }
}
