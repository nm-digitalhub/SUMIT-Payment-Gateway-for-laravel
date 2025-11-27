{{-- Model Information Display Component --}}

<div class="rounded-xl bg-blue-50 dark:bg-blue-900/20 p-5 space-y-4 border border-blue-200 dark:border-blue-800">
    {{-- Header --}}
    <div class="flex items-center gap-3">
        <div class="flex-shrink-0">
            <svg class="w-6 h-6 text-blue-600 dark:text-blue-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" />
            </svg>
        </div>
        <div>
            <h4 class="font-semibold text-lg text-blue-900 dark:text-blue-100">מידע על המודל</h4>
            <p class="text-sm text-blue-700 dark:text-blue-300">{{ class_basename($modelClass) }}</p>
        </div>
    </div>

    {{-- File Path --}}
    <div class="space-y-1">
        <div class="flex items-center gap-2 text-sm font-medium text-blue-800 dark:text-blue-200">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
            </svg>
            <span>מיקום הקובץ:</span>
        </div>
        <code class="block text-xs bg-blue-100 dark:bg-blue-900/40 text-blue-800 dark:text-blue-200 px-3 py-2 rounded-lg overflow-x-auto font-mono">
            {{ $file }}
        </code>
    </div>

    {{-- Fillable Fields --}}
    @if(count($fillable) > 0)
        <div class="space-y-2">
            <div class="flex items-center gap-2 text-sm font-medium text-blue-800 dark:text-blue-200">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25ZM6.75 12h.008v.008H6.75V12Zm0 3h.008v.008H6.75V15Zm0 3h.008v.008H6.75V18Z" />
                </svg>
                <span>שדות Fillable ({{ count($fillable) }}):</span>
            </div>
            <div class="flex flex-wrap gap-2">
                @foreach($fillable as $field)
                    <span class="inline-flex items-center gap-1 px-3 py-1.5 bg-blue-100 dark:bg-blue-900/40 text-blue-800 dark:text-blue-200 text-xs font-mono rounded-md">
                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                        </svg>
                        {{ $field }}
                    </span>
                @endforeach
            </div>
        </div>
    @else
        <div class="flex items-center gap-2 text-sm text-blue-600 dark:text-blue-300">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
            </svg>
            <span>המודל לא מגדיר שדות fillable</span>
        </div>
    @endif

    {{-- Success Indicator --}}
    <div class="flex items-center gap-2 text-sm font-medium text-green-700 dark:text-green-300 pt-2 border-t border-blue-200 dark:border-blue-700">
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
        </svg>
        <span>המודל תקין ומוכן למיפוי</span>
    </div>
</div>
