<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\View\Component;
use OfficeGuy\LaravelSumitGateway\Contracts\Payable;
use OfficeGuy\LaravelSumitGateway\Models\OfficeGuyToken;
use OfficeGuy\LaravelSumitGateway\Services\PaymentService;

/**
 * Payment Widget Component (subscription/checkout)
 *
 * Renders the payment form with card fields and optional saved cards.
 * Used by subscription.blade.php with :payable, :saved-cards, :require-tokenization.
 */
class PaymentWidget extends Component
{
    public bool $showSavedMethods;

    public mixed $savedTokens;

    public string $pciMode;

    public string $cvvMode;

    public string $citizenIdMode;

    public bool $fourDigitsYear;

    public bool $singleColumn;

    public int $maxPayments;

    public bool $supportTokens;

    public bool $isUserLoggedIn;

    public float $orderAmount;

    public function __construct(
        public Payable $payable,
        array|Collection $savedCards = [],
        public bool $requireTokenization = false
    ) {
        $this->pciMode = config('officeguy.pci', 'no');
        $this->cvvMode = config('officeguy.cvv', 'required');
        $this->citizenIdMode = config('officeguy.citizen_id', 'required');
        $this->fourDigitsYear = config('officeguy.four_digits_year', true);
        $this->singleColumn = config('officeguy.single_column_layout', true);
        $this->supportTokens = $requireTokenization || (bool) config('officeguy.support_tokens', false);
        $this->isUserLoggedIn = auth()->check();
        $this->orderAmount = $payable->getPayableAmount();

        $this->maxPayments = $this->orderAmount > 0
            ? PaymentService::getMaximumPayments($this->orderAmount)
            : (int) config('officeguy.max_payments', 1);

        $this->showSavedMethods = $this->isUserLoggedIn && $this->supportTokens;

        $cards = collect($savedCards);
        if ($this->showSavedMethods && $cards->isNotEmpty()) {
            $this->savedTokens = $cards;
        } elseif ($this->showSavedMethods && auth()->check()) {
            $this->savedTokens = OfficeGuyToken::getForOwner(auth()->user(), 'officeguy');
        } else {
            $this->savedTokens = collect();
        }
    }

    public function render(): View
    {
        return view('officeguy::components.payment-form');
    }
}
