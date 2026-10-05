<?php

namespace App\Http\Resources;

use App\Models\LegalText;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LegalText
 */
class LegalTextResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'type' => $this->type->value,
            'title' => $this->type->label(),
            'current_version' => LegalTextVersionResource::make($this->whenLoaded('currentVersion')),
        ];
    }
}
