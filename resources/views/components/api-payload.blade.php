@props([
    'value' => null,
    'highlight' => ['Payment', 'Customer', 'Errors', 'Error', 'Status', 'Data'],
    'showCopyPath' => true,
    'enableLinks' => true,
    'minSearchChars' => 2,
    'defaultExpandDepth' => 1,
    'maxRenderNodes' => 2500,
    'maxStringifyBytes' => 350_000,
    'maxDepth' => 18,
])

<div class="api-payload-wrapper" wire:ignore>
    <div
        x-data="apiPayloadTree(
            {{ \Illuminate\Support\Js::from($value) }},
            {{ \Illuminate\Support\Js::from($highlight) }},
            {{ \Illuminate\Support\Js::from($showCopyPath) }},
            {{ \Illuminate\Support\Js::from($enableLinks) }},
            {{ \Illuminate\Support\Js::from((int) $minSearchChars) }},
            {{ \Illuminate\Support\Js::from((int) $defaultExpandDepth) }},
            {{ \Illuminate\Support\Js::from((int) $maxRenderNodes) }},
            {{ \Illuminate\Support\Js::from((int) $maxStringifyBytes) }},
            {{ \Illuminate\Support\Js::from((int) $maxDepth) }}
        )"
        x-init="init()"
        class="space-y-3 text-sm"
    >
        {{-- Toolbar --}}
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            {{-- Search --}}
            <div class="relative w-full sm:max-w-md">
                <input
                    type="text"
                    x-model.debounce.300ms="search"
                    :placeholder="`חיפוש בתוך JSON… (מינימום ${minSearchChars} תווים)`"
                    class="w-full rounded-md border-gray-300 text-xs focus:ring-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200 pr-8"
                >
                <div class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">
                    <x-heroicon-o-magnifying-glass class="w-4 h-4" />
                </div>

                <div
                    x-show="search.length > 0 && search.length < minSearchChars"
                    class="text-xs text-orange-600 dark:text-orange-400 mt-1"
                >
                    נא להזין לפחות <span x-text="minSearchChars"></span> תווים
                </div>
            </div>

            {{-- Actions --}}
            <div class="flex items-center gap-2 justify-end">
                <button
                    type="button"
                    class="inline-flex items-center gap-1 rounded-md border border-gray-300 px-2 py-1 text-xs text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700"
                    @click="expandAll()"
                    :disabled="isTooLarge"
                    title="פתח הכל"
                >
                    <x-heroicon-o-plus-circle class="w-4 h-4" />
                    פתח הכל
                </button>

                <button
                    type="button"
                    class="inline-flex items-center gap-1 rounded-md border border-gray-300 px-2 py-1 text-xs text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700"
                    @click="collapseAll()"
                    :disabled="isTooLarge"
                    title="סגור הכל"
                >
                    <x-heroicon-o-minus-circle class="w-4 h-4" />
                    סגור הכל
                </button>

                <button
                    type="button"
                    class="inline-flex items-center gap-1 rounded-md border border-gray-300 px-2 py-1 text-xs text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700"
                    @click="resetView()"
                    title="איפוס"
                >
                    <x-heroicon-o-arrow-path class="w-4 h-4" />
                    איפוס
                </button>
            </div>
        </div>

        {{-- Copy notification --}}
        <div
            x-show="showCopyNotification"
            x-transition
            class="fixed top-4 left-1/2 -translate-x-1/2 bg-green-600 text-white px-4 py-2 rounded-md shadow-lg z-50 flex items-center gap-2"
        >
            <x-heroicon-o-clipboard-document-check class="w-5 h-5" />
            <span>הנתיב הועתק ללוח!</span>
        </div>

        {{-- Performance guard banner --}}
        <div
            x-show="isTooLarge"
            x-cloak
            class="rounded-md border border-orange-300 bg-orange-50 p-3 text-xs text-orange-800 dark:border-orange-700 dark:bg-orange-900/30 dark:text-orange-200"
        >
            <div class="flex items-start gap-2">
                <x-heroicon-o-exclamation-triangle class="w-5 h-5 mt-0.5" />
                <div class="space-y-1">
                    <div class="font-semibold">Payload גדול מדי להצגה מלאה.</div>
                    <div>
                        כדי לשמור על ביצועים, התצוגה מוגבלת.
                        ניתן עדיין לבצע חיפוש, אך פעולות "פתח הכל / סגור הכל" מושבתות.
                    </div>
                    <div class="text-[11px] opacity-80">
                        מגבלות: maxNodes=<span x-text="maxRenderNodes"></span>,
                        maxBytes=<span x-text="maxStringifyBytes"></span>,
                        maxDepth=<span x-text="maxDepth"></span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Tree Container --}}
        <div class="font-mono bg-gray-50 dark:bg-gray-800 rounded-md p-3 overflow-x-auto max-h-[600px] overflow-y-auto">
            @if(empty($value))
                <div class="flex items-center gap-2 text-gray-500 dark:text-gray-400 italic">
                    <x-heroicon-o-information-circle class="w-5 h-5" />
                    <span>אין נתונים</span>
                </div>
            @else
                <div class="space-y-1">
                    @foreach($value as $key => $val)
                        <x-officeguy::api-payload-node
                            :node="$val"
                            :node-key="is_int($key) ? '[' . $key . ']' : $key"
                            :level="0"
                            :highlight="$highlight"
                            :show-copy-path="$showCopyPath"
                            :enable-links="$enableLinks"
                        />
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Stats --}}
        <div class="text-xs text-gray-500 dark:text-gray-400 flex justify-between items-center">
            <div class="flex items-center gap-1">
                <x-heroicon-o-hashtag class="w-3 h-3" />
                <span x-text="meta.totalKeys"></span> שדות
            </div>

            <div x-show="search.length >= minSearchChars" class="flex items-center gap-1">
                <x-heroicon-o-funnel class="w-3 h-3" />
                <span x-text="meta.filteredKeys"></span> תוצאות
            </div>

            <div class="flex items-center gap-1">
                <x-heroicon-o-cpu-chip class="w-3 h-3" />
                <span x-text="meta.renderedNodes"></span> nodes
            </div>
        </div>
    </div>
