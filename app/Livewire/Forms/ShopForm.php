<?php

namespace App\Livewire\Forms;

use App\Enums\ShopType;
use App\Models\Shop;
use App\Models\User;
use App\Rules\PublicUrl;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Form;

class ShopForm extends Form
{
    public ?Shop $shop = null;

    public string $name = '';

    public string $type = ShopType::GenericWebhook->value;

    public string $endpointUrl = '';

    public bool $useMockEndpoint = false;

    public string $secret = '';

    public function edit(Shop $shop): void
    {
        $this->shop = $shop;
        $this->name = $shop->name;
        $this->type = $shop->type->value;
        $this->endpointUrl = $shop->endpoint_url;
        $this->useMockEndpoint = $shop->usesMockEndpoint();
        $this->secret = '';
    }

    public function create(): void
    {
        $this->reset();
        $this->secret = Str::random(40);
    }

    public function save(User $user): Shop
    {
        $this->validate();

        $shop = $this->shop ?? $user->shops()->make();

        $shop->fill([
            'name' => $this->name,
            'type' => ShopType::from($this->type),
            'endpoint_url' => $this->useMockEndpoint ? '' : $this->endpointUrl,
        ]);

        if ($this->secret !== '') {
            $shop->secret = $this->secret;
        }

        $shop->save();

        if ($this->useMockEndpoint) {
            $shop->update(['endpoint_url' => route('mock-shop', $shop)]);
        }

        $this->reset();

        return $shop;
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(ShopType::class)],
            'useMockEndpoint' => ['boolean'],
            'endpointUrl' => ['exclude_if:useMockEndpoint,true', 'required', 'url:https,http', 'max:2048', new PublicUrl],
            'secret' => [$this->shop === null ? 'required' : 'nullable', 'string', 'min:16', 'max:255'],
        ];
    }
}
