<?php

namespace App\Rules;

use App\Exceptions\UnsafeEndpointException;
use App\Support\PublicEndpoint;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class PublicUrl implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        try {
            PublicEndpoint::resolve(is_string($value) ? $value : '');
        } catch (UnsafeEndpointException) {
            $fail(__('The URL must point to a publicly reachable address.'));
        }
    }
}
