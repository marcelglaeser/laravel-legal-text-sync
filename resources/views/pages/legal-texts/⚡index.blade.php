<?php

use App\Models\LegalText;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Legal texts')] class extends Component {
    #[Computed]
    public function legalTexts(): Collection
    {
        return Auth::user()->legalTexts()
            ->with(['currentVersion', 'latestVersion'])
            ->get()
            ->keyBy(fn (LegalText $legalText) => $legalText->type->value);
    }
}; ?>

<div class="flex flex-col gap-6">
    <div>
        <flux:heading size="xl" level="1">{{ __('Legal texts') }}</flux:heading>
        <flux:subheading>{{ __('Every change creates a new version. Publishing distributes it to all shops.') }}</flux:subheading>
    </div>

    <div class="grid gap-4 md:grid-cols-2">
        @foreach (\App\Enums\LegalTextType::cases() as $type)
            @php($legalText = $this->legalTexts->get($type->value))

            <a href="{{ route('legal-texts.edit', $type) }}" wire:navigate>
                <flux:card class="h-full space-y-3 hover:bg-zinc-50 dark:hover:bg-zinc-700/50">
                    <div class="flex items-center justify-between">
                        <flux:heading size="lg">{{ $type->label() }}</flux:heading>

                        @if ($legalText?->currentVersion)
                            <flux:badge color="green" size="sm">{{ __('Published v:version', ['version' => $legalText->currentVersion->version]) }}</flux:badge>
                        @else
                            <flux:badge size="sm">{{ __('Not published') }}</flux:badge>
                        @endif
                    </div>

                    @if ($legalText?->latestVersion && ! $legalText->latestVersion->isPublished())
                        <flux:text>{{ __('Draft v:version waiting to be published.', ['version' => $legalText->latestVersion->version]) }}</flux:text>
                    @elseif ($legalText?->currentVersion)
                        <flux:text>{{ __('Published :date.', ['date' => $legalText->currentVersion->published_at?->diffForHumans()]) }}</flux:text>
                    @else
                        <flux:text>{{ __('No version yet.') }}</flux:text>
                    @endif
                </flux:card>
            </a>
        @endforeach
    </div>
</div>
