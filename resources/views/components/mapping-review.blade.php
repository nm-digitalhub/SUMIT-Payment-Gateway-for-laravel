{{-- Mapping Review Component - Step 3 Summary --}}

<div class="space-y-6">
    {{-- Header Card --}}
    <div class="rounded-xl bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-blue-900/20 dark:to-indigo-900/20 p-6 border border-blue-200 dark:border-blue-800">
        <div class="flex items-start gap-4">
            <div class="flex-shrink-0">
                <div class="w-12 h-12 rounded-lg bg-blue-600 dark:bg-blue-500 flex items-center justify-center">
                    <svg class="w-7 h-7 text-white" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                </div>
            </div>
            <div class="flex-1">
                <h3 class="text-xl font-bold text-blue-900 dark:text-blue-100 mb-1">
                    {{ $label ?? class_basename($modelClass) }}
                </h3>
                <p class="text-sm font-mono text-blue-700 dark:text-blue-300 bg-blue-100 dark:bg-blue-900/40 px-3 py-1 rounded-md inline-block">
                    {{ $modelClass }}
                </p>
            </div>
        </div>
    </div>

    {{-- Stats Summary --}}
    <div class="grid grid-cols-3 gap-4">
        <div class="bg-green-50 dark:bg-green-900/20 rounded-lg p-4 border border-green-200 dark:border-green-800">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-green-100 dark:bg-green-900/40 flex items-center justify-center">
                    <svg class="w-6 h-6 text-green-600 dark:text-green-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                    </svg>
                </div>
                <div>
                    <div class="text-2xl font-bold text-green-900 dark:text-green-100">
                        {{ count(array_filter($mappings)) }}
                    </div>
                    <div class="text-xs text-green-700 dark:text-green-300">שדות ממופים</div>
                </div>
            </div>
        </div>

        <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4 border border-gray-200 dark:border-gray-700">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-gray-100 dark:bg-gray-700 flex items-center justify-center">
                    <svg class="w-6 h-6 text-gray-600 dark:text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 9.776c.112-.017.227-.026.344-.026h15.812c.117 0 .232.009.344.026m-16.5 0a2.25 2.25 0 0 0-1.883 2.542l.857 6a2.25 2.25 0 0 0 2.227 1.932H19.05a2.25 2.25 0 0 0 2.227-1.932l.857-6a2.25 2.25 0 0 0-1.883-2.542m-16.5 0V6A2.25 2.25 0 0 1 6 3.75h3.879a1.5 1.5 0 0 1 1.06.44l2.122 2.12a1.5 1.5 0 0 0 1.06.44H18A2.25 2.25 0 0 1 20.25 9v.776" />
                    </svg>
                </div>
                <div>
                    <div class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                        {{ count($mappings) - count(array_filter($mappings)) }}
                    </div>
                    <div class="text-xs text-gray-700 dark:text-gray-300">שדות ריקים</div>
                </div>
            </div>
        </div>

        <div class="bg-indigo-50 dark:bg-indigo-900/20 rounded-lg p-4 border border-indigo-200 dark:border-indigo-800">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-indigo-100 dark:bg-indigo-900/40 flex items-center justify-center">
                    <svg class="w-6 h-6 text-indigo-600 dark:text-indigo-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21 3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" />
                    </svg>
                </div>
                <div>
                    <div class="text-2xl font-bold text-indigo-900 dark:text-indigo-100">16</div>
                    <div class="text-xs text-indigo-700 dark:text-indigo-300">סה"כ שדות Payable</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Mappings Grid --}}
    <div class="space-y-2">
        <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 0 1 0 3.75H5.625a1.875 1.875 0 0 1 0-3.75Z" />
            </svg>
            <span>סיכום המיפויים</span>
        </h4>

        <div class="grid grid-cols-1 gap-2 max-h-96 overflow-y-auto pr-2">
            @foreach($mappings as $payableField => $modelField)
                @if($modelField !== null && $modelField !== '')
                    <div class="flex items-center gap-3 p-3 rounded-lg bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 hover:border-blue-300 dark:hover:border-blue-600 transition-colors">
                        {{-- Payable Field --}}
                        <div class="flex items-center gap-2 flex-1">
                            <div class="w-2 h-2 rounded-full bg-blue-500"></div>
                            <span class="font-mono text-sm text-gray-700 dark:text-gray-300">
                                {{ $payableField }}
                            </span>
                        </div>

                        {{-- Arrow --}}
                        <div class="flex-shrink-0">
                            <svg class="w-5 h-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                            </svg>
                        </div>

                        {{-- Model Field --}}
                        <div class="flex-1 text-left">
                            <span class="font-mono text-sm font-semibold text-blue-600 dark:text-blue-400">
                                {{ $modelField }}
                            </span>
                        </div>
                    </div>
                @endif
            @endforeach

            {{-- Empty Mappings (Collapsed) --}}
            @php
                $emptyMappings = array_filter($mappings, fn($value) => $value === null || $value === '');
            @endphp

            @if(count($emptyMappings) > 0)
                <details class="mt-3">
                    <summary class="cursor-pointer p-3 rounded-lg bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 text-sm font-medium text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors">
                        <div class="flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                            </svg>
                            <span>שדות לא ממופים ({{ count($emptyMappings) }})</span>
                        </div>
                    </summary>
                    <div class="mt-2 space-y-1 pr-4">
                        @foreach($emptyMappings as $field => $value)
                            <div class="text-xs text-gray-500 dark:text-gray-400 flex items-center gap-2">
                                <div class="w-1.5 h-1.5 rounded-full bg-gray-400"></div>
                                <span class="font-mono">{{ $field }}</span>
                            </div>
                        @endforeach
                    </div>
                </details>
            @endif
        </div>
    </div>

    {{-- Success Footer --}}
    <div class="rounded-xl bg-gradient-to-r from-green-50 to-emerald-50 dark:from-green-900/20 dark:to-emerald-900/20 p-5 border border-green-200 dark:border-green-800">
        <div class="flex items-start gap-4">
            <div class="flex-shrink-0">
                <svg class="w-6 h-6 text-green-600 dark:text-green-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12c0 1.268-.63 2.39-1.593 3.068a3.745 3.745 0 0 1-1.043 3.296 3.745 3.745 0 0 1-3.296 1.043A3.745 3.745 0 0 1 12 21c-1.268 0-2.39-.63-3.068-1.593a3.746 3.746 0 0 1-3.296-1.043 3.745 3.745 0 0 1-1.043-3.296A3.745 3.745 0 0 1 3 12c0-1.268.63-2.39 1.593-3.068a3.745 3.745 0 0 1 1.043-3.296 3.746 3.746 0 0 1 3.296-1.043A3.746 3.746 0 0 1 12 3c1.268 0 2.39.63 3.068 1.593a3.746 3.746 0 0 1 3.296 1.043 3.746 3.746 0 0 1 1.043 3.296A3.745 3.745 0 0 1 21 12Z" />
                </svg>
            </div>
            <div class="flex-1">
                <p class="font-semibold text-green-900 dark:text-green-100 mb-1">
                    המיפוי מוכן לשמירה
                </p>
                <p class="text-sm text-green-700 dark:text-green-300">
                    לחץ על "שמור מיפוי" כדי ליצור את המיפוי. מעכשיו כל אובייקט של
                    <code class="bg-green-100 dark:bg-green-900/40 px-2 py-0.5 rounded text-green-800 dark:text-green-200 font-mono text-xs">{{ class_basename($modelClass) }}</code>
                    יוכל לשמש כ-Payable באמצעות
                    <code class="bg-green-100 dark:bg-green-900/40 px-2 py-0.5 rounded text-green-800 dark:text-green-200 font-mono text-xs">DynamicPayableWrapper</code>.
                </p>
            </div>
        </div>
    </div>
</div>
