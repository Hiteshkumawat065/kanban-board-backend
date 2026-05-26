<script setup>
import { computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Board from '@/Components/Kanban/Board.vue';

const props = defineProps({
    board: { type: Object, required: true },
});

const initialBoard = computed(() => props.board.data);

const workspaceId = computed(() => initialBoard.value.workspace_id);
const workspaceName = computed(() => initialBoard.value.workspace?.name ?? 'Workspace');

function goBack() {
    // Prefer the workspace's board listing the board belongs to; fall back to
    // the workspaces index if we don't know the workspace id for some reason.
    if (workspaceId.value) {
        router.visit(`/workspaces/${workspaceId.value}/boards`);
    } else {
        router.visit('/workspaces');
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
    <Head :title="initialBoard.name" />

    <!-- Hide the global sidebar on the task-list / board screen so the
         Kanban columns (Backlog, To Do, …) get the full viewport width. -->
    <AuthenticatedLayout :hide-sidebar="true">
        <div
            class="flex h-[calc(100vh-4rem)] flex-col"
            :style="bgStyle"
        >
            <!-- Board header: back button + breadcrumb navigation. -->
            <div class="flex items-center justify-between gap-3 border-b border-white/10 bg-slate-900/40 px-4 py-3 backdrop-blur-sm">
                <div class="flex min-w-0 items-center gap-3">
                    <button
                        type="button"
                        class="inline-flex h-9 items-center gap-1.5 rounded-md bg-white/10 px-3 text-sm font-medium text-white shadow-sm ring-1 ring-white/20 transition hover:bg-white/20"
                        title="Back to workspace"
                        @click="goBack"
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                        </svg>
                        Back
                    </button>

                    <nav class="hidden min-w-0 items-center gap-1.5 text-xs text-slate-300 sm:flex" aria-label="Breadcrumb">
                        <Link href="/dashboard" class="hover:text-white">Dashboard</Link>
                        <span class="text-slate-500">/</span>
                        <Link href="/workspaces" class="hover:text-white">Workspaces</Link>
                        <span class="text-slate-500">/</span>
                        <Link
                            :href="`/workspaces/${workspaceId}/boards`"
                            class="max-w-[12rem] truncate hover:text-white"
                        >
                            {{ workspaceName }}
                        </Link>
                        <span class="text-slate-500">/</span>
                        <span class="max-w-[14rem] truncate font-medium text-white">
                            {{ initialBoard.name }}
                        </span>
                    </nav>
                </div>

                <h2 class="hidden truncate text-lg font-semibold text-white drop-shadow md:block">
                    {{ initialBoard.name }}
                </h2>
            </div>

            <Board :initial-board="initialBoard" />
        </div>
    </AuthenticatedLayout>
</template>
