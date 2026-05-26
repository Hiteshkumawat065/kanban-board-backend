<script setup>
import { computed, ref } from 'vue';

// =============================================================================
//  VariablePalette.vue
//
//  Right-hand sidebar that lists every placeholder available to the email
//  body. Two sources:
//
//    1. System variables — globally available everywhere (current_date,
//       user_name, …). Provided by the backend so the list stays in sync
//       with VariableParser::systemVariables().
//    2. Template variables — extra placeholders the author declared on
//       this specific template, each with an optional sample value used
//       by the preview panel.
//
//  Each row exposes two actions:
//
//    - "Insert"   ⟶ emits `insert` with `{{ key }}` — the editor uses this
//                   to drop the token at the cursor.
//    - "Copy"     ⟶ writes the token to the clipboard so the author can
//                   paste it anywhere (subject line input, etc.).
//
//  Template variables also support add / remove / sample editing, which
//  is what powers the preview panel's mock data.
// =============================================================================

const props = defineProps({
    systemVariables: { type: Array, default: () => [] },
    templateVariables: { type: Array, default: () => [] },
});

const emit = defineEmits(['insert', 'update:templateVariables']);

const search = ref('');

const filteredSystem = computed(() => {
    const q = search.value.trim().toLowerCase();
    if (!q) return props.systemVariables;
    return props.systemVariables.filter(
        (v) =>
            v.key.toLowerCase().includes(q) ||
            (v.label ?? '').toLowerCase().includes(q),
    );
});

function insert(key) {
    emit('insert', `{{ ${key} }}`);
}

async function copy(key) {
    const token = `{{ ${key} }}`;
    try {
        await navigator.clipboard.writeText(token);
        flash(key);
    } catch (e) {
        // Older browsers / non-secure contexts — fall back to a hidden
        // textarea so "copy" still works on http://localhost.
        const ta = document.createElement('textarea');
        ta.value = token;
        document.body.appendChild(ta);
        ta.select();
        document.execCommand('copy');
        document.body.removeChild(ta);
        flash(key);
    }
}

const copiedKey = ref(null);
function flash(key) {
    copiedKey.value = key;
    setTimeout(() => {
        if (copiedKey.value === key) copiedKey.value = null;
    }, 1200);
}

// -----------------------------------------------------------------------------
// Template variable management.
// -----------------------------------------------------------------------------
function addCustomVariable() {
    const next = [
        ...props.templateVariables,
        { key: '', label: '', sample: '' },
    ];
    emit('update:templateVariables', next);
}

function removeCustomVariable(index) {
    const next = props.templateVariables.filter((_, i) => i !== index);
    emit('update:templateVariables', next);
}

function patchCustomVariable(index, key, value) {
    const next = props.templateVariables.map((v, i) =>
        i === index ? { ...v, [key]: value } : v,
    );
    emit('update:templateVariables', next);
}

// Returns the wrapped placeholder string ("{{ key }}"). Defined as a
// helper so the Vue template never contains nested `{{` literals inside
// a Mustache interpolation — the parser is greedy with `{{` and bails
// on unterminated string constants otherwise.
const OPEN = '{' + '{';
const CLOSE = '}' + '}';
function tokenFor(key) {
    return `${OPEN} ${key} ${CLOSE}`;
}
</script>

