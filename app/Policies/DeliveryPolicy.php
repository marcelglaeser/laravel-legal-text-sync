<?php

namespace App\Policies;

use App\Models\Delivery;
use App\Models\User;

class DeliveryPolicy
{
    public function retry(User $user, Delivery $delivery): bool
    {
        return $user->id === $delivery->shop->user_id;
    }
}
