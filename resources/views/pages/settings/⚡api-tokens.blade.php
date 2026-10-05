<?php

use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

new #[Title('API tokens')] class extends Component {
    #[Validate('required|string|max:255')]
    public string $name = '';

    public ?string $plainTextToken = null;

    #[Computed]
    public function tokens(): Collection
    {
        return Auth::user()->tokens()->latest()->get();
    }

    public function createToken(): void
    {
        $this->validate();

        $this->plainTextToken = Auth::user()->createToken($this->name)->plainTextToken;
        $this->reset('name');

        unset($this->tokens);
    }

    public function revokeToken(int $tokenId): void
    {
        Auth::user()->tokens()->whereKey($tokenId)->delete();

        unset($this->tokens);

        Flux::toast(text: __('Token revoked.'));
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading level="2" class="sr-only">{{ __('API tokens') }}</flux:heading>

    <x-pages::settings.layout :heading="__('API tokens')" :subheading="__('Partners use these tokens to fetch your legal texts via the API.')">
        <form wire:submit="createToken" class="flex items-end gap-2">
            <flux:input wire:model="name" :label="__('Token name')" placeholder="{{ __('e.g. Agency XY') }}" class="flex-1" />
            <flux:button type="submit" variant="primary">{{ __('Create') }}</flux:button>
        </form>

        @if ($plainTextToken)
            <flux:callout icon="key" color="green" class="mt-6">
                <flux:callout.heading>{{ __('Copy your new token now. It will not be shown again.') }}</flux:callout.heading>
                <flux:callout.text>
                    <flux:input :value="$plainTextToken" readonly copyable class="mt-2" />
                </flux:callout.text>
            </flux:callout>
        @endif

        <div class="mt-6 space-y-2">
            @foreach ($this->tokens as $token)
                <div wire:key="token-{{ $token->id }}" class="flex items-center justify-between rounded-lg border border-zinc-200 px-4 py-2 dark:border-zinc-700">
                    <div>
                        <flux:heading>{{ $token->name }}</flux:heading>
                        <flux:text size="sm">
                            {{ $token->last_used_at ? __('Last used :date', ['date' => $token->last_used_at->diffForHumans()]) : __('Never used') }}
                        </flux:text>
                    </div>
                    <flux:button size="sm" variant="ghost" icon="trash" wire:click="revokeToken({{ $token->id }})" wire:confirm="{{ __('Revoke this token?') }}" :aria-label="__('Revoke')" />
                </div>
            @endforeach
        </div>

        <flux:text class="mt-6">
            <flux:link :href="url('docs/api')" target="_blank">{{ __('Open the API documentation') }}</flux:link>
        </flux:text>
    </x-pages::settings.layout>
</section>
