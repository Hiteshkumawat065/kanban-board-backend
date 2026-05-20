<script setup>
import { computed, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';

const props = defineProps({
    card: { type: Object, default: null },
    board: { type: Object, default: null },
    lists: { type: Array, default: () => [] },
});

const emit = defineEmits([
    'close',
    'update',
    'delete',
    'assign',
    'unassign',
    'approve-uat',
    'request-rework',
]);

const page = usePage();
const authUser = computed(() => page.props.auth?.user ?? null);

function initialsOf(name) {
    if (!name) return '?';
    return name
        .split(/\s+/)
        .filter(Boolean)
        .map((w) => w[0])
        .slice(0, 2)
        .join('')
        .toUpperCase();
}

const AVATAR_COLORS = [
    '#2563eb',
    '#9333ea',
    '#0891b2',
    '#16a34a',
    '#ea580c',
    '#db2777',
    '#475569',
    '#b45309',
];
function colorFor(id) {
    const n = Number(id) || 0;
    return AVATAR_COLORS[n % AVATAR_COLORS.length];
}

const title = ref('');
const description = ref('');
const dueDate = ref('');
const priority = ref('medium');

watch(
    () => props.card,
    (c) => {
        title.value = c?.title ?? '';
        description.value = c?.description ?? '';
        dueDate.value = c?.due_date ? c.due_date.slice(0, 10) : '';
        priority.value = c?.priority ?? 'medium';
    },
    { immediate: true },
);

const currentList = computed(() => {
    if (!props.card) return null;
    return props.lists.find((l) => l.id === props.card.list_id) ?? null;
});

const isInUat = computed(() => currentList.value?.stage === 'uat');

const assignees = computed(() => props.card?.assignees ?? []);

const assigneeIds = computed(() => new Set(assignees.value.map((u) => u.id)));

const boardMembers = computed(() => props.board?.members ?? []);

const availableMembers = computed(() =>
    boardMembers.value.filter((u) => !assigneeIds.value.has(u.id)),
);

// Can the current user act as mentor on this card?
// Yes if any assignee has mentor_id === auth user's id.
const canApproveUat = computed(() => {
    if (!authUser.value) return false;
    return assignees.value.some((u) => u.mentor_id === authUser.value.id);
});

const uatBadge = computed(() => {
    switch (props.card?.uat_status) {
        case 'pending':
            return { label: 'UAT — awaiting mentor', class: 'bg-amber-100 text-amber-800' };
        case 'approved':
            return { label: 'UAT approved', class: 'bg-emerald-100 text-emerald-800' };
        case 'rework':
            return { label: 'Rework requested', class: 'bg-rose-100 text-rose-800' };
        default:
            return null;
    }
});

function save() {
    const payload = {
        title: title.value,
        description: description.value || null,
        due_date: dueDate.value || null,
        priority: priority.value,
    };
    emit('update', { cardId: props.card.id, payload });
}

function remove() {
    if (confirm('Delete this card?')) {
        emit('delete', props.card.id);
    }
}

const memberToAdd = ref('');

function addAssignee() {
    const userId = Number(memberToAdd.value);
    if (!userId) return;
    emit('assign', { cardId: props.card.id, userId });
    memberToAdd.value = '';
}

function removeAssignee(userId) {
    emit('unassign', { cardId: props.card.id, userId });
}

const reworkReason = ref('');
const showReworkForm = ref(false);

function approve() {
    if (confirm('Approve this UAT submission?')) {
        emit('approve-uat', props.card.id);
    }
}

function submitRework() {
    emit('request-rework', {
        cardId: props.card.id,
        reason: reworkReason.value || null,
    });
    showReworkForm.value = false;
    reworkReason.value = '';
}
</script>

<template>
    <div
        v-if="card"
        class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-slate-900/60 p-4 sm:p-8"
        @click.self="emit('close')"
    >
        <div class="w-full max-w-2xl rounded-lg bg-white shadow-xl dark:bg-slate-800">
            <header class="flex items-start justify-between gap-3 border-b border-slate-200 p-4 dark:border-slate-700">
                <div class="flex flex-1 flex-col gap-2">
                    <input
                        v-model="title"
                        type="text"
                        class="w-full rounded-md border-transparent bg-transparent text-lg font-semibold text-slate-900 focus:border-indigo-500 focus:ring-indigo-500 dark:text-slate-100"
                    />
                    <div class="flex flex-wrap items-center gap-2 text-xs">
                        <span
                            v-if="card.needs_rework"
                            class="inline-flex items-center gap-1 rounded bg-rose-600 px-2 py-0.5 font-semibold uppercase tracking-wide text-white"
                        >
                            REWORK
                        </span>
                        <span
                            v-if="uatBadge"
                            class="inline-flex items-center gap-1 rounded px-2 py-0.5 font-medium"
                            :class="uatBadge.class"
                        >
                            {{ uatBadge.label }}
                        </span>
                        <span v-if="currentList" class="text-slate-500 dark:text-slate-400">
                            in <span class="font-medium">{{ currentList.name }}</span>
                        </span>
                    </div>
                </div>
                <button
                    type="button"
                    class="rounded p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-700"
                    @click="emit('close')"
                >
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </header>

            <div class="space-y-4 p-4">
                <!-- Rework explainer banner -->
                <div
                    v-if="card.needs_rework"
                    class="rounded-md border border-rose-200 bg-rose-50 p-3 text-sm text-rose-800 dark:border-rose-900 dark:bg-rose-900/30 dark:text-rose-200"
                >
                    The mentor has requested rework on this task. It's been moved back to To Do
                    and flagged high-priority. Address the feedback and move it back through
                    In Progress &rarr; UAT for a fresh review.
                </div>

                <!-- Mentor UAT panel -->
                <div
                    v-if="isInUat && canApproveUat && card.uat_status === 'pending'"
                    class="rounded-md border border-amber-200 bg-amber-50 p-3 dark:border-amber-900 dark:bg-amber-900/30"
                >
                    <p class="text-sm font-semibold text-amber-900 dark:text-amber-200">
                        You are reviewing this task as the mentor.
                    </p>
                    <p class="mt-1 text-xs text-amber-800 dark:text-amber-300">
                        Approve to let the developer ship it, or request rework to send it
                        back to To Do (it'll be marked high-priority with a red REWORK badge).
                    </p>

                    <div v-if="!showReworkForm" class="mt-3 flex flex-wrap gap-2">
                        <button
                            type="button"
                            class="rounded-md bg-emerald-600 px-3 py-1.5 text-sm font-medium text-white shadow-sm transition hover:bg-emerald-700"
                            @click="approve"
                        >
                            Approve UAT
                        </button>
                        <button
                            type="button"
                            class="rounded-md bg-rose-600 px-3 py-1.5 text-sm font-medium text-white shadow-sm transition hover:bg-rose-700"
                            @click="showReworkForm = true"
                        >
                            Request rework
                        </button>
                    </div>

                    <div v-else class="mt-3 space-y-2">
                        <label class="block text-xs font-semibold uppercase tracking-wide text-amber-800 dark:text-amber-300">
                            Reason (optional)
                        </label>
                        <textarea
                            v-model="reworkReason"
                            rows="2"
                            placeholder="What needs to be fixed?"
                            class="w-full rounded-md border-amber-300 bg-white text-sm shadow-sm focus:border-rose-500 focus:ring-rose-500 dark:border-amber-700 dark:bg-slate-800 dark:text-slate-100"
                        />
                        <div class="flex gap-2">
                            <button
                                type="button"
                                class="rounded-md bg-rose-600 px-3 py-1.5 text-sm font-medium text-white shadow-sm transition hover:bg-rose-700"
                                @click="submitRework"
                            >
                                Send back for rework
                            </button>
                            <button
                                type="button"
                                class="rounded-md px-3 py-1.5 text-sm font-medium text-slate-600 transition hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-700"
                                @click="showReworkForm = false"
                            >
                                Cancel
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Approved-in-UAT helper -->
                <div
                    v-else-if="isInUat && card.uat_status === 'approved'"
                    class="rounded-md border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-900/30 dark:text-emerald-200"
                >
                    UAT approved. You can now drag this card into the Completed list to mark it done.
                </div>

                <!-- Assignees -->
                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                        Assignees
                    </label>
                    <div v-if="assignees.length" class="mb-2 flex flex-wrap gap-2">
                        <span
                            v-for="user in assignees"
                            :key="user.id"
                            class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 py-1 pl-1 pr-2 text-xs text-slate-700 dark:bg-slate-700 dark:text-slate-200"
                        >
                            <span
                                class="inline-flex h-5 w-5 items-center justify-center rounded-full text-[9px] font-semibold text-white"
                                :style="{ backgroundColor: colorFor(user.id) }"
                            >
                                {{ initialsOf(user.name) }}
                            </span>
                            <span>{{ user.name }}</span>
                            <button
                                type="button"
                                class="ml-1 text-slate-400 transition hover:text-rose-600"
                                :title="`Remove ${user.name}`"
                                @click="removeAssignee(user.id)"
                            >
                                <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </span>
                    </div>
                    <p v-else class="mb-2 text-xs italic text-slate-400">No one assigned yet.</p>

                    <div v-if="availableMembers.length" class="flex items-center gap-2">
                        <select
                            v-model="memberToAdd"
                            class="rounded-md border-slate-300 bg-white text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100"
                        >
                            <option value="">Select a member…</option>
                            <option v-for="user in availableMembers" :key="user.id" :value="user.id">
                                {{ user.name }}
                            </option>
                        </select>
                        <button
                            type="button"
                            class="rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white shadow-sm transition hover:bg-indigo-700 disabled:opacity-50"
                            :disabled="!memberToAdd"
                            @click="addAssignee"
                        >
                            Assign
                        </button>
                    </div>
                </div>

                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                        Description
                    </label>
                    <textarea
                        v-model="description"
                        rows="6"
                        placeholder="Add a more detailed description…"
                        class="w-full rounded-md border-slate-300 bg-white text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100"
                    />
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            Due date
                        </label>
                        <input
                            v-model="dueDate"
                            type="date"
                            class="rounded-md border-slate-300 bg-white text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100"
                        />
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            Priority
                        </label>
                        <select
                            v-model="priority"
                            class="rounded-md border-slate-300 bg-white text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100"
                        >
                            <option value="low">Low</option>
                            <option value="medium">Medium</option>
                            <option value="high">High</option>
                        </select>
                    </div>
                </div>
            </div>

            <footer class="flex items-center justify-between gap-2 border-t border-slate-200 p-4 dark:border-slate-700">
                <button
                    type="button"
                    class="rounded-md px-3 py-1.5 text-sm font-medium text-rose-600 transition hover:bg-rose-50 dark:hover:bg-rose-900/30"
                    @click="remove"
                >
                    Delete card
                </button>
                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        class="rounded-md px-3 py-1.5 text-sm font-medium text-slate-700 transition hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-700"
                        @click="emit('close')"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        class="rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white shadow-sm transition hover:bg-indigo-700"
                        @click="save"
                    >
                        Save
                    </button>
                </div>
            </footer>
        </div>
    </div>
</template>
