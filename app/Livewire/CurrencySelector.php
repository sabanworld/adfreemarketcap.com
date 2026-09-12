<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Services\Currency\CurrencyService;
use App\Services\Currency\DTOs\CurrencyUnit;
use App\Support\FormRateLimiter;
use Livewire\Component;

class CurrencySelector extends Component
{
    public string $currency = 'usd';

    public function mount(CurrencyService $currency): void
    {
        $this->currency = $currency->activeCode();
    }

    public function updatedCurrency(CurrencyService $currency): void
    {
        FormRateLimiter::ensureIsNotRateLimited('currency', errorKey: 'currency');
        FormRateLimiter::hit('currency');

        $this->currency = $currency->remember($this->currency)->code;

        // Prices render in the page component, the market ticker, and layout
        // chrome alike, so reload the document instead of this control alone.
        $this->js('window.location.reload()');
    }

    public function render()
    {
        $currency = app(CurrencyService::class);
        $units = $currency->units();

        return view('livewire.currency-selector', [
            'fiatUnits' => $units->reject(fn (CurrencyUnit $unit): bool => $unit->isCrypto),
            'cryptoUnits' => $units->filter(fn (CurrencyUnit $unit): bool => $unit->isCrypto),
            'active' => $currency->active(),
            'ratesSyncedAt' => $currency->ratesSyncedAt(),
        ]);
    }
}
