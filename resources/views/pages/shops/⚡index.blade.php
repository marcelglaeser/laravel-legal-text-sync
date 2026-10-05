<?php

use App\Livewire\Forms\ShopForm;
use App\Models\Shop;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Shops')] class extends Component {
    public ShopForm $form;

    #[Computed]
    public function shops(): Collection
    {
        return Auth::user()->shops()->withCount('deliveries')->orderBy('name')->get();
    }

    public function create(): void
    {
        $this->form->create();
        $this->resetValidation();

        Flux::modal('shop-form')->show();
    }

    public function edit(int $shopId): void
    {
        $shop = Shop::findOrFail($shopId);

        $this->authorize('update', $shop);

        $this->form->edit($shop);
        $this->resetValidation();

        Flux::modal('shop-form')->show();
    }

    public function save(): void
    {
        if ($this->form->shop !== null) {
            $this->authorize('update', $this->form->shop);
        }

        $shop = $this->form->save(Auth::user());

        Flux::modal('shop-form')->close();
        Flux::toast(variant: 'success', text: __('Shop :name saved.', ['name' => $shop->name]));
    }

    public function delete(int $shopId): void
    {
        $shop = Shop::findOrFail($shopId);

        $this->authorize('delete', $shop);

        $shop->delete();

        Flux::toast(text: __('Shop :name deleted.', ['name' => $shop->name]));
    }
}; ?>

<div class="flex flex-col gap-6">
    <div class="flex items-end justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">{{ __('Shops') }}</flux:heading>
            <flux:subheading>{{ __('Every published legal text is delivered to all of your shops.') }}</flux:subheading>
        </div>

        <flux:button variant="primary" icon="plus" wire:click="create">{{ __('Add shop') }}</flux:button>
    </div>

    @if ($this->shops->isEmpty())
        <flux:callout icon="building-storefront">
            <flux:callout.heading>{{ __('No shops yet') }}</flux:callout.heading>
            <flux:callout.text>{{ __('Add your first shop to start distributing legal texts.') }}</flux:callout.text>
        </flux:callout>
    @else
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Name') }}</flux:table.column>
                <flux:table.column>{{ __('Type') }}</flux:table.column>
                <flux:table.column>{{ __('Endpoint') }}</flux:table.column>
                <flux:table.column>{{ __('Deliveries') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($this->shops as $shop)
                    <flux:table.row :key="$shop->id">
                        <flux:table.cell class="font-medium">{{ $shop->name }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" inset="top bottom">{{ $shop->type->label() }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell class="max-w-64 truncate">{{ $shop->endpoint_url }}</flux:table.cell>
                        <flux:table.cell>{{ $shop->deliveries_count }}</flux:table.cell>
                        <flux:table.cell align="end">
                            <flux:button size="sm" variant="ghost" icon="pencil-square" wire:click="edit({{ $shop->id }})" :aria-label="__('Edit')" />
                            <flux:button
                                size="sm"
                                variant="ghost"
                                icon="trash"
                                wire:click="delete({{ $shop->id }})"
                                wire:confirm="{{ __('Delete this shop and its delivery log?') }}"
                                :aria-label="__('Delete')"
                            />
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif

    <flux:modal name="shop-form" class="md:w-lg">
        <form wire:submit="save" class="space-y-6">
            <flux:heading size="lg">{{ $form->shop ? __('Edit shop') : __('Add shop') }}</flux:heading>

            <flux:input wire:model="form.name" :label="__('Name')" required />

            <flux:select wire:model="form.type" :label="__('Type')">
                @foreach (\App\Enums\ShopType::cases() as $type)
                    <flux:select.option :value="$type->value">{{ $type->label() }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:checkbox
                wire:model.live="form.useMockEndpoint"
                :label="__('Use the built-in mock shop')"
                :description="__('Deliveries go to a demo endpoint in this app that randomly fails.')"
            />

            @unless ($form->useMockEndpoint)
                <flux:input wire:model="form.endpointUrl" type="url" :label="__('Endpoint URL')" placeholder="https://shop.example/legal-texts" />
            @endunless

            <flux:input
                wire:model="form.secret"
                :label="__('Signing secret')"
                :description="$form->shop ? __('Leave empty to keep the current secret.') : __('Used to sign webhook deliveries with HMAC-SHA256.')"
                copyable
            />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
