<?php

use App\Models\LegalTemplateVersion;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Templates')] class extends Component {
    #[Computed]
    public function templates(): Collection
    {
        return collect(\App\Enums\LegalTextType::cases())->mapWithKeys(fn ($type) => [
            $type->value => [
                'current' => LegalTemplateVersion::current($type),
                'latest' => LegalTemplateVersion::latestFor($type),
            ],
        ]);
    }
}; ?>

<div class="flex flex-col gap-6">
    <div>
        <flux:heading size="xl" level="1">{{ __('Templates') }}</flux:heading>
        <flux:subheading>{{ __('Maintained by the legal department. Publishing a template regenerates the texts of all merchants.') }}</flux:subheading>
    </div>

    <div class="grid gap-4 md:grid-cols-2">
        @foreach (\App\Enums\LegalTextType::cases() as $type)
            @php($template = $this->templates[$type->value])

            <a href="{{ route('admin.templates.edit', $type) }}" wire:navigate>
                <flux:card class="h-full space-y-3 hover:bg-zinc-50 dark:hover:bg-zinc-700/50">
                    <div class="flex items-center justify-between">
                        <flux:heading size="lg">{{ $type->label() }}</flux:heading>

                        @if ($template['current'])
                            <flux:badge color="green" size="sm">{{ __('Published v:version', ['version' => $template['current']->version]) }}</flux:badge>
                        @else
                            <flux:badge size="sm">{{ __('Not published') }}</flux:badge>
                        @endif
                    </div>

                    @if ($template['latest'] && ! $template['latest']->isPublished())
                        <flux:text>{{ __('Draft v:version waiting to be published.', ['version' => $template['latest']->version]) }}</flux:text>
                    @elseif ($template['current'])
                        <flux:text>{{ __('Published :date.', ['date' => $template['current']->published_at?->diffForHumans()]) }}</flux:text>
                    @else
                        <flux:text>{{ __('No version yet.') }}</flux:text>
                    @endif
                </flux:card>
            </a>
        @endforeach
    </div>
</div>
