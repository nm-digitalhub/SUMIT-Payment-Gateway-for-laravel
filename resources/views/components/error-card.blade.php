{{-- Error Card Component --}}
<div class="bg-red-50 dark:bg-red-900/20 border-2 border-red-300 dark:border-red-700 rounded-lg p-6">
    <div class="flex items-start gap-4">
        <div class="flex-shrink-0">
            <svg class="w-12 h-12 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
            </svg>
        </div>
        <div class="flex-1">
            <h3 class="text-lg font-semibold text-red-900 dark:text-red-100 mb-2">
                Payment Card Addition Failed
            </h3>
            <p class="text-sm text-red-800 dark:text-red-200 mb-4">
                We encountered an issue while processing your payment card. Please review the error below and try again.
            </p>

            <div class="bg-white dark:bg-gray-800 rounded-lg p-4">
                <div class="flex items-start gap-3">
                    <svg class="w-5 h-5 text-red-600 dark:text-red-400 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                    </svg>
                    <div class="flex-1">
                        <p class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-1">Error Message:</p>
                        <p class="text-sm text-gray-700 dark:text-gray-300">{{ $message }}</p>
                    </div>
                </div>

                @if(isset($errorType))
                    <div class="mt-3 pt-3 border-t border-gray-200 dark:border-gray-700">
                        <p class="text-xs text-gray-600 dark:text-gray-400">
                            <span class="font-medium">Error Type:</span>
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ml-2
                                @if($errorType === 'validation') bg-yellow-100 text-yellow-800 dark:bg-yellow-800 dark:text-yellow-100
                                @elseif($errorType === 'gateway') bg-red-100 text-red-800 dark:bg-red-800 dark:text-red-100
                                @else bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-100
                                @endif">
                                {{ ucfirst($errorType) }}
                            </span>
                        </p>
                    </div>
                @endif
            </div>

            <div class="mt-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-3">
                <div class="flex items-start gap-2">
                    <svg class="w-5 h-5 text-blue-600 dark:text-blue-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <div class="flex-1">
                        <p class="text-xs font-medium text-blue-900 dark:text-blue-100 mb-1">Troubleshooting Tips:</p>
                        <ul class="text-xs text-blue-800 dark:text-blue-200 space-y-1 list-disc list-inside">
                            @if($errorType === 'validation')
                                <li>Double-check all card details are entered correctly</li>
                                <li>Ensure the card number, expiry date, and CVV are valid</li>
                                <li>Verify that the ID number matches the cardholder</li>
                            @elseif($errorType === 'gateway')
                                <li>The card may have been declined by your bank</li>
                                <li>Check if the card has sufficient funds or credit limit</li>
                                <li>Contact your bank if the issue persists</li>
                            @else
                                <li>Try refreshing the page and submitting again</li>
                                <li>If the problem continues, contact support</li>
                            @endif
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
