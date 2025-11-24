@php
    $fieldId = 'og-hosted-token-form-' . uniqid();
    $tokenInputId = $fieldId . '-token';
@endphp

<div class="space-y-4" x-data="{ status: null, error: null }">
    {{-- SDKs --}}
    <script src="https://code.jquery.com/jquery-3.6.4.min.js" crossorigin="anonymous"></script>
    <script src="https://app.sumit.co.il/scripts/payments.js"></script>

    <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <div class="fi-section-header flex flex-col gap-2 px-6 py-4">
            <h3 class="text-base font-semibold text-gray-950 dark:text-white">Enter card details</h3>
            <p class="text-sm text-gray-600 dark:text-gray-400">Details stay in the browser; a secure SingleUseToken is sent to the server.</p>
        </div>
        <div class="fi-section-content p-6">
            <form id="{{ $fieldId }}" class="space-y-4">
                <input type="hidden" id="{{ $tokenInputId }}" name="og-token" data-og="token">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Card Number
                        <input type="text" data-og="cardnumber" autocomplete="cc-number"
                               class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800">
                    </label>

                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        CVV
                        <input type="password" data-og="cvv" maxlength="4" autocomplete="cc-csc"
                               class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800">
                    </label>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Expiry Month
                        <select data-og="expirationmonth"
                                class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800">
                            <option value="">Month</option>
                            @for ($i = 1; $i <= 12; $i++)
                                <option value="{{ sprintf('%02d', $i) }}">{{ sprintf('%02d', $i) }}</option>
                            @endfor
                        </select>
                    </label>

                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Expiry Year
                        <select data-og="expirationyear"
                                class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800">
                            <option value="">Year</option>
                            @for ($i = date('Y'); $i <= date('Y') + 15; $i++)
                                <option value="{{ $i }}">{{ $i }}</option>
                            @endfor
                        </select>
                    </label>
                </div>

                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                    Cardholder ID
                    <input type="text" data-og="citizenid"
                           class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800">
                </label>

                <div class="flex items-center gap-3">
                    <button type="submit" class="fi-btn fi-btn-primary">
                        Generate secure token
                    </button>
                    <span class="text-sm" x-text="status"></span>
                </div>
                <p class="text-sm text-red-600" x-text="error"></p>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const form = document.getElementById('{{ $fieldId }}');
            const tokenInput = document.getElementById('{{ $tokenInputId }}');
            const livewireId = @js($livewireId);
            const companyId = @js($companyId);
            const publicKey = @js($publicKey);

            if (window.OfficeGuy?.Payments) {
                try {
                    OfficeGuy.Payments.BindFormSubmit({
                        CompanyID: companyId,
                        APIPublicKey: publicKey,
                    });
                } catch (e) {
                    console.error('SUMIT bind error', e);
                }
            }

            form.addEventListener('submit', (e) => {
                e.preventDefault();

                // small delay to let SDK write og-token
                setTimeout(() => {
                    const token = tokenInput.value;
                    if (! token) {
                        window.dispatchEvent(new CustomEvent('notify', { detail: { type: 'error', message: 'Token was not generated. Please check card details.' } }));
                        return;
                    }

                    const component = window.Livewire.find(livewireId);
                    component?.set('data.og-token', token);

                    // show success message
                    const alpineCtx = form.closest('[x-data]');
                    alpineCtx?.__x?.set('status', 'Token ready. Click Save to store card.');
                }, 120);
            });
        });
    </script>
</div>
