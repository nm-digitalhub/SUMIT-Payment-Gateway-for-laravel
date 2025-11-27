{{-- Mapping Details Modal Content - For View Action --}}

@php
    $mappingService = app(\OfficeGuy\LaravelSumitGateway\Services\PayableMappingService::class);
    $payableFields = $mappingService->getPayableFields();
    $fieldMappings = $mapping->field_mappings ?? [];
    $mappedCount = count(array_filter($fieldMappings));
    $totalCount = count($payableFields);
@endphp

<div class="space-y-6">
    {{-- Header Stats --}}
    <div class="grid grid-cols-4 gap-4">
        <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-4 border border-blue-200 dark:border-blue-800">
            <div class="text-sm text-blue-700 dark:text-blue-300 mb-1">מזהה</div>
            <div class="text-2xl font-bold text-blue-900 dark:text-blue-100">#{{ $mapping->id }}</div>
        </div>

        <div class="bg-green-50 dark:bg-green-900/20 rounded-lg p-4 border border-green-200 dark:border-green-800">
            <div class="text-sm text-green-700 dark:text-green-300 mb-1">ממופים</div>
            <div class="text-2xl font-bold text-green-900 dark:text-green-100">{{ $mappedCount }}</div>
        </div>

        <div class="bg-purple-50 dark:bg-purple-900/20 rounded-lg p-4 border border-purple-200 dark:border-purple-800">
            <div class="text-sm text-purple-700 dark:text-purple-300 mb-1">סה"כ שדות</div>
            <div class="text-2xl font-bold text-purple-900 dark:text-purple-100">{{ $totalCount }}</div>
        </div>

        <div class="bg-{{ $mapping->is_active ? 'green' : 'gray' }}-50 dark:bg-{{ $mapping->is_active ? 'green' : 'gray' }}-900/20 rounded-lg p-4 border border-{{ $mapping->is_active ? 'green' : 'gray' }}-200 dark:border-{{ $mapping->is_active ? 'green' : 'gray' }}-800">
            <div class="text-sm text-{{ $mapping->is_active ? 'green' : 'gray' }}-700 dark:text-{{ $mapping->is_active ? 'green' : 'gray' }}-300 mb-1">סטטוס</div>
            <div class="text-lg font-bold text-{{ $mapping->is_active ? 'green' : 'gray' }}-900 dark:text-{{ $mapping->is_active ? 'green' : 'gray' }}-100">
                {{ $mapping->is_active ? 'פעיל' : 'מושבת' }}
            </div>
        </div>
    </div>

    {{-- Model Info --}}
    <div class="bg-gray-50 dark:bg-gray-900 rounded-lg p-4 border border-gray-200 dark:border-gray-700">
        <div class="flex items-start gap-4">
            <div class="flex-shrink-0">
                <svg class="w-6 h-6 text-gray-600 dark:text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 7.5l-2.25-1.313M21 7.5v2.25m0-2.25l-2.25 1.313M3 7.5l2.25-1.313M3 7.5l2.25 1.313M3 7.5v2.25m9 3l2.25-1.313M12 12.75l-2.25-1.313M12 12.75V15m0 6.75l2.25-1.313M12 21.75V19.5m0 2.25l-2.25-1.313m0-16.875L12 2.25l2.25 1.313M21 14.25v2.25l-2.25 1.313m-13.5 0L3 16.5v-2.25" />
                </svg>
            </div>
            <div class="flex-1">
                <div class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">מחלקת מודל</div>
                <code class="block bg-white dark:bg-gray-800 px-3 py-2 rounded border border-gray-200 dark:border-gray-700 font-mono text-sm">
                    {{ $mapping->model_class }}
                </code>
            </div>
        </div>
    </div>

    {{-- Mappings by Category --}}
    @php
        $categorizedFields = [
            'core' => ['title' => 'מידע ליבה', 'icon' => 'M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm3 0h.008v.008H18V10.5Zm-12 0h.008v.008H6V10.5Z', 'color' => 'blue'],
            'customer' => ['title' => 'פרטי לקוח', 'icon' => 'M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z', 'color' => 'purple'],
            'items' => ['title' => 'פריטים ועלויות', 'icon' => 'M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z', 'color' => 'green'],
            'tax' => ['title' => 'מע"מ', 'icon' => 'M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm3 0h.008v.008H18V10.5Zm-12 0h.008v.008H6V10.5Z', 'color' => 'yellow'],
        ];
    @endphp

    @foreach($categorizedFields as $category => $categoryData)
        @php
            $categoryFields = array_filter($payableFields, fn($f) => $f['category'] === $category);
        @endphp

        @if(count($categoryFields) > 0)
            <div class="border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden">
                <div class="bg-{{ $categoryData['color'] }}-50 dark:bg-{{ $categoryData['color'] }}-900/20 px-4 py-3 border-b border-{{ $categoryData['color'] }}-200 dark:border-{{ $categoryData['color'] }}-800">
                    <div class="flex items-center gap-2">
                        <svg class="w-5 h-5 text-{{ $categoryData['color'] }}-600 dark:text-{{ $categoryData['color'] }}-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $categoryData['icon'] }}" />
                        </svg>
                        <span class="font-semibold text-{{ $categoryData['color'] }}-900 dark:text-{{ $categoryData['color'] }}-100">
                            {{ $categoryData['title'] }}
                        </span>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 divide-y divide-gray-100 dark:divide-gray-700">
                    @foreach($categoryFields as $field)
                        @php
                            $mappedValue = $fieldMappings[$field['key']] ?? null;
                            $isMapped = $mappedValue !== null && $mappedValue !== '';
                        @endphp

                        <div class="px-4 py-3 hover:bg-gray-50 dark:hover:bg-gray-750 transition-colors">
                            <div class="flex items-start justify-between gap-4">
                                {{-- Field Name --}}
                                <div class="flex-1">
                                    <div class="flex items-center gap-2 mb-1">
                                        @if($isMapped)
                                            <svg class="w-4 h-4 text-green-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                            </svg>
                                        @else
                                            <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                            </svg>
                                        @endif
                                        <span class="font-medium text-gray-900 dark:text-gray-100">{{ $field['label_he'] }}</span>
                                        @if($field['required'])
                                            <span class="text-xs bg-red-100 dark:bg-red-900/40 text-red-700 dark:text-red-300 px-2 py-0.5 rounded">חובה</span>
                                        @endif
                                    </div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400 font-mono mb-1">
                                        {{ $field['method'] }} → {{ $field['return_type'] }}
                                    </div>
                                </div>

                                {{-- Mapped Value --}}
                                <div class="text-left">
                                    @if($isMapped)
                                        <code class="inline-block bg-green-50 dark:bg-green-900/20 text-green-700 dark:text-green-300 px-3 py-1 rounded border border-green-200 dark:border-green-800 font-mono text-sm">
                                            {{ $mappedValue }}
                                        </code>
                                    @else
                                        <span class="inline-block text-gray-400 dark:text-gray-600 text-sm italic">
                                            לא ממופה
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    @endforeach

    {{-- Footer Metadata --}}
    <div class="flex items-center justify-between text-xs text-gray-500 dark:text-gray-400 pt-4 border-t border-gray-200 dark:border-gray-700">
        <div class="flex items-center gap-4">
            <div class="flex items-center gap-1">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
                <span>נוצר: {{ $mapping->created_at->format('d/m/Y H:i') }}</span>
            </div>
            <div class="flex items-center gap-1">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                </svg>
                <span>עודכן: {{ $mapping->updated_at->diffForHumans() }}</span>
            </div>
        </div>

        <div class="text-gray-400 font-mono">
            ID: #{{ $mapping->id }}
        </div>
    </div>
</div>
