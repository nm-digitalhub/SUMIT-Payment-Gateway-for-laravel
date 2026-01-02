@props([
    'node',
    'nodeKey',
    'level' => 0,
    'highlight' => [],
    'showCopyPath' => true,
    'enableLinks' => true,
    'parentPath' => '',
])

@php
    use Illuminate\Support\Facades\Route;

    $isArray = is_array($node) && array_is_list($node);
    $isObject = is_array($node) && !$isArray;
    $isScalar = !is_array($node);

    $isHighlighted = collect($highlight)->contains(fn ($h) =>
        str_contains(strtolower((string)$nodeKey), strtolower($h))
    );

    // Build current path
    $currentPath = $parentPath ? $parentPath . '.' . $nodeKey : $nodeKey;

    // Detect type for icons
    $keyLower = strtolower((string)$nodeKey);
    $valueType = null;
    $linkUrl = null;

    // Helper function to build route URL if exists
    $buildRouteIfExists = function(string $routeName, array $params) {
        return Route::has($routeName) ? route($routeName, $params) : null;
    };

    if ($isScalar) {
        if (is_bool($node)) $valueType = 'boolean';
        elseif (is_numeric($node)) $valueType = 'number';
        elseif (is_string($node)) $valueType = 'string';
        elseif (is_null($node)) $valueType = 'null';
    }

    // Smart icon detection
    $icon = match(true) {
        str_contains($keyLower, 'payment') || str_contains($keyLower, 'amount') || str_contains($keyLower, 'price') => 'heroicon-o-credit-card',
        str_contains($keyLower, 'customer') || str_contains($keyLower, 'user') || str_contains($keyLower, 'client') => 'heroicon-o-user',
        str_contains($keyLower, 'error') || str_contains($keyLower, 'exception') => 'heroicon-o-exclamation-triangle',
        str_contains($keyLower, 'status') => 'heroicon-o-signal',
        str_contains($keyLower, 'email') || str_contains($keyLower, 'mail') => 'heroicon-o-envelope',
        str_contains($keyLower, 'phone') || str_contains($keyLower, 'mobile') => 'heroicon-o-phone',
        str_contains($keyLower, 'date') || str_contains($keyLower, 'time') || str_contains($keyLower, 'created') || str_contains($keyLower, 'updated') => 'heroicon-o-clock',
        str_contains($keyLower, 'document') || str_contains($keyLower, 'invoice') || str_contains($keyLower, 'receipt') => 'heroicon-o-document-text',
        str_contains($keyLower, 'transaction') || str_contains($keyLower, 'txn') => 'heroicon-o-arrow-path',
        str_contains($keyLower, 'order') => 'heroicon-o-shopping-cart',
        str_contains($keyLower, 'token') || str_contains($keyLower, 'key') || str_contains($keyLower, 'secret') => 'heroicon-o-key',
        str_contains($keyLower, 'url') || str_contains($keyLower, 'link') => 'heroicon-o-link',
        str_contains($keyLower, 'success') => 'heroicon-o-check-circle',
        $isArray => 'heroicon-o-queue-list',
        $isObject => 'heroicon-o-cube',
        $valueType === 'boolean' => 'heroicon-o-check-badge',
        $valueType === 'number' => 'heroicon-o-hashtag',
        default => null,
    };

    // Smart link detection for IDs (only creates links to routes that actually exist)
    if ($enableLinks && $isScalar && is_numeric($node)) {
        $linkUrl = match(true) {
            // SUMIT Gateway Cluster resources (corrected route names)
            str_contains($keyLower, 'transaction') && str_contains($keyLower, 'id') =>
                $buildRouteIfExists('filament.admin.sumit-gateway.resources.transactions.view', ['record' => $node]),
            str_contains($keyLower, 'document') && str_contains($keyLower, 'id') =>
                $buildRouteIfExists('filament.admin.sumit-gateway.resources.documents.view', ['record' => $node]),
            str_contains($keyLower, 'subscription') && str_contains($keyLower, 'id') =>
                $buildRouteIfExists('filament.admin.sumit-gateway.resources.subscriptions.view', ['record' => $node]),
            str_contains($keyLower, 'token') && str_contains($keyLower, 'id') =>
                $buildRouteIfExists('filament.admin.sumit-gateway.resources.tokens.view', ['record' => $node]),

            // Application main resources (without cluster)
            str_contains($keyLower, 'customer') && str_contains($keyLower, 'id') =>
                Route::has('filament.admin.resources.clients.index')
                    ? route('filament.admin.resources.clients.index') . '?tableFilters[sumit_customer_id][value]=' . $node
                    : null,
            str_contains($keyLower, 'order') && str_contains($keyLower, 'id') =>
                $buildRouteIfExists('filament.admin.resources.orders.view', ['record' => $node]),

            default => null,
        };
    }
