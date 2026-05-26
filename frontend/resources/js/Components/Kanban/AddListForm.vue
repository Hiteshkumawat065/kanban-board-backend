<script setup>
import { ref, nextTick } from 'vue';

const emit = defineEmits(['create']);

const open = ref(false);
const name = ref('');
const inputRef = ref(null);
const submitting = ref(false);

async function show() {
    open.value = true;
    await nextTick();
    inputRef.value?.focus();
}

function reset() {
    open.value = false;
    name.value = '';
}

async function submit() {
    const value = name.value.trim();
    if (!value || submitting.value) return;
    submitting.value = true;
    try {
        await emit('create', value);
        name.value = '';
        reset();
    } finally {
        submitting.value = false;
    }
}
</script>

<template>
    <div class="w-72 shrink-0">
        <button
            v-if="!open"
            type="button"
            class="flex w-full items-center gap-2 rounded-lg bg-white/70 px-3 py-2.5 text-sm font-medium text-slate-700 backdrop-blur transition hover:bg-white dark:bg-slate-800/60 dark:text-slate-200 dark:hover:bg-slate-800"
            @click="show"
        >
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            Add another list
        </button>

        <form
            v-else
            class="space-y-2 rounded-lg bg-slate-100 p-2 dark:bg-slate-800"
            @submit.prevent="submit"
        >
            <input
                ref="inputRef"
                v-model="name"
                type="text"
                placeholder="Enter list title…"
                class="w-full rounded-md border-slate-300 bg-white text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100"
                @keydown.escape="reset"
            />
            <div class="flex items-center gap-2">
                <button
                    type="submit"
                    :disabled="submitting"
                    class="rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white shadow-sm transition hover:bg-indigo-700 disabled:opacity-50"
                >
                    Add list
                </button>
                <button
                    type="button"
                    class="rounded-md p-1.5 text-slate-500 transition hover:bg-slate-200 dark:hover:bg-slate-700"
                    @click="reset"
                >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </form>
    </div>
</template>
