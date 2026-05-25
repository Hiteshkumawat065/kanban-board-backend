<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';

// =============================================================================
//  Board listing page — lists every active board inside a single workspace.
//  Lives at /workspaces/{workspace}/boards (route name: boards.index).
//  Clicking a board navigates to the Kanban view at /boards/{id}.
// =============================================================================

const props = defineProps({
    workspace: { type: Object, required: true },
    boards: { type: Object, required: true },
});

function goBack() {
    // Boards always belong to a workspace; the natural "back" target is the
    // workspace listing where the user picked this workspace from.
    router.visit('/workspaces');
}

// -----------------------------------------------------------------------------
// Team composition for the board tiles.
//   - `teamCounts` is a {role: count} map exposed by WorkspaceResource.
//   - `membersByRole` re-buckets the flat `members` array into the same
//     groups, used by the "View Details" modal.
// -----------------------------------------------------------------------------
const teamCounts = computed(() => props.workspace.data.team_counts ?? {});
const members = computed(() => props.workspace.data.members?.data ?? []);

const TEAM_GROUPS = [
    { key: 'developer', label: 'Developers', short: 'Devs',     color: 'bg-indigo-50 text-indigo-700 dark:bg-indigo-500/15 dark:text-indigo-300' },
    { key: 'designer',  label: 'Designers',  short: 'Designers', color: 'bg-fuchsia-50 text-fuchsia-700 dark:bg-fuchsia-500/15 dark:text-fuchsia-300' },
    { key: 'qa',        label: 'QAs',        short: 'QAs',      color: 'bg-amber-50 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300' },
    { key: 'manager',   label: 'Managers',   short: 'Managers', color: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300' },
];

const membersByRole = computed(() => {
    const buckets = Object.fromEntries(TEAM_GROUPS.map((g) => [g.key, []]));
    buckets.other = [];
    for (const m of members.value) {
        const key = m.job_title || 'other';
        if (!buckets[key]) buckets[key] = [];
        buckets[key].push(m);
    }
    return buckets;
});

// -----------------------------------------------------------------------------
// "View Details" modal — opened by clicking the button on a board tile.
//   - The modal data is workspace-wide (all members), so we keep a single
//     `showTeamModal` flag regardless of which tile triggered it.
//   - We still remember the originating board name so the modal title can
//     give the user the right context.
// -----------------------------------------------------------------------------
const showTeamModal = ref(false);
const modalBoardName = ref('');

function openTeamModal(board) {
    modalBoardName.value = board.name;
    showTeamModal.value = true;
}

function closeTeamModal() {
    showTeamModal.value = false;
}

function initials(name) {
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
    '#2563eb', '#e07820', '#0891b2', '#16a34a',
    '#ea580c', '#db2777', '#475569', '#b45309',
];
function colorFor(id) {
    const n = Number(id) || 0;
    return AVATAR_COLORS[n % AVATAR_COLORS.length];
}

// --- New-board form state -----------------------------------------
const showForm = ref(false);
const name = ref('');
const submitting = ref(false);
const error = ref(null);

// --- Edit-board form state (shared form above the grid) -----------
const editingId = ref(null);
const editName = ref('');
const editDescription = ref('');
const editSubmitting = ref(false);
const editError = ref(null);

// --- Delete state -------------------------------------------------
const deletingId = ref(null);
const actionError = ref(null);

async function create() {
    if (!name.value.trim() || submitting.value) return;
    submitting.value = true;
    error.value = null;
    try {
        const { data } = await window.axios.post(
            `/api/v1/workspaces/${props.workspace.data.id}/boards`,
            { name: name.value.trim() },
        );
        router.visit(`/boards/${data.data.id}`);
    } catch (e) {
        error.value =
            e?.response?.data?.message ||
            Object.values(e?.response?.data?.errors ?? {}).flat().join('\n') ||
            'Failed to create board';
    } finally {
        submitting.value = false;
    }
}

function openEdit(board) {
    editingId.value = board.id;
    editName.value = board.name ?? '';
    editDescription.value = board.description ?? '';
    editError.value = null;
    showForm.value = false;
}

function cancelEdit() {
    editingId.value = null;
    editError.value = null;
}

async function saveEdit() {
    if (!editName.value.trim() || editSubmitting.value) return;
    editSubmitting.value = true;
    editError.value = null;
    try {
        await window.axios.put(`/api/v1/boards/${editingId.value}`, {
            name: editName.value.trim(),
            description: editDescription.value || null,
        });
        editingId.value = null;
        router.reload({ only: ['boards'] });
    } catch (e) {
        editError.value =
            e?.response?.data?.message ||
            Object.values(e?.response?.data?.errors ?? {}).flat().join('\n') ||
            'Failed to update board';
    } finally {
        editSubmitting.value = false;
    }
}

async function destroy(board) {
    if (deletingId.value) return;
    const ok = window.confirm(
        `Delete board "${board.name}"?\n\n` +
            'All lists and cards inside it will be removed. This cannot be undone.',
    );
    if (!ok) return;

    deletingId.value = board.id;
    actionError.value = null;
    try {
        await window.axios.delete(`/api/v1/boards/${board.id}`);
        router.reload({ only: ['boards'] });
    } catch (e) {
        actionError.value =
            e?.response?.data?.message ||
            `Failed to delete "${board.name}"`;
    } finally {
        deletingId.value = null;
    }
}
</script>

<template>
    <Head :title="workspace.data.name" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex min-w-0 items-center gap-3">
                    <!-- Back button -> workspace listing -->
                    <button
                        type="button"
                        class="inline-flex h-9 items-center gap-1.5 rounded-md border border-slate-200 bg-white px-3 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200 dark:hover:bg-slate-600"
                        title="Back to workspaces"
                        @click="goBack"
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                        </svg>
                        Back
                    </button>

                    <div class="min-w-0">
                        <!-- Breadcrumb navigation -->
                        <nav class="flex items-center gap-1.5 text-xs text-slate-500 dark:text-slate-400" aria-label="Breadcrumb">
                            <Link href="/dashboard" class="hover:text-slate-700 dark:hover:text-slate-200">
                                Dashboard
                            </Link>
                            <span class="text-slate-400 dark:text-slate-500">/</span>
                            <Link href="/workspaces" class="hover:text-slate-700 dark:hover:text-slate-200">
                                Workspaces
                            </Link>
                            <span class="text-slate-400 dark:text-slate-500">/</span>
                            <span class="max-w-[14rem] truncate font-medium text-slate-700 dark:text-slate-200">
                                {{ workspace.data.name }}
                            </span>
                        </nav>
                        <h2 class="mt-0.5 truncate text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                            {{ workspace.data.name }}
                        </h2>
                    </div>
                </div>

                <button
                    type="button"
                    class="rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white shadow-sm transition hover:bg-indigo-700"
                    @click="showForm = !showForm; editingId = null"
                >
                    {{ showForm ? 'Cancel' : '+ New board' }}
                </button>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto px-4 sm:px-6 lg:px-8">
                <div
                    v-if="actionError"
                    class="mb-4 rounded-md bg-rose-50 px-4 py-2 text-sm text-rose-700 dark:bg-rose-900/30 dark:text-rose-300"
                >
                    {{ actionError }}
                </div>

                <form
                    v-if="showForm"
                    class="mb-6 flex flex-wrap items-center gap-3 rounded-lg bg-white p-4 shadow dark:bg-gray-800"
                    @submit.prevent="create"
                >
                    <input
                        v-model="name"
                        type="text"
                        required
                        placeholder="Board name"
                        class="flex-1 rounded-md border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100"
                    />
                    <button
                        type="submit"
                        :disabled="submitting"
                        class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-indigo-700 disabled:opacity-50"
                    >
                        Create
                    </button>
                    <p v-if="error" class="w-full text-sm text-rose-600">{{ error }}</p>
                </form>

                <form
                    v-if="editingId"
                    class="mb-6 space-y-3 rounded-lg bg-white p-4 shadow dark:bg-gray-800"
                    @submit.prevent="saveEdit"
                >
                    <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-200">
                        Edit board
                    </h3>
                    <input
                        v-model="editName"
                        type="text"
                        required
                        placeholder="Board name"
                        class="w-full rounded-md border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100"
                    />
                    <textarea
                        v-model="editDescription"
                        rows="2"
                        placeholder="Description (optional)"
                        class="w-full rounded-md border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100"
                    />
                    <div v-if="editError" class="text-sm text-rose-600">{{ editError }}</div>
                    <div class="flex items-center gap-2">
                        <button
                            type="submit"
                            :disabled="editSubmitting"
                            class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-indigo-700 disabled:opacity-50"
                        >
                            Save
                        </button>
                        <button
                            type="button"
                            class="rounded-md px-3 py-2 text-sm font-medium text-slate-600 transition hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-700"
                            @click="cancelEdit"
                        >
                            Cancel
                        </button>
                    </div>
                </form>

                <div v-if="boards.data.length === 0" class="rounded-lg bg-white p-8 text-center shadow dark:bg-gray-800">
                    <p class="text-gray-600 dark:text-gray-400">
                        No boards yet. Create your first board to get started!
                    </p>
                </div>

                <div v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div
                        v-for="board in boards.data"
                        :key="board.id"
                        class="group relative flex flex-col overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm transition hover:shadow-md dark:border-slate-700 dark:bg-slate-800"
                    >
                        <!-- Thin colored accent strip — keeps a visual hint of
                             the board's color without flooding the whole tile. -->
                        <span
                            class="h-1.5 w-full shrink-0"
                            :style="{ backgroundColor: board.background_color || '#6366f1' }"
                        />

                        <!-- Edit / Delete icons (top-right) -->
                        <div
                            v-if="board.permissions?.update || board.permissions?.delete"
                            class="absolute right-2 top-3 z-10 flex items-center gap-1 opacity-0 transition group-hover:opacity-100 focus-within:opacity-100"
                        >
                            <button
                                v-if="board.permissions?.update"
                                type="button"
                                title="Edit board"
                                class="rounded-md bg-white p-1.5 text-slate-600 shadow-sm ring-1 ring-slate-200 transition hover:bg-slate-50 hover:text-slate-900 dark:bg-slate-700 dark:text-slate-200 dark:ring-slate-600 dark:hover:bg-slate-600"
                                @click.stop.prevent="openEdit(board)"
                            >
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                </svg>
                            </button>
                            <button
                                v-if="board.permissions?.delete"
                                type="button"
                                title="Delete board"
                                :disabled="deletingId === board.id"
                                class="rounded-md bg-white p-1.5 text-rose-600 shadow-sm ring-1 ring-slate-200 transition hover:bg-rose-50 hover:text-rose-700 disabled:opacity-50 dark:bg-slate-700 dark:text-rose-300 dark:ring-slate-600 dark:hover:bg-rose-900/30"
                                @click.stop.prevent="destroy(board)"
                            >
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3" />
                                </svg>
                            </button>
                        </div>

                        <!-- Board title + description -->
                        <Link
                            :href="`/boards/${board.id}`"
                            class="block px-4 pt-4"
                        >
                            <h3 class="pr-16 text-base font-semibold text-slate-900 dark:text-slate-100">
                                {{ board.name }}
                            </h3>
                            <p
                                v-if="board.description"
                                class="mt-1 line-clamp-2 pr-16 text-sm text-slate-500 dark:text-slate-400"
                            >
                                {{ board.description }}
                            </p>
                        </Link>

                        <!-- Team counts row — bucketed by job_title -->
                        <div class="mt-3 flex flex-wrap items-center gap-1.5 px-4">
                            <span
                                v-for="g in TEAM_GROUPS"
                                :key="g.key"
                                :class="['inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-medium', g.color]"
                                :title="`${teamCounts[g.key] ?? 0} ${g.label}`"
                            >
                                <span class="font-bold">{{ teamCounts[g.key] ?? 0 }}</span>
                                {{ g.short }}
                            </span>
                        </div>

                        <!-- View Details footer -->
                        <div class="mt-3 flex items-center justify-between border-t border-slate-100 px-4 py-2 dark:border-slate-700">
                            <button
                                type="button"
                                class="inline-flex items-center gap-1 text-xs font-medium text-indigo-600 transition hover:text-indigo-700 hover:underline dark:text-indigo-400 dark:hover:text-indigo-300"
                                @click="openTeamModal(board)"
                            >
                                View Details
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                </svg>
                            </button>
                            <Link
                                :href="`/boards/${board.id}`"
                                class="text-xs font-medium text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200"
                            >
                                Open board →
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ===== Team Details modal =====
             Workspace-wide member breakdown, opened from the "View Details"
             button on any board tile. Members are grouped by job_title
             (Developers / Designers / QA / Manager) with the originating
             board's name in the header for context.
        -->
        <Modal :show="showTeamModal" max-width="2xl" @close="closeTeamModal">
            <div class="flex flex-col">
                <header class="flex items-start justify-between gap-3 border-b border-slate-100 px-5 py-4 dark:border-slate-700">
                    <div class="min-w-0">
                        <h3 class="text-base font-semibold text-slate-800 dark:text-slate-100">
                            Team — {{ modalBoardName }}
                        </h3>
                        <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                            Members of <span class="font-medium">{{ workspace.data.name }}</span>
                            grouped by role.
                        </p>
                    </div>
                    <button
                        type="button"
                        class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-md text-slate-500 hover:bg-slate-100 hover:text-slate-700 dark:text-slate-400 dark:hover:bg-slate-700 dark:hover:text-white"
                        aria-label="Close"
                        @click="closeTeamModal"
                    >
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </header>

                <div class="max-h-[60vh] overflow-y-auto px-5 py-4">
                    <div
                        v-if="members.length === 0"
                        class="py-8 text-center text-sm text-slate-500 dark:text-slate-400"
                    >
                        No members in this workspace yet.
                    </div>

                    <div v-else class="space-y-5">
                        <section v-for="g in TEAM_GROUPS" :key="g.key">
                            <div class="mb-2 flex items-center gap-2">
                                <h4 class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                    {{ g.label }}
                                </h4>
                                <span
                                    :class="['inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold', g.color]"
                                >
                                    {{ (membersByRole[g.key] || []).length }}
                                </span>
                            </div>

                            <ul
                                v-if="(membersByRole[g.key] || []).length"
                                class="grid grid-cols-1 gap-2 sm:grid-cols-2"
                            >
                                <li
                                    v-for="m in membersByRole[g.key]"
                                    :key="m.id"
                                    class="flex items-center gap-3 rounded-md border border-slate-100 bg-slate-50 px-3 py-2 dark:border-slate-700 dark:bg-slate-700/40"
                                >
                                    <span
                                        class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-xs font-bold text-white"
                                        :style="{ backgroundColor: colorFor(m.id) }"
                                    >
                                        {{ initials(m.name) }}
                                    </span>
                                    <div class="min-w-0 flex-1">
                                        <div class="truncate text-sm font-medium text-slate-800 dark:text-slate-100">
                                            {{ m.name }}
                                        </div>
                                        <div class="truncate text-xs text-slate-500 dark:text-slate-400">
                                            {{ m.email }}
                                        </div>
                                    </div>
                                </li>
                            </ul>
                            <p
                                v-else
                                class="text-xs italic text-slate-400 dark:text-slate-500"
                            >
                                No {{ g.label.toLowerCase() }} yet.
                            </p>
                        </section>

                        <!-- "Other" bucket — only rendered if any member has a
                             job_title we don't have a dedicated section for. -->
                        <section v-if="(membersByRole.other || []).length">
                            <div class="mb-2 flex items-center gap-2">
                                <h4 class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                    Other
                                </h4>
                                <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-600 dark:bg-slate-700 dark:text-slate-300">
                                    {{ membersByRole.other.length }}
                                </span>
                            </div>
                            <ul class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                                <li
                                    v-for="m in membersByRole.other"
                                    :key="m.id"
                                    class="flex items-center gap-3 rounded-md border border-slate-100 bg-slate-50 px-3 py-2 dark:border-slate-700 dark:bg-slate-700/40"
                                >
                                    <span
                                        class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-xs font-bold text-white"
                                        :style="{ backgroundColor: colorFor(m.id) }"
                                    >
                                        {{ initials(m.name) }}
                                    </span>
                                    <div class="min-w-0 flex-1">
                                        <div class="truncate text-sm font-medium text-slate-800 dark:text-slate-100">
                                            {{ m.name }}
                                        </div>
                                        <div class="truncate text-xs text-slate-500 dark:text-slate-400">
                                            {{ m.email }}
                                        </div>
                                    </div>
                                </li>
                            </ul>
                        </section>
                    </div>
                </div>

                <footer class="flex items-center justify-end border-t border-slate-100 px-5 py-3 dark:border-slate-700">
                    <button
                        type="button"
                        class="rounded-md border border-slate-200 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 transition hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200 dark:hover:bg-slate-600"
                        @click="closeTeamModal"
                    >
                        Close
                    </button>
                </footer>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
