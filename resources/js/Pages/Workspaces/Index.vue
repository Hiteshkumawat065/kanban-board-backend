<script setup>
import { ref } from 'vue';
import { computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';

const props = defineProps({
    workspaces: { type: Object, required: true },
});

function goBack() {
    // The workspace listing sits directly under the dashboard, so "back"
    // always returns the user there.
    router.visit('/dashboard');
}

// --- New-workspace form state -------------------------------------
const showForm = ref(false);
const name = ref('');
const description = ref('');
const submitting = ref(false);
const error = ref(null);

// --- Edit-workspace state (shared modal-style form above the grid)
const editingId = ref(null);
const editName = ref('');
const editDescription = ref('');
const editSubmitting = ref(false);
const editError = ref(null);

// --- Delete state -------------------------------------------------
const deletingId = ref(null);
const actionError = ref(null);

// --- View-details modal state ------------------------------------
// `viewing` holds the workspace summary from the listing payload so the
// modal can render immediately (name, counts, etc.) while `viewDetails`
// is hydrated from GET /api/v1/workspaces/{id} for owner + team data.
const viewing = ref(null);
const viewDetails = ref(null);
const viewLoading = ref(false);
const viewError = ref(null);

// Pretty labels for the team_counts buckets returned by the API. Keys
// must match the JobTitle enum values on the backend.
const jobTitleLabels = {
    developer: 'Developers',
    designer: 'Designers',
    qa: 'QAs',
    manager: 'Managers',
    other: 'Other',
};

const roleLabels = {
    owner: 'Owner',
    admin: 'Admin',
    member: 'Member',
};

// Only show buckets that actually have at least one member so the modal
// stays compact for small teams.
const visibleTeamCounts = computed(() => {
    const counts = viewDetails.value?.team_counts ?? {};
    return Object.entries(counts).filter(([, n]) => n > 0);
});

async function openView(ws) {
    viewing.value = ws;
    viewDetails.value = null;
    viewError.value = null;
    viewLoading.value = true;
    // Close any other open inline forms so the modal isn't competing.
    showForm.value = false;
    editingId.value = null;
    try {
        const { data } = await window.axios.get(`/api/v1/workspaces/${ws.id}`);
        viewDetails.value = data.data;
    } catch (e) {
        viewError.value =
            e?.response?.data?.message || 'Failed to load workspace details';
    } finally {
        viewLoading.value = false;
    }
}

function closeView() {
    viewing.value = null;
    viewDetails.value = null;
    viewError.value = null;
    viewLoading.value = false;
}

function formatDate(iso) {
    if (!iso) return '—';
    try {
        return new Date(iso).toLocaleDateString(undefined, {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
        });
    } catch {
        return iso;
    }
}

// Workspaces don't carry a stored color, so we derive a stable accent
// colour from the workspace id. Same palette feel as the board tiles
// (top accent strip) so the two listing screens look consistent.
const WORKSPACE_ACCENT_COLORS = [
    '#6366f1', // indigo
    '#0ea5e9', // sky
    '#10b981', // emerald
    '#f59e0b', // amber
    '#ef4444', // red
    '#ec4899', // pink
    '#8b5cf6', // violet
    '#14b8a6', // teal
];
function accentColorFor(id) {
    const n = Number(id) || 0;
    return WORKSPACE_ACCENT_COLORS[n % WORKSPACE_ACCENT_COLORS.length];
}

function openCreate() {
    // Opening the create modal cancels any edit currently in progress so
    // the user is never juggling two forms at once.
    editingId.value = null;
    name.value = '';
    description.value = '';
    error.value = null;
    showForm.value = true;
}

function closeCreate() {
    if (submitting.value) return;
    showForm.value = false;
    error.value = null;
}

async function create() {
    if (!name.value.trim() || submitting.value) return;
    submitting.value = true;
    error.value = null;
    try {
        const { data } = await window.axios.post('/api/v1/workspaces', {
            name: name.value.trim(),
            description: description.value || null,
        });
        showForm.value = false;
        router.visit(`/workspaces/${data.data.id}/boards`);
    } catch (e) {
        error.value =
            e?.response?.data?.message ||
            Object.values(e?.response?.data?.errors ?? {}).flat().join('\n') ||
            'Failed to create workspace';
    } finally {
        submitting.value = false;
    }
}

function openEdit(ws) {
    editingId.value = ws.id;
    editName.value = ws.name ?? '';
    editDescription.value = ws.description ?? '';
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
        await window.axios.put(`/api/v1/workspaces/${editingId.value}`, {
            name: editName.value.trim(),
            description: editDescription.value || null,
        });
        editingId.value = null;
        router.reload({ only: ['workspaces'] });
    } catch (e) {
        editError.value =
            e?.response?.data?.message ||
            Object.values(e?.response?.data?.errors ?? {}).flat().join('\n') ||
            'Failed to update workspace';
    } finally {
        editSubmitting.value = false;
    }
}

