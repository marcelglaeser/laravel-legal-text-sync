<?php

namespace App\Listeners;

use App\Events\LegalTextVersionPublished;
use App\Jobs\DeliverLegalTextVersion;
use App\Models\Shop;
use Illuminate\Contracts\Queue\ShouldQueue;

class DistributeLegalTextVersion implements ShouldQueue
{
    public int $tries = 3;

    public function handle(LegalTextVersionPublished $event): void
    {
        $version = $event->version;

        $version->legalText->user->shops()->each(function (Shop $shop) use ($version) {
            $delivery = $shop->deliveries()->createOrFirst([
                'legal_text_version_id' => $version->id,
            ]);

            if ($delivery->wasRecentlyCreated) {
                DeliverLegalTextVersion::dispatch($delivery);
            }
        });
    }
}
