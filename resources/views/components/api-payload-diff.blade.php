@props([
    'request' => null,
    'response' => null,
])

<div class="api-payload-diff-wrapper" wire:ignore>
    <div x-data="apiPayloadDiff(
        {{ \Illuminate\Support\Js::from($request) }},
        {{ \Illuminate\Support\Js::from($response) }}
    )" class="space-y-4">

        {{-- Header --}}
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
                <x-heroicon-o-arrows-right-left class="w-5 h-5 text-gray-500 dark:text-gray-400" />
                <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">השוואת Request ל-Response</h3>
            </div>
            <div class="flex gap-2">
                <button
                    @click="showOnlyDiff = !showOnlyDiff"
                    class="text-xs px-3 py-1 rounded-md transition-colors"
                    :class="showOnlyDiff ? 'bg-primary-500 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300'"
                >
                    <span class="flex items-center gap-1">
                        <x-heroicon-o-funnel class="w-3 h-3" />
                        <span>רק הבדלים</span>
                    </span>
                </button>
                <button
                    @click="highlightMode = (highlightMode === 'none' ? 'additions' : (highlightMode === 'additions' ? 'all' : 'none'))"
                    class="text-xs px-3 py-1 rounded-md bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300"
                >
                    <span class="flex items-center gap-1">
                        <x-heroicon-o-paint-brush class="w-3 h-3" />
                        <span x-text="highlightMode === 'none' ? 'ללא הדגשה' : (highlightMode === 'additions' ? 'תוספות' : 'הכל')"></span>
                    </span>
                </button>
            </div>
        </div>

        {{-- Stats --}}
        <div class="grid grid-cols-3 gap-4 text-xs">
            <div class="flex items-center gap-2 p-2 rounded-md bg-blue-50 dark:bg-blue-900/20">
                <x-heroicon-o-arrow-up class="w-4 h-4 text-blue-600 dark:text-blue-400" />
                <div>
                    <div class="font-semibold text-blue-900 dark:text-blue-100">Request</div>
                    <div class="text-blue-600 dark:text-blue-400" x-text="countKeys(request)"></div>
                </div>
            </div>
            <div class="flex items-center gap-2 p-2 rounded-md bg-green-50 dark:bg-green-900/20">
                <x-heroicon-o-arrow-down class="w-4 h-4 text-green-600 dark:text-green-400" />
                <div>
                    <div class="font-semibold text-green-900 dark:text-green-100">Response</div>
                    <div class="text-green-600 dark:text-green-400" x-text="countKeys(response)"></div>
                </div>
            </div>
            <div class="flex items-center gap-2 p-2 rounded-md bg-orange-50 dark:bg-orange-900/20">
                <x-heroicon-o-arrows-right-left class="w-4 h-4 text-orange-600 dark:text-orange-400" />
                <div>
                    <div class="font-semibold text-orange-900 dark:text-orange-100">הבדלים</div>
                    <div class="text-orange-600 dark:text-orange-400" x-text="diffCount"></div>
                </div>
            </div>
        </div>

        {{-- Side by side comparison --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            {{-- Request --}}
            <div class="space-y-2">
                <div class="flex items-center gap-2 text-sm font-semibold text-blue-700 dark:text-blue-300">
                    <x-heroicon-o-arrow-up class="w-4 h-4" />
                    <span>Request</span>
                </div>
                <div class="font-mono bg-blue-50 dark:bg-blue-900/10 rounded-md p-3 overflow-x-auto max-h-[500px] overflow-y-auto border border-blue-200 dark:border-blue-800">
                    <template x-if="!request || Object.keys(request).length === 0">
                        <div class="text-gray-500 dark:text-gray-400 italic flex items-center gap-2">
                            <x-heroicon-o-information-circle class="w-4 h-4" />
                            <span>אין נתוני Request</span>
                        </div>
                    </template>
                    <template x-if="request && Object.keys(request).length > 0">
                        <div>
                            <template x-for="(nodeValue, nodeKey) in displayRequest()" :key="nodeKey">
                                <div :class="{'opacity-30': showOnlyDiff && !isDifferent(nodeKey)}" x-html="renderNode(nodeValue, nodeKey, 0, '')"></div>
                            </template>
                        </div>
                    </template>
                </div>
            </div>

            {{-- Response --}}
            <div class="space-y-2">
                <div class="flex items-center gap-2 text-sm font-semibold text-green-700 dark:text-green-300">
                    <x-heroicon-o-arrow-down class="w-4 h-4" />
                    <span>Response</span>
                </div>
                <div class="font-mono bg-green-50 dark:bg-green-900/10 rounded-md p-3 overflow-x-auto max-h-[500px] overflow-y-auto border border-green-200 dark:border-green-800">
                    <template x-if="!response || Object.keys(response).length === 0">
                        <div class="text-gray-500 dark:text-gray-400 italic flex items-center gap-2">
                            <x-heroicon-o-information-circle class="w-4 h-4" />
                            <span>אין נתוני Response</span>
                        </div>
                    </template>
                    <template x-if="response && Object.keys(response).length > 0">
                        <div>
                            <template x-for="(nodeValue, nodeKey) in displayResponse()" :key="nodeKey">
                                <div
                                    :class="{
                                        'opacity-30': showOnlyDiff && !isDifferent(nodeKey),
                                        'bg-green-100 dark:bg-green-900/30 rounded px-1': highlightMode !== 'none' && !hasInRequest(nodeKey),
                                        'bg-yellow-100 dark:bg-yellow-900/30 rounded px-1': highlightMode === 'all' && isDifferent(nodeKey) && hasInRequest(nodeKey)
                                    }"
                                    x-html="renderNode(nodeValue, nodeKey, 0, '')"
                                ></div>
                            </template>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </div>
</div>

@once
@push('scripts')
<script>
    window.apiPayloadDiff = function (req, res) {
        return {
            request: req,
            response: res,
            showOnlyDiff: false,
            highlightMode: 'all', // 'none', 'additions', 'all'

            get diffCount() {
                if (!this.response || !this.request) return 0;
                let count = 0;
                for (let key in this.response) {
                    if (!(key in this.request) || JSON.stringify(this.response[key]) !== JSON.stringify(this.request[key])) {
                        count++;
                    }
                }
                return count;
            },

            isDifferent(key) {
                if (!this.request || !this.response) return false;
                if (!(key in this.request) || !(key in this.response)) return true;
                return JSON.stringify(this.request[key]) !== JSON.stringify(this.response[key]);
            },

            hasInRequest(key) {
                return this.request && (key in this.request);
            },

            displayRequest() {
                if (!this.showOnlyDiff) return this.request;
                const filtered = {};
                for (let key in this.request) {
                    if (this.isDifferent(key)) {
                        filtered[key] = this.request[key];
                    }
                }
                return filtered;
            },

            displayResponse() {
                if (!this.showOnlyDiff) return this.response;
                const filtered = {};
                for (let key in this.response) {
                    if (this.isDifferent(key)) {
                        filtered[key] = this.response[key];
                    }
                }
                return filtered;
            },

            countKeys(obj) {
                if (!obj || typeof obj !== 'object') return 0;
                let count = 0;
                const traverse = (o) => {
                    for (let k in o) {
                        count++;
                        if (typeof o[k] === 'object' && o[k] !== null) {
                            traverse(o[k]);
                        }
                    }
                };
                traverse(obj);
                return count;
            },

            // ✅ XSS Protection
            escapeHtml(value) {
                return String(value)
                    .replace(/&/g, "&amp;")
                    .replace(/</g, "&lt;")
                    .replace(/>/g, "&gt;")
                    .replace(/"/g, "&quot;")
                    .replace(/'/g, "&#039;");
            },

            // ✅ Pure Function
            renderNode(nodeValue, nodeKey, level, parentPath) {
                const isArray = Array.isArray(nodeValue);
                const isObject = typeof nodeValue === 'object' && nodeValue !== null && !isArray;
                const isScalar = !isArray && !isObject;
                const isNull = nodeValue === null;
                const isBoolean = typeof nodeValue === 'boolean';
                const isNumber = typeof nodeValue === 'number';

                const currentPath = parentPath ? `${parentPath}.${nodeKey}` : nodeKey;
                const paddingClass = `pl-${Math.min(level * 4, 12)}`;
                const isOpen = level < 1;

                let html = `<div class="${paddingClass}" x-data="{ open: ${isOpen} }">`;
                html += '<div class="flex items-center gap-2 cursor-pointer select-none hover:bg-gray-100 dark:hover:bg-gray-700 rounded px-1 py-0.5 group" @click="open = !open">';

                // Expand/collapse arrow
                if (isArray || isObject) {
                    html += '<span class="text-gray-500 dark:text-gray-400 flex-shrink-0">';
                    html += '<svg x-show="!open" class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>';
                    html += '<svg x-show="open" class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>';
                    html += '</span>';
                } else {
                    html += '<span class="w-3 h-3 flex-shrink-0"></span>';
                }

                // Key name
                const safeKey = this.escapeHtml(nodeKey);
                const displayKey = isArray ? `${safeKey} <span class="text-xs text-gray-500 dark:text-gray-400">[Array]</span>` : safeKey;
                html += `<span class="font-semibold text-gray-700 dark:text-gray-300">${displayKey}</span>`;

                // Value or count
                if (isScalar) {
                    html += '<span class="text-gray-700 dark:text-gray-300">:</span>';

                    let displayValue, valueClass;

                    if (isNull) {
                        displayValue = 'null';
                        valueClass = 'text-gray-400 dark:text-gray-500 italic';
                    } else if (isBoolean) {
                        displayValue = nodeValue ? 'true' : 'false';
                        valueClass = 'text-purple-600 dark:text-purple-400 font-semibold';
                    } else if (isNumber) {
                        displayValue = this.escapeHtml(nodeValue);
                        valueClass = 'text-green-600 dark:text-green-400';
                    } else {
                        displayValue = this.escapeHtml(nodeValue);
                        valueClass = '';
                    }

                    html += `<span class="${valueClass}">${displayValue}</span>`;
                } else {
                    const count = isArray ? nodeValue.length : Object.keys(nodeValue).length;
                    html += `<span class="text-xs text-gray-500 dark:text-gray-400">(${count})</span>`;
                }

                html += '</div>'; // End flex container

                // Children (recursive)
                if (isArray || isObject) {
                    html += '<div x-show="open" class="mt-1 space-y-1">';
                    if (isArray) {
                        for (let i = 0; i < nodeValue.length; i++) {
                            html += this.renderNode(nodeValue[i], `[${i}]`, level + 1, currentPath);
                        }
                    } else {
                        for (const [childKey, childValue] of Object.entries(nodeValue)) {
                            html += this.renderNode(childValue, childKey, level + 1, currentPath);
                        }
                    }
                    html += '</div>';
                }

                html += '</div>'; // End main container
                return html;
            }
        };
    };
</script>
@endpush
@endonce
