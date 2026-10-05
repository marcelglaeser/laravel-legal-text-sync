<?php

use App\Enums\DeliveryStatus;
use App\Models\Delivery;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Dashboard')] class extends Component {
    #[Computed]
    public function deliveries(): Collection
    {
        return Auth::user()->deliveries()
            ->with(['shop', 'legalTextVersion.legalText'])
            ->latest('deliveries.updated_at')
            ->limit(25)
            ->get();
    }

    #[Computed]
    public function counts(): array
    {
        return collect(DeliveryStatus::cases())
            ->mapWithKeys(fn (DeliveryStatus $status) => [
                $status->value => Auth::user()->deliveries()->where('deliveries.status', $status)->count(),
            ])
            ->all();
    }

    public function retry(int $deliveryId): void
    {
        $delivery = Delivery::findOrFail($deliveryId);

        $this->authorize('retry', $delivery);

        $delivery->retry();

        Flux::toast(variant: 'success', text: __('Delivery to :shop queued again.', ['shop' => $delivery->shop->name]));
    }
}; ?>

<div class="flex flex-col gap-6" wire:poll.5s>
    <div>
        <flux:heading size="xl" level="1">{{ __('Delivery status') }}</flux:heading>
        <flux:subheading>{{ __('Live view of the latest legal text deliveries to your shops.') }}</flux:subheading>
    </div>

    <div class="grid gap-4 sm:grid-cols-3">
        @foreach (\App\Enums\DeliveryStatus::cases() as $status)
            <flux:card class="flex items-center justify-between">
                <flux:text>{{ ucfirst($status->value) }}</flux:text>
                <flux:badge :color="$status->color()" size="lg">{{ $this->counts[$status->value] }}</flux:badge>
            </flux:card>
        @endforeach
    </div>

    @if ($this->deliveries->isEmpty())
        <flux:callout icon="information-circle">
            <flux:callout.heading>{{ __('No deliveries yet') }}</flux:callout.heading>
            <flux:callout.text>
                {{ __('Add a shop and publish a legal text to see deliveries here.') }}
            </flux:callout.text>
        </flux:callout>
    @else
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Shop') }}</flux:table.column>
                <flux:table.column>{{ __('Legal text') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column>{{ __('Attempts') }}</flux:table.column>
                <flux:table.column>{{ __('Updated') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($this->deliveries as $delivery)
                    <flux:table.row :key="$delivery->id">
                        <flux:table.cell>
                            <div class="font-medium">{{ $delivery->shop->name }}</div>
                            <flux:text size="sm">{{ $delivery->shop->type->label() }}</flux:text>
                        </flux:table.cell>
                        <flux:table.cell>
                            {{ $delivery->legalTextVersion->legalText->type->label() }}
                            <span class="text-zinc-500">v{{ $delivery->legalTextVersion->version }}</span>
                        </flux:table.cell>
                        <flux:table.cell>
                            @if ($delivery->last_error)
                                <flux:tooltip :content="$delivery->last_error">
                                    <flux:badge :color="$delivery->status->color()" size="sm" inset="top bottom">{{ $delivery->status->value }}</flux:badge>
                                </flux:tooltip>
                            @else
                                <flux:badge :color="$delivery->status->color()" size="sm" inset="top bottom">{{ $delivery->status->value }}</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>{{ $delivery->attempts }}</flux:table.cell>
                        <flux:table.cell>{{ $delivery->updated_at?->diffForHumans() }}</flux:table.cell>
                        <flux:table.cell align="end">
                            @if ($delivery->status === \App\Enums\DeliveryStatus::Failed)
                                <flux:button size="sm" icon="arrow-path" wire:click="retry({{ $delivery->id }})">
                                    {{ __('Retry') }}
                                </flux:button>
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif
</div>
