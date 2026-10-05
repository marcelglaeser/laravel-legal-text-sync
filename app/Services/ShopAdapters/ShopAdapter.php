<?php

namespace App\Services\ShopAdapters;

use App\Models\Delivery;
use App\Models\Shop;

interface ShopAdapter
{
    public function deliver(Shop $shop, Delivery $delivery): void;
}
