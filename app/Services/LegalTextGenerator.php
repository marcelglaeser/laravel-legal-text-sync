<?php

namespace App\Services;

use App\Enums\LegalTextType;
use App\Models\LegalTemplateVersion;
use App\Models\LegalTextVersion;
use App\Models\MerchantProfile;

class LegalTextGenerator
{
    public function generate(MerchantProfile $profile, LegalTemplateVersion $template): LegalTextVersion
    {
        $version = $profile->user
            ->legalText($template->type)
            ->createVersion($template->render($profile), $template);

        if (! $profile->requires_approval) {
            $version->publish();
        }

        return $version;
    }

    public function generateAll(MerchantProfile $profile): void
    {
        foreach (LegalTextType::cases() as $type) {
            $template = LegalTemplateVersion::current($type);

            if ($template !== null) {
                $this->generate($profile, $template);
            }
        }
    }
}
