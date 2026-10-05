<?php

namespace App\Listeners;

use App\Events\LegalTextVersionPublished;
use App\Models\Shop;
use Illuminate\Contracts\Queue\ShouldQueue;

class DistributeLegalTextVersion implements ShouldQueue
{
    public int $tries = 3;

    public function handle(LegalTextVersionPublished $event): void
    {
        $event->version->legalText->user->shops()->each(
            fn (Shop $shop) => $shop->deliver($event->version),
        );
    }
}
