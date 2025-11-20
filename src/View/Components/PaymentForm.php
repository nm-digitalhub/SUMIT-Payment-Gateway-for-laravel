<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\View\Components;

use Illuminate\View\Component;
use OfficeGuy\LaravelSumitGateway\Models\OfficeGuyToken;
use OfficeGuy\LaravelSumitGateway\Services\PaymentService;

/**
 * Payment Form Component
 *
 * Renders the payment form with card input fields and saved payment methods
 */
class PaymentForm extends Component
{
    public bool $showSavedMethods;
    public mixed $savedTokens;
    public string $pciMode;
    public string $cvvMode;
    public string $citizenIdMode;
    public bool $fourDigitsYear;
    public int $maxPayments;
    public bool $supportTokens;
    public bool $isUserLoggedIn;
    public float $orderAmount;

    /**
     * Create a new component instance.
     */
    public function __construct(
        ?float $orderAmount = null,
        mixed $owner = null
    ) {
        $this->pciMode = config('officeguy.pci', 'no');
        $this->cvvMode = config('officeguy.cvv', 'required');
        $this->citizenIdMode = config('officeguy.citizen_id', 'required');
        $this->fourDigitsYear = config('officeguy.four_digits_year', true);
        $this->supportTokens = config('officeguy.support_tokens', false);
        $this->isUserLoggedIn = auth()->check();
        $this->orderAmount = $orderAmount ?? 0;

        // Calculate max payments based on order amount
        $this->maxPayments = $this->orderAmount > 0
            ? PaymentService::getMaximumPayments($this->orderAmount)
            : (int)config('officeguy.max_payments', 1);

        // Get saved tokens if user is logged in and tokens are supported
        $this->showSavedMethods = $this->isUserLoggedIn && $this->supportTokens;

        if ($this->showSavedMethods) {
            $tokenOwner = $owner ?? auth()->user();
            $this->savedTokens = OfficeGuyToken::getForOwner($tokenOwner, 'officeguy');
        } else {
            $this->savedTokens = collect();
        }
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render()
    {
        return view('officeguy::components.payment-form');
    }
}
