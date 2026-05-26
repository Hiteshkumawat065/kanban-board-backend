<script setup>
import { computed, ref } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';

// =============================================================================
//  Dashboard landing page.
//  Renders panels populated by DashboardController@index:
//    1. Welcome banner with first-name greeting.
//    2. Stat row     — workspaces / boards / members totals.
//    3. Three balanced list panels:
//         · Recent Workspaces · Recent Boards · Recent Comments
//       Each panel uses a fixed min/max height with internal scroll so the
//       row always lines up regardless of how many rows each list contains.
//    4. Closed boards preview (5 rows) + a "View all" modal that lazy-loads
//       every archived board from GET /api/v1/boards/closed on first open.
// =============================================================================

const props = defineProps({
    stats: {
        type: Object,
        default: () => ({ boards: 0, workspaces: 0, members: 0 }),
    },
    recent_workspaces: { type: Array, default: () => [] },
    recent_boards: { type: Array, default: () => [] },
    recent_comments: { type: Array, default: () => [] },
    closed_boards_preview: { type: Array, default: () => [] },
    closed_boards_count: { type: Number, default: 0 },
});

const page = usePage();

const firstName = computed(() => {
    const full = page.props.auth?.user?.name ?? '';
    return full.split(/\s+/)[0] || 'there';
});

