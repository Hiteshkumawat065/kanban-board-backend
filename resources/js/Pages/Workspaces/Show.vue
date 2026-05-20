<script setup>
import { ref } from 'vue';
import { computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    workspace: { type: Object, required: true },
    boards: { type: Object, required: true },
});

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

// IT-professional themed background (circuit / code aesthetic) with a dark
// overlay so the lists and cards stay readable on top of it.
const bgStyle = computed(() => ({
    backgroundImage:
        "linear-gradient(rgba(15, 23, 42, 0.72), rgba(15, 23, 42, 0.72)), url('https://images.unsplash.com/photo-1518770660439-4636190af475?ixlib=rb-4.0.3&auto=format&fit=crop&w=1920&q=80')",
    backgroundSize: 'cover',
    backgroundPosition: 'center',
    backgroundRepeat: 'no-repeat',
}));
</script>

<template>
    <Head :title="workspace.data.name" />

    <AuthenticatedLayout >
        <template #header>
            <div class="flex items-center justify-between"  > 
                <div>
                    <Link href="/workspaces" class="text-sm text-indigo-600 hover:underline">
                        ← All workspaces
                    </Link>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                        {{ workspace.data.name }}
                    </h2>
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

        <div class="py-8 h-[calc(100vh-4rem)]" :style="bgStyle" >
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
                        class="group relative h-32 overflow-hidden rounded-lg shadow transition hover:shadow-md"
                        :style="{ backgroundColor: board.background_color || '#0079bf' }"
                    >
                        <!-- Edit / Delete icons (top-right) -->
                        <div
                            v-if="board.permissions?.update || board.permissions?.delete"
                            class="absolute right-2 top-2 z-10 flex items-center gap-1 opacity-0 transition group-hover:opacity-100 focus-within:opacity-100"
                        >
                            <button
                                v-if="board.permissions?.update"
                                type="button"
                                title="Edit board"
                                class="rounded-md bg-white/90 p-1.5 text-slate-700 shadow-sm transition hover:bg-white"
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
                                class="rounded-md bg-white/90 p-1.5 text-rose-600 shadow-sm transition hover:bg-rose-50 hover:text-rose-700 disabled:opacity-50"
                                @click.stop.prevent="destroy(board)"
                            >
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3" />
                                </svg>
                            </button>
                        </div>

                        <Link
                            :href="`/boards/${board.id}`"
                            class="block h-full w-full p-4 text-white"
                        >
                            <h3 class="pr-16 text-lg font-semibold">
                                {{ board.name }}
                            </h3>
                            <p v-if="board.description" class="mt-1 line-clamp-2 pr-16 text-sm opacity-90">
                                {{ board.description }}
                            </p>
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
