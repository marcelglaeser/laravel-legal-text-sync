<?php

use App\Enums\DeliveryStatus;
use App\Enums\LegalTextType;
use App\Models\LegalText;
use App\Models\LegalTextVersion;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public LegalTextType $type;

    public function mount(LegalTextType $type): void
    {
        $this->type = $type;
    }

    #[Computed]
    public function legalText(): LegalText
    {
        return Auth::user()->legalText($this->type)->load(['currentVersion', 'latestVersion']);
    }

    #[Computed]
    public function versions(): Collection
    {
        return $this->legalText->versions()
            ->with('legalTemplateVersion')
            ->withCount([
                'deliveries',
                'deliveries as delivered_count' => fn ($query) => $query->where('status', DeliveryStatus::Delivered),
                'deliveries as failed_count' => fn ($query) => $query->where('status', DeliveryStatus::Failed),
            ])
            ->latest('version')
            ->get();
    }

    public function approve(int $versionId): void
    {
        $version = LegalTextVersion::findOrFail($versionId);

        $this->authorize('approve', $version);

        if ($version->publish()) {
            Flux::toast(variant: 'success', text: __('Version :version approved and queued for delivery to your shops.', ['version' => $version->version]));
        }

        unset($this->legalText, $this->versions);
    }

    public function render()
    {
        return $this->view()->title($this->type->label());
    }
}; ?>

<div class="flex flex-col gap-6" wire:poll.5s.visible>
    <div>
        <flux:breadcrumbs>
            <flux:breadcrumbs.item :href="route('legal-texts.index')" wire:navigate>{{ __('Legal texts') }}</flux:breadcrumbs.item>
            <flux:breadcrumbs.item>{{ $type->label() }}</flux:breadcrumbs.item>
        </flux:breadcrumbs>

        <flux:heading size="xl" level="1" class="mt-2">{{ $type->label() }}</flux:heading>
    </div>

    @php($pending = $this->legalText->latestVersion?->isAwaitingApproval() ? $this->legalText->latestVersion : null)

    @if ($pending)
        <flux:callout icon="exclamation-triangle" color="amber">
            <flux:callout.heading>{{ __('Version :version is waiting for your approval', ['version' => $pending->version]) }}</flux:callout.heading>
            <flux:callout.text>
                {{ __('Based on template version :template. Your shops keep the current version until you approve.', ['template' => $pending->legalTemplateVersion->version]) }}
            </flux:callout.text>
            <x-slot name="actions">
                <flux:button variant="primary" icon="check" wire:click="approve({{ $pending->id }})">{{ __('Approve and publish') }}</flux:button>
            </x-slot>
        </flux:callout>
    @endif

    <div class="grid gap-8 lg:grid-cols-5">
        <div class="space-y-6 lg:col-span-3">
            @if ($pending)
                <div class="space-y-2">
                    <flux:heading>{{ __('New version v:version', ['version' => $pending->version]) }}</flux:heading>
                    <flux:card class="text-sm"><div class="whitespace-pre-line">{{ $pending->content }}</div></flux:card>
                </div>
            @endif

            <div class="space-y-2">
                <flux:heading>{{ __('Live version') }}</flux:heading>
                @if ($this->legalText->currentVersion)
                    <flux:card class="text-sm"><div class="whitespace-pre-line">{{ $this->legalText->currentVersion->content }}</div></flux:card>
                @else
                    <flux:text>{{ __('No version live yet.') }}</flux:text>
                @endif
            </div>
        </div>

        <div class="space-y-4 lg:col-span-2">
            <flux:heading size="lg">{{ __('History') }}</flux:heading>

            @forelse ($this->versions as $version)
                <flux:card :key="$version->id" class="space-y-2">
                    <div class="flex items-center justify-between">
                        <flux:heading>v{{ $version->version }}</flux:heading>

                        @if ($version->is($this->legalText->currentVersion))
                            <flux:badge color="green" size="sm">{{ __('Live') }}</flux:badge>
                        @elseif ($version->isPublished())
                            <flux:badge size="sm">{{ __('Replaced') }}</flux:badge>
                        @elseif ($version->is($pending))
                            <flux:badge color="amber" size="sm">{{ __('Approval pending') }}</flux:badge>
                        @else
                            <flux:badge size="sm">{{ __('Skipped') }}</flux:badge>
                        @endif
                    </div>

                    <flux:text size="sm">
                        {{ __('Template v:template · created :date', ['template' => $version->legalTemplateVersion->version, 'date' => $version->created_at?->format('d.m.Y H:i')]) }}
                        @if ($version->isPublished())
                            · {{ __('live :date', ['date' => $version->published_at?->format('d.m.Y H:i')]) }}
                        @endif
                    </flux:text>

                    @if ($version->deliveries_count > 0)
                        <flux:text size="sm">
                            {{ __(':delivered of :total shops delivered', ['delivered' => $version->delivered_count, 'total' => $version->deliveries_count]) }}
                            @if ($version->failed_count > 0)
                                · <a href="{{ route('dashboard') }}" wire:navigate class="text-red-600 underline">{{ __(':count failed', ['count' => $version->failed_count]) }}</a>
                            @endif
                        </flux:text>
                    @endif
                </flux:card>
            @empty
                <flux:text>{{ __('No versions yet.') }}</flux:text>
            @endforelse
        </div>
    </div>
</div>
