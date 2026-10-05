<?php

namespace App\Policies;

use App\Models\LegalTextVersion;
use App\Models\User;

class LegalTextVersionPolicy
{
    public function publish(User $user, LegalTextVersion $version): bool
    {
        return $user->id === $version->legalText->user_id;
    }
}
