<?php

namespace App\Enums;

use App\Services\ShopAdapters\GenericWebhookAdapter;
use App\Services\ShopAdapters\JtlAdapter;
use App\Services\ShopAdapters\ShopAdapter;
use App\Services\ShopAdapters\ShopifyAdapter;

enum ShopType: string
{
    case Shopify = 'shopify';
    case Jtl = 'jtl';
    case GenericWebhook = 'generic_webhook';

    public function label(): string
    {
        return match ($this) {
            self::Shopify => 'Shopify',
            self::Jtl => 'JTL-Shop',
            self::GenericWebhook => 'Generic Webhook',
        };
    }

    /**
     * @return class-string<ShopAdapter>
     */
    public function adapter(): string
    {
        return match ($this) {
            self::Shopify => ShopifyAdapter::class,
            self::Jtl => JtlAdapter::class,
            self::GenericWebhook => GenericWebhookAdapter::class,
        };
    }
}
