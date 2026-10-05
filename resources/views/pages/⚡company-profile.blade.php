<?php

use App\Livewire\Forms\MerchantProfileForm;
use App\Services\LegalTextGenerator;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Company profile')] class extends Component {
    public MerchantProfileForm $form;

    public function mount(): void
    {
        $profile = Auth::user()->merchantProfile;

        if ($profile !== null) {
            $this->form->edit($profile);
        }
    }

    public function save(LegalTextGenerator $generator): void
    {
        $profile = $this->form->save(Auth::user());

        $generator->generateAll($profile);

        Flux::toast(variant: 'success', text: $profile->requires_approval
            ? __('Profile saved. Changed legal texts are waiting for your approval.')
            : __('Profile saved. Changed legal texts are being delivered to your shops.'));
    }
}; ?>

<div class="flex max-w-2xl flex-col gap-6">
    <div>
        <flux:heading size="xl" level="1">{{ __('Company profile') }}</flux:heading>
        <flux:subheading>{{ __('These details are inserted into your legal texts.') }}</flux:subheading>
    </div>

    <form wire:submit="save" class="space-y-6">
        <flux:input wire:model="form.companyName" :label="__('Company name')" required />
        <flux:input wire:model="form.representative" :label="__('Represented by')" required />
        <flux:input wire:model="form.street" :label="__('Street')" required />

        <div class="grid grid-cols-3 gap-4">
            <flux:input wire:model="form.postalCode" :label="__('Postal code')" required />
            <flux:input wire:model="form.city" :label="__('City')" required class="col-span-2" />
        </div>

        <flux:input wire:model="form.email" type="email" :label="__('Contact email')" required />
        <flux:input wire:model="form.vatId" :label="__('VAT ID')" />

        <flux:separator />

        <flux:switch
            wire:model="form.requiresApproval"
            :label="__('Approve updates before they go live')"
            :description="__('When enabled, new versions wait for your approval. Otherwise updates are delivered to your shops automatically.')"
        />

        <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
    </form>
</div>
