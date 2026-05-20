<script setup>
import { ref, nextTick } from 'vue';

const props = defineProps({
    listId: { type: Number, required: true },
});

const emit = defineEmits(['create']);

const open = ref(false);
const title = ref('');
const textareaRef = ref(null);
const submitting = ref(false);

async function show() {
    open.value = true;
    await nextTick();
    textareaRef.value?.focus();
}

function reset() {
    open.value = false;
    title.value = '';
}

async function submit() {
    const value = title.value.trim();
    if (!value || submitting.value) return;
    submitting.value = true;
    try {
        await emit('create', { title: value });
        title.value = '';
        await nextTick();
        textareaRef.value?.focus();
    } finally {
        submitting.value = false;
    }
}

function handleKey(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        submit();
    } else if (e.key === 'Escape') {
        reset();
    }
}
</script>

<template>
    <div class="px-2 pb-2">
        <button
            v-if="!open"
            type="button"
            class="flex w-full items-center gap-1 rounded-md px-2 py-1.5 text-left text-sm text-slate-600 transition hover:bg-slate-200/70 dark:text-slate-400 dark:hover:bg-slate-700/50"
            @click="show"
        >
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            Add a card
        </button>

        <form v-else class="space-y-2" @submit.prevent="submit">
            <textarea
                ref="textareaRef"
                v-model="title"
                rows="2"
                placeholder="Enter a title for this card…"
                class="w-full resize-none rounded-md border-slate-300 bg-white text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100"
                @keydown="handleKey"
            />
            <div class="flex items-center gap-2">
                <button
                    type="submit"
                    :disabled="submitting"
                    class="rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white shadow-sm transition hover:bg-indigo-700 disabled:opacity-50"
                >
                    Add card
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