</div>

@once
@push('scripts')
<script>
    // Global store for tree state - accessible to all components
    document.addEventListener('alpine:init', () => {
        Alpine.store('apiPayloadTree', {
            openMap: {},

            isOpen(path) {
                return !!this.openMap[path];
            },

            toggle(path) {
                this.openMap[path] = !this.openMap[path];
            },

            setOpen(path, state) {
                this.openMap[path] = state;
            },

            reset() {
                this.openMap = {};
            }
        });
    });

    window.apiPayloadTree = function (payload, highlight = [], showCopyPath = true, enableLinks = true, minSearchChars = 2, defaultExpandDepth = 1, maxRenderNodes = 2500, maxStringifyBytes = 350000, maxDepth = 18) {
            return {
                data: payload ?? null,
                search: '',
                highlight,
                showCopyPath,
                enableLinks,
                minSearchChars,
                defaultExpandDepth,
                maxRenderNodes,
                maxStringifyBytes,
                maxDepth,

                showCopyNotification: false,
                isTooLarge: false,

                meta: {
                    totalKeys: 0,
                    filteredKeys: 0,
                    renderedNodes: 0,
                    approxBytes: 0,
                },

                init() {
                    if (this.data === null) {
                        this.meta.totalKeys = 0;
                        this.meta.filteredKeys = 0;
                        this.meta.renderedNodes = 0;
                        return;
                    }

                    const approx = this.safeApproxBytes(this.data);
                    this.meta.approxBytes = approx;

                    const nodeCount = this.countNodesGuarded(this.data);
                    this.meta.totalKeys = nodeCount.keys;
                    this.meta.renderedNodes = nodeCount.nodes;

                    this.isTooLarge = (nodeCount.nodes > this.maxRenderNodes) || (approx > this.maxStringifyBytes);

                    this.applyDefaultExpand();
                },

                resetView() {
                    this.search = '';
                    Alpine.store('apiPayloadTree').reset();
                    this.applyDefaultExpand();
                },

                applyDefaultExpand() {
                    if (this.data === null) return;
                    const store = Alpine.store('apiPayloadTree');
                    store.reset();

                    const root = this.buildRootEntries(this.data);
                    for (const entry of root) {
                        if (entry.kind === 'node') {
                            store.setOpen(entry.path, entry.level < this.defaultExpandDepth);
                            this.expandByDepth(entry, this.defaultExpandDepth);
                        }
                    }
                },

                expandByDepth(entry, depth) {
                    if (!entry || entry.kind !== 'node') return;
                    if (entry.level >= depth) return;

                    const store = Alpine.store('apiPayloadTree');
                    const children = this.getChildrenEntries(entry);
                    for (const child of children) {
                        if (child.kind === 'node') {
                            store.setOpen(child.path, child.level < depth);
                            this.expandByDepth(child, depth);
                        }
                    }
                },

                expandAll() {
                    if (this.isTooLarge || this.data === null) return;

                    const store = Alpine.store('apiPayloadTree');
                    store.reset();
                    const root = this.buildRootEntries(this.data);

                    const stack = [...root];
                    let touched = 0;

                    while (stack.length) {
                        const entry = stack.pop();
                        if (!entry || entry.kind !== 'node') continue;

                        store.setOpen(entry.path, true);
                        touched++;

                        if (touched > this.maxRenderNodes) break;

                        const children = this.getChildrenEntries(entry);
                        for (const c of children) {
                            if (c.kind === 'node') stack.push(c);
                        }
                    }
                },

                collapseAll() {
                    if (this.isTooLarge || this.data === null) return;

                    const store = Alpine.store('apiPayloadTree');
                    store.reset();
                    const root = this.buildRootEntries(this.data);

                    const stack = [...root];
                    let touched = 0;

                    while (stack.length) {
                        const entry = stack.pop();
                        if (!entry || entry.kind !== 'node') continue;

                        store.setOpen(entry.path, false);
                        touched++;

                        if (touched > this.maxRenderNodes) break;

                        const children = this.getChildrenEntries(entry);
                        for (const c of children) {
                            if (c.kind === 'node') stack.push(c);
                        }
                    }
                },

                visibleRootEntries() {
                    if (this.data === null) return [];
                    const entries = this.buildRootEntries(this.data);

                    if (!this.search || this.search.length < this.minSearchChars) {
                        this.meta.filteredKeys = this.meta.totalKeys;
                        return entries;
                    }

                    const needle = this.search.toLowerCase();
                    const filtered = [];

                    let keys = 0;

                    for (const e of entries) {
                        const kept = this.filterEntry(e, needle);
                        if (kept) {
                            filtered.push(kept);
                            keys += this.countKeysFromEntry(kept);
                        }
                    }

                    this.meta.filteredKeys = keys;
                    return filtered;
                },

                filterEntry(entry, needle) {
                    if (!entry) return null;

                    if (entry.kind === 'leaf') {
                        const hit = this.matches(entry.key, entry.value, needle);
                        return hit ? entry : null;
                    }

                    if (entry.kind === 'node') {
                        if (this.matches(entry.key, null, needle)) {
                            return entry;
                        }

                        const children = this.getChildrenEntries(entry);
                        const keptChildren = [];

                        for (const c of children) {
                            const kept = this.filterEntry(c, needle);
                            if (kept) keptChildren.push(kept);
                        }

                        if (keptChildren.length === 0) return null;

                        return {
                            ...entry,
                            _filteredChildren: keptChildren,
                            _isFiltered: true,
                        };
                    }

                    return null;
                },

                matches(key, value, needle) {
                    const k = (key ?? '').toString().toLowerCase();
                    if (k.includes(needle)) return true;

                    if (value === null || value === undefined) return false;

                    if (typeof value === 'object') {
                        const s = this.safeStringify(value);
                        return s.toLowerCase().includes(needle);
                    }

                    return String(value).toLowerCase().includes(needle);
                },

                buildRootEntries(data) {
                    const isArray = Array.isArray(data);
                    const entries = [];

                    if (isArray) {
                        for (let i = 0; i < data.length; i++) {
                            entries.push(this.makeEntry(`[${i}]`, data[i], '$', 0));
                        }
                    } else if (typeof data === 'object' && data !== null) {
                        for (const [k, v] of Object.entries(data)) {
                            entries.push(this.makeEntry(k, v, '$', 0));
                        }
                    } else {
                        entries.push(this.makeEntry('$', data, '$', 0));
                    }

                    return entries;
                },

                makeEntry(key, value, parentJsonPath, level) {
                    const path = this.joinJsonPath(parentJsonPath, key);

                    if (this.isBranch(value) && level < this.maxDepth) {
                        return {
                            kind: 'node',
                            key,
                            value,
                            path,
                            level,
                        };
                    }

                    return {
                        kind: 'leaf',
                        key,
                        value,
                        path,
                        level,
                    };
                },

                getChildrenEntries(entry) {
                    if (!entry || entry.kind !== 'node') return [];

                    if (entry._isFiltered && Array.isArray(entry._filteredChildren)) {
                        return entry._filteredChildren;
                    }

                    const value = entry.value;

                    if (!this.isBranch(value)) return [];

                    const isArray = Array.isArray(value);
                    const children = [];

                    if (isArray) {
                        for (let i = 0; i < value.length; i++) {
                            children.push(this.makeEntry(`[${i}]`, value[i], entry.path, entry.level + 1));
                        }
                    } else {
                        for (const [k, v] of Object.entries(value)) {
                            children.push(this.makeEntry(k, v, entry.path, entry.level + 1));
                        }
                    }

                    return children;
                },

                joinJsonPath(parent, key) {
                    const k = String(key);

                    if (k.startsWith('[') && k.endsWith(']')) {
                        return `${parent}${k}`;
                    }

                    if (this.isSimpleIdentifier(k)) {
                        return `${parent}.${k}`;
                    }

                    const escaped = k.replace(/\\/g, '\\\\').replace(/"/g, '\\"');
                    return `${parent}["${escaped}"]`;
                },

                isSimpleIdentifier(k) {
                    return /^[A-Za-z_$][A-Za-z0-9_$]*$/.test(k);
                },

                isBranch(value) {
                    return typeof value === 'object' && value !== null;
                },

                safeStringify(value) {
                    try {
                        const s = JSON.stringify(value);
                        if (s && s.length > this.maxStringifyBytes) {
                            return s.slice(0, this.maxStringifyBytes) + '…';
                        }
                        return s ?? '';
                    } catch (e) {
                        return '';
                    }
                },

                safeApproxBytes(value) {
                    try {
                        const s = JSON.stringify(value);
                        return s ? s.length : 0;
                    } catch (e) {
                        return this.roughObjectSize(value);
                    }
                },

                roughObjectSize(obj) {
                    const seen = new WeakSet();
                    const stack = [obj];
                    let bytes = 0;

                    while (stack.length) {
                        const v = stack.pop();

                        if (v === null || v === undefined) continue;

                        const t = typeof v;

                        if (t === 'string') {
                            bytes += v.length * 2;
                            continue;
                        }
                        if (t === 'number') {
                            bytes += 8;
                            continue;
                        }
                        if (t === 'boolean') {
                            bytes += 4;
                            continue;
                        }
                        if (t !== 'object') continue;

                        if (seen.has(v)) continue;
                        seen.add(v);

                        if (Array.isArray(v)) {
                            for (const i of v) stack.push(i);
                        } else {
                            for (const [k, val] of Object.entries(v)) {
                                bytes += k.length * 2;
                                stack.push(val);
                            }
                        }

                        if (bytes > this.maxStringifyBytes) break;
                    }

                    return bytes;
                },

                countNodesGuarded(obj) {
                    const seen = new WeakSet();
                    const stack = [{ v: obj, depth: 0 }];
                    let keys = 0;
                    let nodes = 0;

                    while (stack.length) {
                        const { v, depth } = stack.pop();
                        if (v === null || v === undefined) continue;

                        const t = typeof v;
                        if (t !== 'object') continue;

                        if (seen.has(v)) continue;
                        seen.add(v);

                        nodes++;
                        if (nodes > this.maxRenderNodes) break;
                        if (depth > this.maxDepth) continue;

                        if (Array.isArray(v)) {
                            for (let i = 0; i < v.length; i++) {
                                keys++;
                                stack.push({ v: v[i], depth: depth + 1 });
                                if (nodes > this.maxRenderNodes) break;
                            }
                        } else {
                            for (const [k, val] of Object.entries(v)) {
                                keys++;
                                stack.push({ v: val, depth: depth + 1 });
                                if (nodes > this.maxRenderNodes) break;
                            }
                        }
                    }

                    return { keys, nodes };
                },

                countKeysFromEntry(entry) {
                    if (!entry) return 0;
                    if (entry.kind === 'leaf') return 1;

                    let count = 1;
                    const children = this.getChildrenEntries(entry);
                    for (const c of children) count += this.countKeysFromEntry(c);
                    return count;
                },

                copyToClipboard(text) {
                    if (!text) return;
                    navigator.clipboard.writeText(text).then(() => {
                        this.showCopyNotification = true;
                        setTimeout(() => this.showCopyNotification = false, 1800);
                    });
                },

                copyPath(path) {
                    this.copyToClipboard(path);
                },
        };
    };

    window.apiPayloadNode = function (entry, root) {
        return {
                entry,
                root,
                get showCopyPath() { return root.showCopyPath; },

                get isBranch() {
                    return entry.kind === 'node';
                },

                get isOpen() {
                    return Alpine.store('apiPayloadTree').isOpen(entry.path);
                },

                get displayKey() {
                    return entry.key;
                },

                get childCount() {
                    if (entry.kind !== 'node') return 0;
                    const v = entry.value;
                    if (Array.isArray(v)) return v.length;
                    if (typeof v === 'object' && v !== null) return Object.keys(v).length;
                    return 0;
                },

                get keyClass() {
                    const k = String(entry.key ?? '').toLowerCase();
                    const hl = root.highlight.some(h => k.includes(String(h).toLowerCase()));
                    return hl ? 'text-primary-600 dark:text-primary-400' : 'text-gray-700 dark:text-gray-300';
                },

                get displayValue() {
                    if (entry.kind !== 'leaf') return '';
                    const v = entry.value;

                    if (v === null) return 'null';
                    if (typeof v === 'boolean') return v ? 'true' : 'false';
                    if (typeof v === 'number') return String(v);

                    if (typeof v === 'object') return root.safeStringify(v);
                    return String(v);
                },

                get valueClass() {
                    if (entry.kind !== 'leaf') return '';
                    const v = entry.value;

                    if (v === null) return 'text-gray-400 dark:text-gray-500 italic';
                    if (typeof v === 'boolean') return 'text-purple-600 dark:text-purple-400 font-semibold';
                    if (typeof v === 'number') return 'text-green-600 dark:text-green-400';
                    return 'text-gray-700 dark:text-gray-300';
                },

                toggle() {
                    if (!this.isBranch) return;
                    Alpine.store('apiPayloadTree').toggle(entry.path);
                },

                children() {
                    if (entry.kind !== 'node') return [];
                    // Memoization - cache children to avoid repeated computations
                    if (!entry._children) {
                        entry._children = root.getChildrenEntries(entry);
                    }
                    return entry._children;
                },

                copyPath() {
                    root.copyToClipboard(entry.path);
                },

                indentStyle(level) {
                    const clamped = Math.min(level, 12);
                    return `padding-inline-start: ${clamped * 0.75}rem;`;
                }
        };
    };
</script>
@endpush
@endonce