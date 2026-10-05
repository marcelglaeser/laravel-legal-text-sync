<?php

namespace Database\Seeders;

use App\Enums\LegalTextType;
use App\Enums\ShopType;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $merchant = User::factory()->create([
            'name' => 'Musterhändler GmbH',
            'email' => 'demo@example.com',
        ]);

        $merchant->shops()->createMany([
            ['name' => 'Shopify Store', 'type' => ShopType::Shopify, 'endpoint_url' => 'https://musterhaendler.myshopify.com', 'secret' => Str::random(40)],
            ['name' => 'JTL-Shop', 'type' => ShopType::Jtl, 'endpoint_url' => 'https://shop.musterhaendler.de', 'secret' => Str::random(40)],
        ]);

        $mockShop = $merchant->shops()->create([
            'name' => 'Eigener Shop (Mock)',
            'type' => ShopType::GenericWebhook,
            'endpoint_url' => '',
            'secret' => Str::random(40),
        ]);
        $mockShop->update(['endpoint_url' => route('mock-shop', $mockShop)]);

        $imprint = $merchant->legalText(LegalTextType::Imprint);
        $imprint->createVersion("Musterhändler GmbH\nMusterstraße 1\n12345 Musterstadt\n\nVertreten durch: Max Mustermann\nHandelsregister: HRB 12345, Amtsgericht Musterstadt")->publish();

        $terms = $merchant->legalText(LegalTextType::Terms);
        $terms->createVersion("§ 1 Geltungsbereich\nFür alle Bestellungen über unseren Online-Shop gelten die nachfolgenden AGB.")->publish();
        $terms->createVersion("§ 1 Geltungsbereich\nFür alle Bestellungen über unsere Online-Shops gelten die nachfolgenden AGB.\n\n§ 2 Vertragsschluss\nDie Darstellung der Produkte stellt kein rechtlich bindendes Angebot dar.");
    }
}
