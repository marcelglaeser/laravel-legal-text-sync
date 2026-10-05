<?php

namespace App\Events;

use App\Models\LegalTextVersion;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LegalTextVersionPublished
{
    use Dispatchable, SerializesModels;

    public function __construct(public LegalTextVersion $version) {}
}