@endphp

<div
    x-data="{
        path: '{{ $currentPath }}',
        get isOpen() {
            return $store.apiPayloadTree.isOpen(this.path);
        },
        toggle() {
            $store.apiPayloadTree.toggle(this.path);
        }
    }"
    x-init="if (!$store.apiPayloadTree.openMap.hasOwnProperty(path)) { $store.apiPayloadTree.setOpen(path, {{ $level < 1 ? 'true' : 'false' }}); }"
    style="padding-left: {{ min($level * 16, 48) }}px"
>
    <div
        class="flex items-center gap-2 cursor-pointer select-none hover:bg-gray-100 dark:hover:bg-gray-700 rounded px-1 py-0.5 group"
        @click="toggle()"
    >
        {{-- Expand/Collapse arrow --}}
        @if($isArray || $isObject)
            <span class="text-gray-500 dark:text-gray-400 flex-shrink-0">
                <x-heroicon-o-chevron-right x-show="!isOpen" class="w-3 h-3" />
                <x-heroicon-o-chevron-down x-show="isOpen" class="w-3 h-3" />
            </span>
        @else
            <span class="w-3 h-3 flex-shrink-0"></span>
        @endif

        {{-- Smart icon --}}
        @if($icon)
            <span class="{{ $isHighlighted ? 'text-primary-600 dark:text-primary-400' : 'text-gray-400 dark:text-gray-500' }} flex-shrink-0">
                @svg($icon, 'w-4 h-4')
            </span>
        @endif

        {{-- Key name --}}
        <span class="font-semibold {{ $isHighlighted ? 'text-primary-600 dark:text-primary-400' : 'text-gray-700 dark:text-gray-300' }}">
            @if($isArray)
                {{ $nodeKey }} <span class="text-xs text-gray-500 dark:text-gray-400">[Array]</span>
            @else
                {{ $nodeKey }}
            @endif
        </span>

        {{-- Value or count --}}
        @if(!$isArray && !$isObject)
            <span class="text-gray-700 dark:text-gray-300 break-all">:</span>
            @if($linkUrl)
                <a
                    href="{{ $linkUrl }}"
                    target="_blank"
                    class="text-primary-600 dark:text-primary-400 hover:underline flex items-center gap-1"
                    @click.stop
                >
                    {{ is_bool($node) ? ($node ? 'true' : 'false') : $node }}
                    <x-heroicon-o-arrow-top-right-on-square class="w-3 h-3" />
                </a>
            @else
                <span class="
                    {{ is_bool($node) ? 'text-purple-600 dark:text-purple-400 font-semibold' : '' }}
                    {{ is_numeric($node) ? 'text-green-600 dark:text-green-400' : '' }}
                    {{ is_null($node) ? 'text-gray-400 dark:text-gray-500 italic' : '' }}
                ">
                    {{ is_bool($node) ? ($node ? 'true' : 'false') : ($node ?? 'null') }}
                </span>
            @endif
        @else
            <span class="text-xs text-gray-500 dark:text-gray-400">
                ({{ count($node) }})
            </span>
        @endif

        {{-- Copy path button --}}
        @if($showCopyPath)
            <button
                type="button"
                @click.stop="$root.copyPath(path)"
                class="opacity-0 group-hover:opacity-100 transition-opacity ml-auto"
                title="העתק נתיב"
            >
                <x-heroicon-o-clipboard class="w-4 h-4 text-gray-400 hover:text-primary-600 dark:hover:text-primary-400" />
            </button>
        @endif
    </div>

    {{-- Children --}}
    @if($isArray || $isObject)
        <div x-show="isOpen" class="mt-1 space-y-1">
            @foreach($node as $childKey => $child)
                <x-officeguy::api-payload-node
                    :node="$child"
                    :node-key="$isArray ? '[' . $childKey . ']' : $childKey"
                    :level="$level + 1"
                    :highlight="$highlight"
                    :show-copy-path="$showCopyPath"
                    :enable-links="$enableLinks"
                    :parent-path="$currentPath"
                />
            @endforeach
        </div>
    @endif
</div>
