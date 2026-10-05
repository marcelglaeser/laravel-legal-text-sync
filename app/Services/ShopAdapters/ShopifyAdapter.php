<?php

namespace App\Services\ShopAdapters;

use App\Models\Delivery;
use App\Models\Shop;
use Illuminate\Support\Facades\Log;

class ShopifyAdapter implements ShopAdapter
{
    public function deliver(Shop $shop, Delivery $delivery): void
    {
        Log::info('Shopify mock: updated shop policy page.', [
            'shop_id' => $shop->id,
            'delivery_id' => $delivery->id,
        ]);
    }
}