async function destroy(ws) {
    if (deletingId.value) return;
    const ok = window.confirm(
        `Delete workspace "${ws.name}"?\n\n` +
            'All boards, lists, and cards inside it will be removed. This cannot be undone.',
    );
    if (!ok) return;

    deletingId.value = ws.id;
    actionError.value = null;
    try {
        await window.axios.delete(`/api/v1/workspaces/${ws.id}`);
        router.reload({ only: ['workspaces'] });
    } catch (e) {
        actionError.value =
            e?.response?.data?.message ||
            `Failed to delete "${ws.name}"`;
    } finally {
        deletingId.value = null;
    }
}
 
</script>

<template>
    <Head title="Workspaces" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex min-w-0 items-center gap-3">
                    <!-- Back button -> dashboard -->
                    <button
                        type="button"
                        class="inline-flex h-9 items-center gap-1.5 rounded-md border border-slate-200 bg-white px-3 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200 dark:hover:bg-slate-600"
                        title="Back to dashboard"
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
                            <span class="font-medium text-slate-700 dark:text-slate-200">
                                Workspaces
                            </span>
                        </nav>
                        <h2 class="mt-0.5 truncate text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                            Your Workspaces
                        </h2>
                    </div>
                </div>

                <button
                    type="button"
                    class="rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white shadow-sm transition hover:bg-indigo-700"
                    @click="openCreate"
                >
                    + New workspace
                </button>
            </div>
        </template>

        <div class="py-8"   > 
            <div class="mx-auto px-4 sm:px-6 lg:px-8">
                <div
                    v-if="actionError"
                    class="mb-4 rounded-md bg-rose-50 px-4 py-2 text-sm text-rose-700 dark:bg-rose-900/30 dark:text-rose-300"
                >
                    {{ actionError }}
                </div>

                <form
                    v-if="editingId"
                    class="mb-6 space-y-3 rounded-lg bg-white p-4 shadow dark:bg-gray-800"
                    @submit.prevent="saveEdit"
                >
                    <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-200">
                        Edit workspace
                    </h3>
                    <input
                        v-model="editName"
                        type="text"
                        required
                        placeholder="Workspace name"
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

                <div v-if="workspaces.data.length === 0" class="rounded-lg bg-white p-8 text-center shadow dark:bg-gray-800">
                    <p class="text-gray-600 dark:text-gray-400">
                        No workspaces yet. Create your first one!
                    </p>
                </div>
 
                <div v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div
                        v-for="ws in workspaces.data"
                        :key="ws.id"
                        class="group relative flex flex-col overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm transition hover:shadow-md dark:border-slate-700 dark:bg-slate-800"
                    >
                        <!-- Thin colored accent strip — mirrors the board
                             tile design. Workspaces don't store a color, so
                             we derive one from the id for visual variety. -->
                        <span
                            class="h-1.5 w-full shrink-0"
                            :style="{ backgroundColor: accentColorFor(ws.id) }"
                        />

                        <!-- View / Edit / Delete icons (top-right). The
                             container is always rendered because "view
                             details" is available to every member; the Edit
                             and Delete buttons are still gated by role.
                             Rendered above the Link so they receive the
                             click first. -->
                        <div
                            class="absolute right-2 top-3 z-10 flex items-center gap-1 opacity-0 transition group-hover:opacity-100 focus-within:opacity-100"
                        >
                            <button
                                type="button"
                                title="View details"
                                class="rounded-md bg-white/90 p-1.5 text-slate-600 shadow-sm ring-1 ring-slate-200 transition hover:bg-slate-100 hover:text-slate-900 dark:bg-slate-700/90 dark:text-slate-200 dark:ring-slate-600 dark:hover:bg-slate-600"
                                @click.stop.prevent="openView(ws)"
                            >
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12z" />
                                    <circle cx="12" cy="12" r="3" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" />
                                </svg>
                            </button>
                            <button
                                v-if="ws.permissions?.update"
                                type="button"
                                title="Edit workspace"
                                class="rounded-md bg-white/90 p-1.5 text-slate-600 shadow-sm ring-1 ring-slate-200 transition hover:bg-slate-100 hover:text-slate-900 dark:bg-slate-700/90 dark:text-slate-200 dark:ring-slate-600 dark:hover:bg-slate-600"
                                @click.stop.prevent="openEdit(ws)"
                            >
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                </svg>
                            </button>
                            <button
                                v-if="ws.permissions?.delete"
                                type="button"
                                title="Delete workspace"
                                :disabled="deletingId === ws.id"
                                class="rounded-md bg-white/90 p-1.5 text-rose-600 shadow-sm ring-1 ring-slate-200 transition hover:bg-rose-50 hover:text-rose-700 disabled:opacity-50 dark:bg-slate-700/90 dark:text-rose-300 dark:ring-slate-600 dark:hover:bg-rose-900/30"
                                @click.stop.prevent="destroy(ws)"
                            >
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3" />
                                </svg>
                            </button>
                        </div>

                        <Link
                            :href="`/workspaces/${ws.id}/boards`"
                            class="block flex-1 px-5 pb-4 pt-4"
                        >
                            <h3 class="pr-16 text-lg font-semibold text-gray-900 dark:text-gray-100">
                                {{ ws.name }}
                            </h3>
                            <p v-if="ws.description" class="mt-1 line-clamp-2 text-sm text-gray-600 dark:text-gray-400">
                                {{ ws.description }}
                            </p>
                        </Link>

                        <!-- Counts row — visually separated by a divider line
                             and styled like the board-tile team chips so the
                             two listing screens read the same way. -->
                        <div class="mt-auto flex flex-wrap items-center gap-1.5 border-t border-slate-100 px-5 py-3 dark:border-slate-700">
                            <span
                                class="inline-flex items-center gap-1 rounded-full bg-indigo-50 px-2 py-0.5 text-[11px] font-medium text-indigo-700 dark:bg-indigo-500/15 dark:text-indigo-300"
                                :title="`${ws.boards_count ?? 0} boards`"
                            >
                                <span class="font-bold">{{ ws.boards_count ?? 0 }}</span>
                                Boards
                            </span>
                            <span
                                class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-medium text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300"
                                :title="`${ws.members_count ?? 0} members`"
                            >
                                <span class="font-bold">{{ ws.members_count ?? 0 }}</span>
                                Members
                            </span>
                        </div>
                    </div>
                </div>


                
            </div>
        </div>

        <!-- Create-workspace modal. Triggered by the header "+ New
             workspace" button. Keeps the create form out of the page
             flow so the listing stays clean. -->
        <Modal :show="showForm" max-width="md" :closeable="false" @close="closeCreate">
            <form class="p-6" @submit.prevent="create">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                            New workspace
                        </h3>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                            Workspaces group boards and members together.
                        </p>
                    </div>
                    <button
                        type="button"
                        class="rounded-md p-1 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-700 dark:hover:text-slate-200"
                        title="Close"
                        @click="closeCreate"
                    >
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="mt-5 space-y-4">
                    <div>
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-300">
                            Name <span class="text-rose-500">*</span>
                        </label>
                        <input
                            v-model="name"
                            type="text"
                            required
                            autofocus
                            placeholder="e.g. Marketing"
                            class="mt-1 w-full rounded-md border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100"
                        />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-300">
                            Description
                        </label>
                        <textarea
                            v-model="description"
                            rows="3"
                            placeholder="What is this workspace for? (optional)"
                            class="mt-1 w-full rounded-md border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100"
                        />
                    </div>
                    <div v-if="error" class="rounded-md bg-rose-50 px-3 py-2 text-sm text-rose-700 dark:bg-rose-900/30 dark:text-rose-300">
                        {{ error }}
                    </div>
                </div>

                <div class="mt-6 flex items-center justify-end gap-2">
                    <button
                        type="button"
                        :disabled="submitting"
                        class="rounded-md px-3 py-2 text-sm font-medium text-slate-600 transition hover:bg-slate-100 disabled:opacity-50 dark:text-slate-300 dark:hover:bg-slate-700"
                        @click="closeCreate"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        :disabled="submitting || !name.trim()"
                        class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-indigo-700 disabled:opacity-50"
                    >
                        {{ submitting ? 'Creating…' : 'Create workspace' }}
                    </button>
                </div>
            </form>
        </Modal>

        <!-- View-details modal. Opens immediately with summary data from
             the listing payload, then hydrates owner + team composition
             from GET /api/v1/workspaces/{id}. -->
        <Modal :show="viewing !== null" max-width="lg" :closeable="false" @close="closeView">
            <div v-if="viewing" class="p-6">
                <div class="flex items-start gap-4">
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center overflow-hidden rounded-md bg-indigo-100 text-base font-semibold text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-200">
                        <img
                            v-if="viewing.avatar_url"
                            :src="viewing.avatar_url"
                            :alt="viewing.name"
                            class="h-full w-full object-cover"
                        />
                        <span v-else>{{ (viewing.name || '?').charAt(0).toUpperCase() }}</span>
                    </div>
                    <div class="min-w-0 flex-1">
                        <h3 class="truncate text-lg font-semibold text-gray-900 dark:text-gray-100">
                            {{ viewing.name }}
                        </h3>
                        <p v-if="viewing.slug" class="mt-0.5 truncate text-xs text-slate-500 dark:text-slate-400">
                            /{{ viewing.slug }}
                        </p>
                    </div>
                    <button
                        type="button"
                        class="rounded-md p-1 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-700 dark:hover:text-slate-200"
                        title="Close"
                        @click="closeView"
                    >
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <p
                    v-if="viewing.description"
                    class="mt-3 whitespace-pre-line text-sm text-slate-700 dark:text-slate-300"
                >
                    {{ viewing.description }}
                </p>
                <p v-else class="mt-3 text-sm italic text-slate-400 dark:text-slate-500">
                    No description provided.
                </p>

                <!-- Quick stats from the listing payload — always available. -->
                <div class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-3">
                    <div class="rounded-md bg-slate-50 p-3 dark:bg-slate-700/40">
                        <div class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            Boards
                        </div>
                        <div class="mt-1 text-lg font-semibold text-slate-800 dark:text-slate-100">
                            {{ viewing.boards_count ?? 0 }}
                        </div>
                    </div>
                    <div class="rounded-md bg-slate-50 p-3 dark:bg-slate-700/40">
                        <div class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            Members
                        </div>
                        <div class="mt-1 text-lg font-semibold text-slate-800 dark:text-slate-100">
                            {{ viewing.members_count ?? 0 }}
                        </div>
                    </div>
                    <div class="rounded-md bg-slate-50 p-3 dark:bg-slate-700/40">
                        <div class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            Created
                        </div>
                        <div class="mt-1 text-sm font-medium text-slate-800 dark:text-slate-100">
                            {{ formatDate(viewDetails?.created_at ?? viewing.created_at) }}
                        </div>
                    </div>
                </div>

                <!-- Hydrated details (owner / role / team breakdown). -->
                <div v-if="viewLoading" class="mt-5 text-sm text-slate-500 dark:text-slate-400">
                    Loading details…
                </div>
                <div
                    v-else-if="viewError"
                    class="mt-5 rounded-md bg-rose-50 px-3 py-2 text-sm text-rose-700 dark:bg-rose-900/30 dark:text-rose-300"
                >
                    {{ viewError }}
                </div>
                <div v-else-if="viewDetails" class="mt-5 space-y-4">
                    <div v-if="viewDetails.owner" class="flex items-center gap-3 rounded-md border border-slate-200 p-3 dark:border-slate-700">
                        <img
                            v-if="viewDetails.owner.avatar_url"
                            :src="viewDetails.owner.avatar_url"
                            :alt="viewDetails.owner.name"
                            class="h-9 w-9 rounded-full object-cover"
                        />
                        <div class="min-w-0 flex-1">
                            <div class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">
                                Owner
                            </div>
                            <div class="truncate text-sm font-medium text-slate-800 dark:text-slate-100">
                                {{ viewDetails.owner.name }}
                            </div>
                            <div class="truncate text-xs text-slate-500 dark:text-slate-400">
                                {{ viewDetails.owner.email }}
                            </div>
                        </div>
                    </div>

                    <div v-if="viewDetails.role" class="flex items-center justify-between rounded-md border border-slate-200 p-3 dark:border-slate-700">
                        <span class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            Your role
                        </span>
                        <span class="inline-flex items-center rounded-full bg-indigo-50 px-2.5 py-0.5 text-xs font-medium text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-200">
                            {{ roleLabels[viewDetails.role] ?? viewDetails.role }}
                        </span>
                    </div>

                    <div v-if="visibleTeamCounts.length">
                        <div class="mb-2 text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            Team composition
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <span
                                v-for="[key, count] in visibleTeamCounts"
                                :key="key"
                                class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700 dark:bg-slate-700 dark:text-slate-200"
                            >
                                {{ jobTitleLabels[key] ?? key }}
                                <span class="rounded-full bg-white px-1.5 py-0.5 text-[10px] font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                                    {{ count }}
                                </span>
                            </span>
                        </div>
                    </div>
                </div>

                <div class="mt-6 flex items-center justify-end gap-2">
                    <Link
                        :href="`/workspaces/${viewing.id}/boards`"
                        class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-indigo-700"
                    >
                        Open boards
                    </Link>
                    <button
                        type="button"
                        class="rounded-md px-3 py-2 text-sm font-medium text-slate-600 transition hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-700"
                        @click="closeView"
                    >
                        Close
                    </button>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
