<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Board from '@/Components/Kanban/Board.vue';

const props = defineProps({
    board: { type: Object, required: true },
});

const initialBoard = computed(() => props.board.data);

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

    <AuthenticatedLayout>
        <div
            class="flex h-[calc(100vh-4rem)] flex-col"
            :style="bgStyle"
        >
            <div class="flex items-center justify-between gap-3 px-4 pt-3">
                <div>
                    <Link
                        :href="`/workspaces/${initialBoard.workspace_id}`"
                        class="text-xs font-medium text-slate-200 hover:text-white"
                    >
                        ← Back to workspace
                    </Link>
                    <h2 class="text-lg font-semibold leading-tight text-white drop-shadow">
                        {{ initialBoard.name }}
                    </h2>
                </div>
            </div>

            <Board :initial-board="initialBoard" />
        </div>
    </AuthenticatedLayout>
</template>
