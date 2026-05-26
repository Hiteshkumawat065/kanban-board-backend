<script setup>
import { reactive, ref, watch } from 'vue';
import Modal from '@/Components/Modal.vue';

// =============================================================================
//  SendTestModal.vue
//
//  Modal that lets an admin fire a test email at any address using the
//  current draft of the template. We hit the backend's `send-test`
//  endpoint synchronously (the service bypasses the queue for tests),
//  so the operator sees an immediate success/failure status without
//  having to refresh the page or open the log viewer.
//
//  The modal also lets the operator override individual variable values
//  before sending — handy when you want to spot-check what the template
//  looks like with realistic data.
// =============================================================================

const props = defineProps({
    show: { type: Boolean, default: false },
    template: { type: Object, required: true }, // { slug, template_name, subject }
    systemVariables: { type: Array, default: () => [] },
    templateVariables: { type: Array, default: () => [] },
});

const emit = defineEmits(['close']);

const to = ref('');
const context = reactive({});
const submitting = ref(false);
const result = ref(null); // { type: 'success' | 'error', message: string }

// Seed defaults whenever the modal opens so the operator gets a populated
// "context" form on first paint — based on each variable's sample value.
watch(
    () => props.show,
    (next) => {
        if (!next) return;
        result.value = null;
        const seeded = {};
        [...props.systemVariables, ...props.templateVariables].forEach((v) => {
            if (v?.key) seeded[v.key] = v.sample ?? '';
        });
        // Wipe and refill (we can't reassign `reactive`).
        Object.keys(context).forEach((k) => delete context[k]);
        Object.assign(context, seeded);
    },
);

async function submit() {
    if (!to.value.trim()) return;
    submitting.value = true;
    result.value = null;
    try {
        const { data } = await window.axios.post(
            route('email-templates.send-test', props.template.slug),
            { to: to.value.trim(), context },
        );
        result.value = {
            type: data.status === 'failed' ? 'error' : 'success',
            message:
                data.status === 'failed'
                    ? data.error || 'Send failed.'
                    : 'Test email sent successfully.',
        };
    } catch (e) {
        result.value = {
            type: 'error',
            message:
                e?.response?.data?.error
                ?? e?.response?.data?.message
                ?? 'Failed to send test email.',
        };
    } finally {
        submitting.value = false;
    }
}
</script>

<template>
    <Modal :show="show" max-width="2xl" @close="emit('close')">
        <div class="px-6 py-5">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-slate-100">
                        Send test email
                    </h3>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        Send a one-off email using <strong>{{ template.template_name }}</strong>. The
                        backend bypasses the queue so you'll get an immediate delivery status.
                    </p>
                </div>
                <button
                    type="button"
                    class="rounded p-1 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-700"
                    @click="emit('close')"
                >
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <form class="mt-5 space-y-4" @submit.prevent="submit">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                        Send to
                    </label>
                    <input
                        v-model="to"
                        type="email"
                        required
                        placeholder="you@example.com"
                        class="mt-1 w-full rounded-md border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100"
                    />
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                        Variable values
                    </label>
                    <p class="mt-0.5 text-[11px] text-slate-500 dark:text-slate-400">
                        Override any placeholder before sending. Defaults come from each variable's sample value.
                    </p>
                    <div class="mt-2 grid max-h-[260px] grid-cols-1 gap-2 overflow-y-auto rounded-md border border-slate-200 bg-slate-50 p-3 dark:border-slate-700 dark:bg-slate-900/40 sm:grid-cols-2">
                        <div v-for="v in [...systemVariables, ...templateVariables].filter((v) => v && v.key)" :key="v.key">
                            <label class="block font-mono text-[11px] text-slate-500 dark:text-slate-400">
                                {{ v.key }}
                            </label>
                            <input
                                v-model="context[v.key]"
                                type="text"
                                :placeholder="v.sample ?? ''"
                                class="mt-0.5 w-full rounded-md border-slate-200 bg-white px-2 py-1 text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100"
                            />
                        </div>
                    </div>
                </div>

                <div
                    v-if="result"
                    class="rounded-md border px-3 py-2 text-xs"
                    :class="
                        result.type === 'success'
                            ? 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300'
                            : 'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-800 dark:bg-rose-900/30 dark:text-rose-300'
                    "
                >
                    {{ result.message }}
                </div>

                <div class="flex items-center justify-end gap-2 pt-2">
                    <button
                        type="button"
                        class="rounded-md border border-slate-200 bg-white px-3 py-1.5 text-sm font-medium text-slate-600 transition hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200"
                        @click="emit('close')"
                    >
                        Close
                    </button>
                    <button
                        type="submit"
                        class="inline-flex items-center gap-2 rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-500 disabled:opacity-60"
                        :disabled="submitting || !to.trim()"
                    >
                        <svg v-if="submitting" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" class="opacity-25" />
                            <path d="M4 12a8 8 0 018-8" stroke="currentColor" stroke-width="3" class="opacity-75" />
                        </svg>
                        {{ submitting ? 'Sending…' : 'Send test' }}
                    </button>
                </div>
            </form>
        </div>
    </Modal>
</template>
