<?php

declare(strict_types=1);

namespace App\Rules;

use App\Services\Altcha\AltchaService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class ValidAltcha implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) && $value !== null) {
            $fail(__('Please complete the verification challenge.'));

            return;
        }

        if (! app(AltchaService::class)->verify(is_string($value) ? $value : null)) {
            $fail(__('Please complete the verification challenge.'));
        }
    }
}
