<?php

use App\Enums\DeliveryStatus;
use App\Enums\LegalTextType;
use App\Models\LegalText;
use App\Models\LegalTextVersion;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Validate;
use Livewire\Component;

new class extends Component {
    public LegalTextType $type;

    #[Validate('required|string|min:20|max:100000')]
    public string $content = '';

    public function mount(LegalTextType $type): void
    {
        $this->type = $type;
        $this->content = $this->legalText->latestVersion->content ?? '';
    }

    #[Computed]
    public function legalText(): LegalText
    {
        return Auth::user()->legalText($this->type);
    }

    #[Computed]
    public function versions(): Collection
    {
        return $this->legalText->versions()
            ->withCount([
                'deliveries',
                'deliveries as delivered_count' => fn ($query) => $query->where('status', DeliveryStatus::Delivered),
                'deliveries as failed_count' => fn ($query) => $query->where('status', DeliveryStatus::Failed),
            ])
            ->latest('version')
            ->get();
    }

    public function save(): void
    {
        $this->validate();

        $version = $this->legalText->createVersion($this->content);

        unset($this->versions);

        Flux::toast(variant: 'success', text: __('Saved as version :version.', ['version' => $version->version]));
    }

    public function publish(int $versionId): void
    {
        $version = LegalTextVersion::findOrFail($versionId);

        $this->authorize('publish', $version);

        if ($version->publish()) {
            Flux::toast(variant: 'success', text: __('Version :version published and queued for delivery.', ['version' => $version->version]));
        }

        unset($this->versions);
    }

    public function render()
    {
        return $this->view()->title($this->type->label());
    }
}; ?>

<div class="flex flex-col gap-6" wire:poll.5s.visible="$refresh">
    <div>
        <flux:breadcrumbs>
            <flux:breadcrumbs.item :href="route('legal-texts.index')" wire:navigate>{{ __('Legal texts') }}</flux:breadcrumbs.item>
            <flux:breadcrumbs.item>{{ $type->label() }}</flux:breadcrumbs.item>
        </flux:breadcrumbs>

        <flux:heading size="xl" level="1" class="mt-2">{{ $type->label() }}</flux:heading>
    </div>

    <div class="grid gap-8 lg:grid-cols-5">
        <form wire:submit="save" class="space-y-4 lg:col-span-3">
            <flux:textarea wire:model="content" :label="__('Content')" rows="18" />

            <flux:button type="submit" variant="primary" icon="document-plus">{{ __('Save as new version') }}</flux:button>
        </form>

        <div class="space-y-4 lg:col-span-2">
            <flux:heading size="lg">{{ __('Versions') }}</flux:heading>

            @forelse ($this->versions as $version)
                <flux:card :key="$version->id" class="space-y-2">
                    <div class="flex items-center justify-between">
                        <flux:heading>v{{ $version->version }}</flux:heading>

                        @if ($version->isPublished())
                            <flux:badge color="green" size="sm">{{ __('Published') }}</flux:badge>
                        @else
                            <flux:button size="sm" variant="primary" icon="paper-airplane" wire:click="publish({{ $version->id }})">
                                {{ __('Publish') }}
                            </flux:button>
                        @endif
                    </div>

                    <flux:text size="sm">
                        {{ __('Created :date', ['date' => $version->created_at?->format('d.m.Y H:i')]) }}
                        @if ($version->isPublished())
                            · {{ __('published :date', ['date' => $version->published_at?->format('d.m.Y H:i')]) }}
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
                <flux:text>{{ __('No versions yet. Save the text to create version 1.') }}</flux:text>
            @endforelse
        </div>
    </div>
</div>
