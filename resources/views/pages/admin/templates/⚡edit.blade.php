<?php

use App\Enums\LegalTextType;
use App\Models\LegalTemplateVersion;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
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
        $this->content = LegalTemplateVersion::latestFor($type)->content ?? '';
    }

    #[Computed]
    public function versions(): Collection
    {
        return LegalTemplateVersion::query()
            ->where('type', $this->type)
            ->withCount('legalTextVersions')
            ->latest('version')
            ->get();
    }

    public function save(): void
    {
        $this->validate();

        $version = LegalTemplateVersion::draft($this->type, $this->content);

        unset($this->versions);

        Flux::toast(variant: 'success', text: __('Saved as draft version :version.', ['version' => $version->version]));
    }

    public function publish(int $versionId): void
    {
        $version = LegalTemplateVersion::findOrFail($versionId);

        abort_unless($version->isLatest(), 403);

        if ($version->publish()) {
            Flux::toast(variant: 'success', text: __('Template version :version published. Merchant texts are being generated.', ['version' => $version->version]));
        }

        unset($this->versions);
    }

    public function render()
    {
        return $this->view()->title(__('Template: :type', ['type' => $this->type->label()]));
    }
}; ?>

<div class="flex flex-col gap-6" wire:poll.10s.visible>
    <div>
        <flux:breadcrumbs>
            <flux:breadcrumbs.item :href="route('admin.templates.index')" wire:navigate>{{ __('Templates') }}</flux:breadcrumbs.item>
            <flux:breadcrumbs.item>{{ $type->label() }}</flux:breadcrumbs.item>
        </flux:breadcrumbs>

        <flux:heading size="xl" level="1" class="mt-2">{{ __('Template: :type', ['type' => $type->label()]) }}</flux:heading>
    </div>

    <div class="grid gap-8 lg:grid-cols-5">
        <form wire:submit="save" class="space-y-4 lg:col-span-3">
            <flux:textarea wire:model="content" :label="__('Template')" rows="18" class="font-mono" />

            <flux:text size="sm">
                {{ __('Placeholders:') }}
                @foreach (\App\Models\MerchantProfile::PLACEHOLDERS as $placeholder)
                    <code class="rounded bg-zinc-100 px-1 dark:bg-zinc-700">{{ \App\Models\MerchantProfile::placeholderTag($placeholder) }}</code>
                @endforeach
            </flux:text>

            <flux:button type="submit" variant="primary" icon="document-plus">{{ __('Save as new draft') }}</flux:button>
        </form>

        <div class="space-y-4 lg:col-span-2">
            <flux:heading size="lg">{{ __('Versions') }}</flux:heading>

            @forelse ($this->versions as $version)
                <flux:card :key="$version->id" class="space-y-2">
                    <div class="flex items-center justify-between">
                        <flux:heading>v{{ $version->version }}</flux:heading>

                        @if ($version->isPublished())
                            <flux:badge color="green" size="sm">{{ __('Published') }}</flux:badge>
                        @elseif ($loop->first)
                            <flux:button
                                size="sm"
                                variant="primary"
                                icon="paper-airplane"
                                wire:click="publish({{ $version->id }})"
                                wire:confirm="{{ __('Publish this template? Texts for all merchants will be regenerated.') }}"
                            >
                                {{ __('Publish') }}
                            </flux:button>
                        @else
                            <flux:badge size="sm">{{ __('Superseded draft') }}</flux:badge>
                        @endif
                    </div>

                    <flux:text size="sm">
                        {{ __('Created :date', ['date' => $version->created_at?->format('d.m.Y H:i')]) }}
                        @if ($version->isPublished())
                            · {{ __('published :date', ['date' => $version->published_at?->format('d.m.Y H:i')]) }}
                        @endif
                    </flux:text>

                    @if ($version->isPublished())
                        <flux:text size="sm">{{ __(':count merchant texts generated', ['count' => $version->legal_text_versions_count]) }}</flux:text>
                    @endif
                </flux:card>
            @empty
                <flux:text>{{ __('No versions yet. Save the template to create version 1.') }}</flux:text>
            @endforelse
        </div>
    </div>
</div>
