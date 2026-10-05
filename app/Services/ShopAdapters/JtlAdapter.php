<?php

namespace App\Services\ShopAdapters;

use App\Models\Delivery;
use App\Models\Shop;
use Illuminate\Support\Facades\Log;

class JtlAdapter implements ShopAdapter
{
    public function deliver(Shop $shop, Delivery $delivery): void
    {
        Log::info('JTL mock: updated legal text via JTL connector.', [
            'shop_id' => $shop->id,
            'delivery_id' => $delivery->id,
        ]);
    }
}
