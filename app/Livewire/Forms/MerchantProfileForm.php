<?php

namespace App\Livewire\Forms;

use App\Models\MerchantProfile;
use App\Models\User;
use Livewire\Form;

class MerchantProfileForm extends Form
{
    public string $companyName = '';

    public string $representative = '';

    public string $street = '';

    public string $postalCode = '';

    public string $city = '';

    public string $email = '';

    public string $vatId = '';

    public bool $requiresApproval = false;

    public function edit(MerchantProfile $profile): void
    {
        $this->companyName = $profile->company_name;
        $this->representative = $profile->representative;
        $this->street = $profile->street;
        $this->postalCode = $profile->postal_code;
        $this->city = $profile->city;
        $this->email = $profile->email;
        $this->vatId = $profile->vat_id ?? '';
        $this->requiresApproval = $profile->requires_approval;
    }

    public function save(User $user): MerchantProfile
    {
        $this->validate();

        return $user->merchantProfile()->updateOrCreate([], [
            'company_name' => $this->companyName,
            'representative' => $this->representative,
            'street' => $this->street,
            'postal_code' => $this->postalCode,
            'city' => $this->city,
            'email' => $this->email,
            'vat_id' => $this->vatId !== '' ? $this->vatId : null,
            'requires_approval' => $this->requiresApproval,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'companyName' => ['required', 'string', 'max:255'],
            'representative' => ['required', 'string', 'max:255'],
            'street' => ['required', 'string', 'max:255'],
            'postalCode' => ['required', 'string', 'max:20'],
            'city' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'vatId' => ['nullable', 'string', 'max:20'],
            'requiresApproval' => ['boolean'],
        ];
    }
}