<template>
    <aside
        class="flex h-full flex-col rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800"
    >
        <div class="border-b border-slate-200 p-3 dark:border-slate-700">
            <div class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                Variables
            </div>
            <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                Click <span class="font-medium text-slate-700 dark:text-slate-200">Insert</span> to drop a placeholder where your cursor is.
            </p>
            <div class="relative mt-2">
                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-2 text-slate-400">
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z" />
                    </svg>
                </span>
                <input
                    v-model="search"
                    type="search"
                    placeholder="Search variables…"
                    class="w-full rounded-md border-slate-200 bg-slate-50 py-1 pl-7 pr-2 text-xs placeholder-slate-400 focus:border-indigo-500 focus:bg-white focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-900/40 dark:text-slate-100"
                />
            </div>
        </div>

        <div class="flex-1 overflow-y-auto p-3">
            <!-- System variables -->
            <div class="text-[11px] font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">
                System
            </div>
            <ul class="mt-2 space-y-1">
                <li
                    v-for="v in filteredSystem"
                    :key="v.key"
                    class="group rounded-md border border-slate-100 bg-slate-50 px-2 py-1.5 transition hover:border-indigo-200 hover:bg-indigo-50 dark:border-slate-700 dark:bg-slate-900/40 dark:hover:border-indigo-500/40 dark:hover:bg-indigo-500/10"
                >
                    <div class="flex items-center justify-between gap-2">
                        <div class="min-w-0">
                            <div class="truncate font-mono text-[12px] text-indigo-600 dark:text-indigo-300">
                                {{ tokenFor(v.key) }}
                            </div>
                            <div class="truncate text-[11px] text-slate-500 dark:text-slate-400">
                                {{ v.label }}
                            </div>
                        </div>
                        <div class="flex shrink-0 items-center gap-1">
                            <button
                                type="button"
                                class="rounded bg-indigo-600 px-1.5 py-0.5 text-[10px] font-semibold text-white transition hover:bg-indigo-500"
                                @click="insert(v.key)"
                            >
                                Insert
                            </button>
                            <button
                                type="button"
                                class="rounded border border-slate-200 bg-white px-1.5 py-0.5 text-[10px] font-semibold text-slate-600 transition hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-300"
                                :title="copiedKey === v.key ? 'Copied!' : 'Copy token'"
                                @click="copy(v.key)"
                            >
                                {{ copiedKey === v.key ? '✓' : 'Copy' }}
                            </button>
                        </div>
                    </div>
                </li>
            </ul>

            <!-- Template-scoped variables -->
            <div class="mt-5 flex items-center justify-between">
                <div class="text-[11px] font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">
                    Template
                </div>
                <button
                    type="button"
                    class="rounded-md border border-dashed border-slate-300 px-1.5 py-0.5 text-[11px] font-semibold text-slate-600 transition hover:border-indigo-400 hover:text-indigo-600 dark:border-slate-600 dark:text-slate-300"
                    @click="addCustomVariable"
                >
                    + Add
                </button>
            </div>

            <div v-if="!templateVariables.length" class="mt-2 text-xs text-slate-400 dark:text-slate-500">
                No custom variables yet. Add one if your template needs a placeholder beyond the system list (e.g. <span class="font-mono">order_id</span>).
            </div>

            <ul v-else class="mt-2 space-y-2">
                <li
                    v-for="(v, idx) in templateVariables"
                    :key="idx"
                    class="rounded-md border border-slate-200 p-2 dark:border-slate-700"
                >
                    <div class="grid grid-cols-2 gap-1.5">
                        <input
                            :value="v.key"
                            type="text"
                            placeholder="key"
                            class="rounded-md border-slate-200 bg-white px-2 py-1 text-[11px] font-mono shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-900/40 dark:text-slate-100"
                            @input="patchCustomVariable(idx, 'key', $event.target.value)"
                        />
                        <input
                            :value="v.label"
                            type="text"
                            placeholder="Label"
                            class="rounded-md border-slate-200 bg-white px-2 py-1 text-[11px] shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-900/40 dark:text-slate-100"
                            @input="patchCustomVariable(idx, 'label', $event.target.value)"
                        />
                    </div>
                    <input
                        :value="v.sample"
                        type="text"
                        placeholder="Sample value (used in preview)"
                        class="mt-1.5 w-full rounded-md border-slate-200 bg-white px-2 py-1 text-[11px] shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-900/40 dark:text-slate-100"
                        @input="patchCustomVariable(idx, 'sample', $event.target.value)"
                    />
                    <div class="mt-1.5 flex items-center justify-between">
                        <span class="font-mono text-[11px] text-indigo-600 dark:text-indigo-300">
                            {{ v.key ? tokenFor(v.key) : '—' }}
                        </span>
                        <div class="flex items-center gap-1">
                            <button
                                v-if="v.key"
                                type="button"
                                class="rounded bg-indigo-600 px-1.5 py-0.5 text-[10px] font-semibold text-white transition hover:bg-indigo-500"
                                @click="insert(v.key)"
                            >
                                Insert
                            </button>
                            <button
                                type="button"
                                class="rounded border border-rose-200 bg-rose-50 px-1.5 py-0.5 text-[10px] font-semibold text-rose-600 transition hover:bg-rose-100 dark:border-rose-700 dark:bg-rose-900/30 dark:text-rose-300"
                                @click="removeCustomVariable(idx)"
                            >
                                Remove
                            </button>
                        </div>
                    </div>
                </li>
            </ul>
        </div>
    </aside>
</template>
