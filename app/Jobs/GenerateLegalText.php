<?php

namespace App\Jobs;

use App\Models\LegalTemplateVersion;
use App\Models\MerchantProfile;
use App\Services\LegalTextGenerator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GenerateLegalText implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public bool $deleteWhenMissingModels = true;

    public function __construct(
        public MerchantProfile $profile,
        public LegalTemplateVersion $template,
    ) {}

    public function handle(LegalTextGenerator $generator): void
    {
        if (! $this->template->is(LegalTemplateVersion::current($this->template->type))) {
            return;
        }

        $generator->generate($this->profile, $this->template);
    }
}
