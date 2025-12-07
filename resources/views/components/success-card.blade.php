{{-- Success Card Component --}}
<div class="bg-green-50 dark:bg-green-900/20 border-2 border-green-300 dark:border-green-700 rounded-lg p-6">
    <div class="flex items-start gap-4">
        <div class="flex-shrink-0">
            <svg class="w-12 h-12 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
        </div>
        <div class="flex-1">
            <h3 class="text-lg font-semibold text-green-900 dark:text-green-100 mb-2">
                Payment Card Added Successfully!
            </h3>
            <p class="text-sm text-green-800 dark:text-green-200 mb-4">
                The new payment card has been securely saved and is ready to use.
            </p>

            <div class="bg-white dark:bg-gray-800 rounded-lg p-4 space-y-3">
                <div class="flex justify-between items-center">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Card Type:</span>
                    <span class="text-sm text-gray-900 dark:text-gray-100">{{ $token->card_type ?? 'N/A' }}</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Last 4 Digits:</span>
                    <span class="text-sm font-mono text-gray-900 dark:text-gray-100">•••• {{ $token->last_four ?? 'N/A' }}</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Expiry:</span>
                    <span class="text-sm text-gray-900 dark:text-gray-100">{{ $token->exp_month ?? 'N/A' }}/{{ $token->exp_year ?? 'N/A' }}</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Customer:</span>
                    <span class="text-sm text-gray-900 dark:text-gray-100">
                        {{ $owner->name ?? $owner->email ?? "ID: {$owner->id}" }}
                    </span>
                </div>
                @if($setAsDefault)
                    <div class="flex justify-between items-center pt-2 border-t border-gray-200 dark:border-gray-700">
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Default Payment:</span>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100">
                            <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                            </svg>
                            Yes
                        </span>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
