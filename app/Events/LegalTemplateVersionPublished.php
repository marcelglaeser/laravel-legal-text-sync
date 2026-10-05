<?php

namespace App\Events;

use App\Models\LegalTemplateVersion;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LegalTemplateVersionPublished
{
    use Dispatchable, SerializesModels;

    public function __construct(public LegalTemplateVersion $template) {}
}
