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

    #[Computed]
    public function hasProfile(): bool
    {
        return Auth::user()->merchantProfile()->exists();
    }
}; ?>

<div class="flex flex-col gap-6" wire:poll.10s.visible>
    <div>
        <flux:heading size="xl" level="1">{{ __('Legal texts') }}</flux:heading>
        <flux:subheading>{{ __('Generated from the legal templates and your company profile, kept up to date automatically.') }}</flux:subheading>
    </div>

    @unless ($this->hasProfile)
        <flux:callout icon="building-office" variant="warning">
            <flux:callout.heading>{{ __('Complete your company profile') }}</flux:callout.heading>
            <flux:callout.text>
                {{ __('Your legal texts are generated as soon as your company details are filled in.') }}
                <flux:callout.link :href="route('company-profile.edit')" wire:navigate>{{ __('Open company profile') }}</flux:callout.link>
            </flux:callout.text>
        </flux:callout>
    @endunless

    <div class="grid gap-4 md:grid-cols-2">
        @foreach (\App\Enums\LegalTextType::cases() as $type)
            @php($legalText = $this->legalTexts->get($type->value))

            <a href="{{ route('legal-texts.show', $type) }}" wire:navigate>
                <flux:card class="h-full space-y-3 hover:bg-zinc-50 dark:hover:bg-zinc-700/50">
                    <div class="flex items-center justify-between gap-2">
                        <flux:heading size="lg">{{ $type->label() }}</flux:heading>

                        <div class="flex gap-1">
                            @if ($legalText?->latestVersion?->isAwaitingApproval())
                                <flux:badge color="amber" size="sm">{{ __('Approval pending') }}</flux:badge>
                            @endif

                            @if ($legalText?->currentVersion)
                                <flux:badge color="green" size="sm">{{ __('v:version live', ['version' => $legalText->currentVersion->version]) }}</flux:badge>
                            @else
                                <flux:badge size="sm">{{ __('Not live') }}</flux:badge>
                            @endif
                        </div>
                    </div>

                    @if ($legalText?->currentVersion)
                        <flux:text>{{ __('Live since :date.', ['date' => $legalText->currentVersion->published_at?->diffForHumans()]) }}</flux:text>
                    @else
                        <flux:text>{{ __('No version live yet.') }}</flux:text>
                    @endif
                </flux:card>
            </a>
        @endforeach
    </div>
</div>
