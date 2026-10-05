<?php

namespace App\Services\ShopAdapters;

use App\Models\Delivery;
use App\Models\Shop;
use App\Support\PublicEndpoint;
use App\Support\WebhookSignature;
use Illuminate\Support\Facades\Http;

class GenericWebhookAdapter implements ShopAdapter
{
    public function deliver(Shop $shop, Delivery $delivery): void
    {
        $version = $delivery->legalTextVersion;
        $type = $version->legalText->type;

        $body = json_encode([
            'event' => 'legal_text.published',
            'delivery_id' => $delivery->id,
            'type' => $type->value,
            'title' => $type->label(),
            'version' => $version->version,
            'content' => $version->content,
            'published_at' => $version->published_at?->toIso8601String(),
        ], JSON_THROW_ON_ERROR);

        $request = Http::timeout(10)
            ->withoutRedirecting()
            ->withHeaders([
                WebhookSignature::HEADER => WebhookSignature::sign($body, $shop->secret),
                'Idempotency-Key' => "delivery-{$delivery->id}",
            ])
            ->withBody($body);

        if (! $shop->usesMockEndpoint()) {
            $endpoint = PublicEndpoint::resolve($shop->endpoint_url);

            if (! $endpoint->isIpLiteral()) {
                $request->withOptions(['curl' => [CURLOPT_RESOLVE => [$endpoint->curlResolveEntry()]]]);
            }
        }

        $request->post($shop->endpoint_url)->throw();
    }
}