// --- Visual helpers ----------------------------------------------------------
// Deterministic per-user / per-workspace avatar colors — same palette as the
// Kanban assignee chips so a person/workspace stays the same color anywhere
// in the app.
const AVATAR_COLORS = [
    '#2563eb',
    '#e07820',
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

// -----------------------------------------------------------------------------
// "View all closed boards" modal.
//   - First open fires a GET to /api/v1/boards/closed; subsequent opens reuse
//     the cached result so the modal stays snappy.
//   - allClosedBoards is shaped like the preview rows so the same template
//     <li> can render either source.
// -----------------------------------------------------------------------------
const showClosedModal = ref(false);
const allClosedBoards = ref([]);
const closedLoading = ref(false);
const closedError = ref(null);
let closedFetched = false;

async function openClosedModal() {
    showClosedModal.value = true;
    if (closedFetched || closedLoading.value) return;

    closedLoading.value = true;
    closedError.value = null;
    try {
        const { data } = await window.axios.get('/api/v1/boards/closed');
        // The BoardResource collection nests rows under `.data`. Normalize
        // each entry into the same {id, name, ...} shape the panel uses.
        const rows = (data?.data ?? []).map((b) => ({
            id: b.id,
            name: b.name,
            background_color: b.background_color,
            workspace_name: b.workspace?.name ?? null,
            workspace_id: b.workspace_id,
            // BoardResource doesn't expose `archived_at`, fall back to
            // updated_at for the "closed Xh ago" hint when missing.
            archived_at: b.archived_at ?? b.updated_at ?? null,
        }));
        allClosedBoards.value = rows;
        closedFetched = true;
    } catch (e) {
        closedError.value =
            e?.response?.data?.message || 'Failed to load closed boards.';
    } finally {
        closedLoading.value = false;
    }
}

function closeClosedModal() {
    showClosedModal.value = false;
}

// Stat tile config — kept in script so the template stays declarative.
// Each tile renders as: [colored icon square] [LABEL / value / subtitle] [⋯ menu]
const statTiles = computed(() => [
    {
        key: 'workspaces',
        label: 'Workspaces',
        value: props.stats?.workspaces ?? 0,
        subtitle: 'Total workspaces',
        accent: 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-300',
        // Folder icon — "workspaces" reads as folders / containers rather
        // than the old check-circle which was meant for "tasks completed".
        path: 'M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z',
    },
    {
        key: 'boards',
        label: 'Boards',
        value: props.stats?.boards ?? 0,
        subtitle: 'Total boards',
        accent: 'bg-indigo-50 text-indigo-600 dark:bg-indigo-500/15 dark:text-indigo-300',
        path: 'M4 6h16M4 6a2 2 0 012-2h12a2 2 0 012 2M4 6v12a2 2 0 002 2h12a2 2 0 002-2V6M8 10v8m4-8v8m4-8v8',
    },
    {
        key: 'members',
        label: 'Members',
        value: props.stats?.members ?? 0,
        subtitle: 'Total members',
        accent: 'bg-amber-50 text-amber-600 dark:bg-amber-500/15 dark:text-amber-300',
        path: 'M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-5.13a4 4 0 11-8 0 4 4 0 018 0zm6 0a4 4 0 11-8 0 4 4 0 018 0z',
    },
]);
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <div class="px-4 py-6 sm:px-6 lg:px-8">
            <!-- ===== 1. Welcome banner ===== -->
            <section class="mb-6">
                <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">
                    Welcome back, {{ firstName }} <span aria-hidden="true">👋</span>
                </h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    Here's a quick overview of your workspaces, boards, and recent activity.
                </p>
            </section>

            <!-- ===== 2. Stat row =====
                 Three summary tiles: Workspaces / Boards / Members.
                 Layout per tile:
                   [colored icon square]   LABEL              [⋯ menu]
                                           {{ value }}
                                           subtitle
             -->
            <section class="mb-5 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div
                    v-for="tile in statTiles"
                    :key="tile.key"
                    class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition hover:shadow dark:border-slate-700 dark:bg-slate-800"
                >
                    <div class="flex items-start gap-4">
                        <div
                            :class="['flex h-12 w-12 shrink-0 items-center justify-center rounded-lg', tile.accent]"
                        >
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" :d="tile.path" />
                            </svg>
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                {{ tile.label }}
                            </div>
                            <div class="mt-1 text-3xl font-bold leading-none text-slate-900 dark:text-slate-100">
                                {{ tile.value }}
                            </div>
                            <div class="mt-2 text-xs text-slate-500 dark:text-slate-400">
                                {{ tile.subtitle }}
                            </div>
                        </div>
                        <button
                            type="button"
                            class="-mr-1 -mt-1 inline-flex h-7 w-7 items-center justify-center rounded-md text-slate-400 transition hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-700 dark:hover:text-slate-200"
                            aria-label="More options"
                        >
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h.01M12 12h.01M19 12h.01" />
                            </svg>
                        </button>
                    </div>
                </div>
            </section>

            <!-- ===== 2. Three balanced list panels =====
                 Recent Workspaces · Recent Boards · Recent Activity

                 Layout strategy:
                   - The grid forces all three columns to the same row, so
                     stretch-aligned children share the same outer height.
                   - Each panel is `flex flex-col` with a fixed `min-h` and
                     `max-h` so they never grow uneven; the inner <ul> uses
                     `overflow-y-auto flex-1` so long lists scroll inside
                     instead of stretching the panel.
             -->
            <section class="grid grid-cols-1 items-stretch gap-4 md:grid-cols-2 xl:grid-cols-3">
                <!-- Recent Workspaces -->
                <div class="flex min-h-[18rem] max-h-[26rem] flex-col rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800">
                    <div class="flex items-center justify-between border-b border-slate-100 px-5 py-3 dark:border-slate-700">
                        <h2 class="text-sm font-semibold text-slate-800 dark:text-slate-200">
                            Recent Workspaces
                        </h2>
                        <Link
                            href="/workspaces"
                            class="text-xs font-medium text-indigo-600 hover:text-indigo-700 hover:underline dark:text-indigo-400 dark:hover:text-indigo-300"
                        >
                            View all
                        </Link>
                    </div>

                    <ul v-if="recent_workspaces.length" class="flex-1 divide-y divide-slate-100 overflow-y-auto dark:divide-slate-700">
                        <li v-for="ws in recent_workspaces" :key="ws.id">
                            <Link
                                :href="`/workspaces/${ws.id}/boards`"
                                class="flex items-center gap-3 px-5 py-3 transition hover:bg-slate-50 dark:hover:bg-slate-700/50"
                            >
                                <span
                                    class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-xs font-bold text-white"
                                    :style="{ backgroundColor: colorFor(ws.id) }"
                                >
                                    {{ initials(ws.name) }}
                                </span>
                                <div class="min-w-0 flex-1">
                                    <div class="truncate text-sm font-medium text-slate-800 dark:text-slate-100">
                                        {{ ws.name }}
                                    </div>
                                    <div class="truncate text-xs text-slate-500 dark:text-slate-400">
                                        {{ ws.boards_count }} {{ ws.boards_count === 1 ? 'board' : 'boards' }}
                                        <span v-if="ws.updated_at"> · Updated {{ ws.updated_at }}</span>
                                    </div>
                                </div>
                                <svg class="h-4 w-4 shrink-0 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                </svg>
                            </Link>
                        </li>
                    </ul>

                    <div v-else class="flex flex-1 flex-col items-center justify-center px-5 py-8 text-center">
                        <div class="flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 text-slate-400 dark:bg-slate-700 dark:text-slate-500">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
                            </svg>
                        </div>
                        <p class="mt-3 text-sm font-medium text-slate-700 dark:text-slate-200">
                            No workspaces yet
                        </p>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                            Your workspaces will appear here.
                        </p>
                    </div>
                </div>

                <!-- Recent Boards -->
                <div class="flex min-h-[18rem] max-h-[26rem] flex-col rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800">
                    <div class="flex items-center justify-between border-b border-slate-100 px-5 py-3 dark:border-slate-700">
                        <h2 class="text-sm font-semibold text-slate-800 dark:text-slate-200">
                            Recent Boards
                        </h2>
                        <Link
                            href="/workspaces"
                            class="text-xs font-medium text-indigo-600 hover:text-indigo-700 hover:underline dark:text-indigo-400 dark:hover:text-indigo-300"
                        >
                            View all
                        </Link>
                    </div>

                    <ul v-if="recent_boards.length" class="flex-1 divide-y divide-slate-100 overflow-y-auto dark:divide-slate-700">
                        <li v-for="b in recent_boards" :key="b.id">
                            <Link
                                :href="`/boards/${b.id}`"
                                class="flex items-center gap-3 px-5 py-3 transition hover:bg-slate-50 dark:hover:bg-slate-700/50"
                            >
                                <span
                                    class="inline-block h-9 w-1.5 shrink-0 rounded"
                                    :style="{ backgroundColor: b.background_color || '#6366f1' }"
                                />
                                <div class="min-w-0 flex-1">
                                    <div class="truncate text-sm font-medium text-slate-800 dark:text-slate-100">
                                        {{ b.name }}
                                    </div>
                                    <div class="truncate text-xs text-slate-500 dark:text-slate-400">
                                        {{ b.workspace_name }}
                                        <span v-if="b.updated_at"> · Updated {{ b.updated_at }}</span>
                                    </div>
                                </div>
                                <svg class="h-4 w-4 shrink-0 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                </svg>
                            </Link>
                        </li>
                    </ul>

                    <div v-else class="flex flex-1 flex-col items-center justify-center px-5 py-8 text-center">
                        <div class="flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 text-slate-400 dark:bg-slate-700 dark:text-slate-500">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 6h16M4 6a2 2 0 012-2h12a2 2 0 012 2M4 6v12a2 2 0 002 2h12a2 2 0 002-2V6M8 10v8m4-8v8m4-8v8" />
                            </svg>
                        </div>
                        <p class="mt-3 text-sm font-medium text-slate-700 dark:text-slate-200">
                            No recent boards
                        </p>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                            Your most recent boards will appear here.
                        </p>
                    </div>
                </div>

                <!-- Recent Activity (comments + future activity feed) -->
                <div class="flex min-h-[18rem] max-h-[26rem] flex-col rounded-xl border border-slate-200 bg-white shadow-sm md:col-span-2 xl:col-span-1 dark:border-slate-700 dark:bg-slate-800">
                    <div class="flex items-center justify-between border-b border-slate-100 px-5 py-3 dark:border-slate-700">
                        <h2 class="text-sm font-semibold text-slate-800 dark:text-slate-200">
                            Recent Activity
                        </h2>
                        <Link
                            v-if="recent_comments.length"
                            href="/workspaces"
                            class="text-xs font-medium text-indigo-600 hover:text-indigo-700 hover:underline dark:text-indigo-400 dark:hover:text-indigo-300"
                        >
                            View all
                        </Link>
                    </div>

                    <ul v-if="recent_comments.length" class="flex-1 divide-y divide-slate-100 overflow-y-auto dark:divide-slate-700">
                        <li v-for="c in recent_comments" :key="c.id">
                            <Link
                                :href="c.board_id ? `/boards/${c.board_id}` : '#'"
                                class="flex items-start gap-3 px-5 py-3 transition hover:bg-slate-50 dark:hover:bg-slate-700/50"
                            >
                                <span
                                    class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-[10px] font-semibold text-white"
                                    :style="{ backgroundColor: colorFor(c.user_id) }"
                                    :title="c.user_name"
                                >
                                    {{ initials(c.user_name) }}
                                </span>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm text-slate-800 dark:text-slate-100">
                                        <span class="font-medium">{{ c.user_name }}</span>
                                        <span class="text-slate-500 dark:text-slate-400"> on </span>
                                        <span class="font-medium">{{ c.card_title || 'a card' }}</span>
                                    </p>
                                    <p class="mt-0.5 line-clamp-2 text-xs text-slate-600 dark:text-slate-300">
                                        {{ c.body }}
                                    </p>
                                    <p class="mt-1 truncate text-[11px] text-slate-400 dark:text-slate-500">
                                        <span v-if="c.board_name">{{ c.board_name }} · </span>{{ c.when }}
                                    </p>
                                </div>
                            </Link>
                        </li>
                    </ul>

                    <div v-else class="flex flex-1 flex-col items-center justify-center px-5 py-8 text-center">
                        <div class="flex h-12 w-12 items-center justify-center rounded-full bg-indigo-50 text-indigo-500 dark:bg-indigo-500/15 dark:text-indigo-300">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.86 9.86 0 01-4-.8L3 20l1.3-3.9A7.94 7.94 0 013 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                            </svg>
                        </div>
                        <p class="mt-3 text-sm font-medium text-slate-700 dark:text-slate-200">
                            No recent activity
                        </p>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                            Comments and updates will appear here.
                        </p>
                    </div>
                </div>
            </section>

            <!-- ===== 3. Closed Boards =====
                 Full-width row. Shows the first 5 archived boards inline;
                 the "View all" link opens a modal that lazy-fetches the
                 entire archive from /api/v1/boards/closed.
             -->
            <section class="mt-4">
                <div class="flex min-h-[14rem] max-h-[24rem] flex-col rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800">
                    <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-5 py-3 dark:border-slate-700">
                        <div class="flex items-center gap-2">
                            <h2 class="text-sm font-semibold text-slate-800 dark:text-slate-200">
                                Closed Boards
                            </h2>
                            <span
                                v-if="closed_boards_count"
                                class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-600 dark:bg-slate-700 dark:text-slate-300"
                            >
                                {{ closed_boards_count }}
                            </span>
                        </div>
                        <button
                            v-if="closed_boards_count > 0"
                            type="button"
                            class="text-xs font-medium text-indigo-600 hover:text-indigo-700 hover:underline dark:text-indigo-400 dark:hover:text-indigo-300"
                            @click="openClosedModal"
                        >
                            View all
                        </button>
                    </div>

                    <ul v-if="closed_boards_preview.length" class="flex-1 divide-y divide-slate-100 overflow-y-auto dark:divide-slate-700">
                        <li v-for="b in closed_boards_preview" :key="b.id">
                            <Link
                                :href="`/boards/${b.id}`"
                                class="flex items-center gap-3 px-5 py-3 transition hover:bg-slate-50 dark:hover:bg-slate-700/50"
                            >
                                <span
                                    class="inline-block h-9 w-1.5 shrink-0 rounded opacity-60"
                                    :style="{ backgroundColor: b.background_color || '#94a3b8' }"
                                />
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2">
                                        <div class="truncate text-sm font-medium text-slate-700 dark:text-slate-200">
                                            {{ b.name }}
                                        </div>
                                        <span class="inline-flex shrink-0 items-center rounded bg-rose-50 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wider text-rose-600 dark:bg-rose-500/15 dark:text-rose-300">
                                            Closed
                                        </span>
                                    </div>
                                    <div class="truncate text-xs text-slate-500 dark:text-slate-400">
                                        {{ b.workspace_name }}
                                        <span v-if="b.archived_at"> · closed {{ b.archived_at }}</span>
                                    </div>
                                </div>
                                <svg class="h-4 w-4 shrink-0 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                </svg>
                            </Link>
                        </li>
                    </ul>

                    <div v-else class="flex flex-1 flex-col items-center justify-center px-5 py-8 text-center">
                        <div class="flex h-12 w-12 items-center justify-center rounded-full bg-indigo-50 text-indigo-500 dark:bg-indigo-500/15 dark:text-indigo-300">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M20 7H4a1 1 0 00-1 1v3h18V8a1 1 0 00-1-1zM5 11v8a2 2 0 002 2h10a2 2 0 002-2v-8M10 15h4" />
                            </svg>
                        </div>
                        <p class="mt-3 text-sm font-medium text-slate-700 dark:text-slate-200">
                            No closed boards yet
                        </p>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                            Closed boards will be shown here.
                        </p>
                    </div>
                </div>
            </section>
        </div>

        <!-- ===== Closed Boards modal =====
             Lazy-loaded list of every archived board the user can see.
             Clicking a row navigates to that board's detail screen.
        -->
        <Modal :show="showClosedModal" max-width="2xl" @close="closeClosedModal">
            <div class="flex flex-col">
                <header class="flex items-center justify-between border-b border-slate-100 px-5 py-4 dark:border-slate-700">
                    <div>
                        <h3 class="text-base font-semibold text-slate-800 dark:text-slate-100">
                            All Closed Boards
                        </h3>
                        <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                            {{ closed_boards_count }} archived
                            {{ closed_boards_count === 1 ? 'board' : 'boards' }}
                            across your workspaces
                        </p>
                    </div>
                    <button
                        type="button"
                        class="inline-flex h-8 w-8 items-center justify-center rounded-md text-slate-500 hover:bg-slate-100 hover:text-slate-700 dark:text-slate-400 dark:hover:bg-slate-700 dark:hover:text-white"
                        aria-label="Close"
                        @click="closeClosedModal"
                    >
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </header>

                <div class="max-h-[60vh] min-h-[16rem] overflow-y-auto">
                    <div
                        v-if="closedLoading"
                        class="flex h-40 items-center justify-center text-sm text-slate-500 dark:text-slate-400"
                    >
                        Loading closed boards…
                    </div>

                    <div
                        v-else-if="closedError"
                        class="px-5 py-8 text-center text-sm text-rose-600 dark:text-rose-400"
                    >
                        {{ closedError }}
                    </div>

                    <ul
                        v-else-if="allClosedBoards.length"
                        class="divide-y divide-slate-100 dark:divide-slate-700"
                    >
                        <li v-for="b in allClosedBoards" :key="b.id">
                            <Link
                                :href="`/boards/${b.id}`"
                                class="flex items-center gap-3 px-5 py-3 transition hover:bg-slate-50 dark:hover:bg-slate-700/50"
                                @click="closeClosedModal"
                            >
                                <span
                                    class="inline-block h-9 w-1.5 shrink-0 rounded opacity-60"
                                    :style="{ backgroundColor: b.background_color || '#94a3b8' }"
                                />
                                <div class="min-w-0 flex-1">
                                    <div class="truncate text-sm font-medium text-slate-700 dark:text-slate-200">
                                        {{ b.name }}
                                    </div>
                                    <div class="truncate text-xs text-slate-500 dark:text-slate-400">
                                        {{ b.workspace_name }}
                                    </div>
                                </div>
                                <span class="inline-flex shrink-0 items-center rounded bg-rose-50 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wider text-rose-600 dark:bg-rose-500/15 dark:text-rose-300">
                                    Closed
                                </span>
                                <svg class="h-4 w-4 shrink-0 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                </svg>
                            </Link>
                        </li>
                    </ul>

                    <div
                        v-else
                        class="flex h-40 items-center justify-center text-sm text-slate-500 dark:text-slate-400"
                    >
                        No closed boards.
                    </div>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
