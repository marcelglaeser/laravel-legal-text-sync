<?php

namespace App\Http\Resources;

use App\Models\LegalTextVersion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LegalTextVersion
 */
class LegalTextVersionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'version' => $this->version,
            'content' => $this->content,
            'published_at' => $this->published_at?->toIso8601String(),
        ];
    }
}
