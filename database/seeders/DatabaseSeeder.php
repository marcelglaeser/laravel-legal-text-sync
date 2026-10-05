<?php

namespace Database\Seeders;

use App\Enums\LegalTextType;
use App\Enums\ShopType;
use App\Models\LegalTemplateVersion;
use App\Models\User;
use App\Services\LegalTextGenerator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(LegalTextGenerator $generator): void
    {
        User::create([
            'name' => 'Rechtsabteilung',
            'email' => 'admin@example.com',
            'password' => 'password',
        ])->forceFill(['is_admin' => true])->save();

        $merchant = User::create([
            'name' => 'Max Mustermann',
            'email' => 'demo@example.com',
            'password' => 'password',
        ]);

        $profile = $merchant->merchantProfile()->create([
            'company_name' => 'Musterhändler GmbH',
            'representative' => 'Max Mustermann',
            'street' => 'Musterstraße 1',
            'postal_code' => '12345',
            'city' => 'Musterstadt',
            'email' => 'info@musterhaendler.de',
            'vat_id' => 'DE123456789',
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

        foreach ($this->templates() as $type => $content) {
            LegalTemplateVersion::draft(LegalTextType::from($type), $content)->publish();
        }

        $generator->generateAll($profile);

        $profile->update(['requires_approval' => true]);

        $termsUpdate = LegalTemplateVersion::draft(
            LegalTextType::Terms,
            $this->templates()['terms']."\n\n§ 3 Widerrufsrecht\nVerbrauchern steht ein Widerrufsrecht nach Maßgabe der Widerrufsbelehrung zu.",
        );
        $termsUpdate->publish();

        $generator->generate($profile, $termsUpdate);
    }

    /**
     * @return array<string, string>
     */
    private function templates(): array
    {
        return [
            'imprint' => "Angaben gemäß § 5 DDG\n\n{{ company_name }}\n{{ street }}\n{{ postal_code }} {{ city }}\n\nVertreten durch: {{ representative }}\nE-Mail: {{ email }}\nUSt-IdNr.: {{ vat_id }}",
            'terms' => "Allgemeine Geschäftsbedingungen der {{ company_name }}\n\n§ 1 Geltungsbereich\nFür alle Bestellungen über unsere Online-Shops gelten die nachfolgenden AGB.\n\n§ 2 Vertragsschluss\nDie Darstellung der Produkte stellt kein rechtlich bindendes Angebot dar.",
            'privacy' => "Datenschutzerklärung\n\nVerantwortlich im Sinne der DSGVO ist die {{ company_name }}, {{ street }}, {{ postal_code }} {{ city }}, E-Mail: {{ email }}.",
            'withdrawal' => "Widerrufsbelehrung\n\nSie haben das Recht, binnen vierzehn Tagen ohne Angabe von Gründen diesen Vertrag zu widerrufen. Um Ihr Widerrufsrecht auszuüben, müssen Sie uns ({{ company_name }}, {{ street }}, {{ postal_code }} {{ city }}, {{ email }}) mittels einer eindeutigen Erklärung informieren.",
        ];
    }
}
