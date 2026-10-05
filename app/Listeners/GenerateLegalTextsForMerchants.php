<?php

namespace App\Listeners;

use App\Events\LegalTemplateVersionPublished;
use App\Jobs\GenerateLegalText;
use App\Models\MerchantProfile;
use Illuminate\Contracts\Queue\ShouldQueue;

class GenerateLegalTextsForMerchants implements ShouldQueue
{
    public int $tries = 3;

    public function handle(LegalTemplateVersionPublished $event): void
    {
        MerchantProfile::query()->lazyById()->each(
            fn (MerchantProfile $profile) => GenerateLegalText::dispatch($profile, $event->template),
        );
    }
}
