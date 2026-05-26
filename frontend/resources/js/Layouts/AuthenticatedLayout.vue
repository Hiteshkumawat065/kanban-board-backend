<script setup>
import { computed, ref } from 'vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import { Link, usePage } from '@inertiajs/vue3';

// =============================================================================
//  Global app shell: fixed top bar + persistent left sidebar + main content.
//  Every authenticated page renders inside this layout's <slot />.
//
//  Layout math:
//    - Top bar height = 4rem (h-16). Pages that need full-viewport height
//      use `h-[calc(100vh-4rem)]` to fit underneath it.
//    - Sidebar width = 16rem on md+ screens; main content gets md:pl-64 to
//      sit beside it. On mobile the sidebar slides in as an overlay drawer.
// =============================================================================

// Pages can opt out of the persistent sidebar (e.g. the Kanban board / task
// list screen wants a full-width canvas with its own back navigation).
const props = defineProps({
    hideSidebar: { type: Boolean, default: false },
});

const page = usePage();
const authUser = computed(() => page.props.auth?.user ?? null);

// Workspaces are shared globally via HandleInertiaRequests so the switcher
// works on every page (Dashboard, Workspaces list, Board, Profile…).
const workspaces = computed(() => page.props.nav?.workspaces ?? []);

// First name for the workspace-switcher button label / greeting fallbacks.
const firstName = computed(() => (authUser.value?.name ?? '').split(/\s+/)[0] ?? '');

// "Harish Kumar" -> "HK". Matches the helper used on assignee chips.
const userInitials = computed(() => {
    const name = authUser.value?.name ?? '';
    if (!name) return '?';
    return name
        .split(/\s+/)
        .filter(Boolean)
        .map((w) => w[0])
        .slice(0, 2)
        .join('')
        .toUpperCase();
});

// Deterministic color per user id — matches the palette used on Kanban
// assignee chips so an individual stays the same color everywhere.
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
const userAvatarColor = computed(() => {
    const n = Number(authUser.value?.id) || 0;
    return AVATAR_COLORS[n % AVATAR_COLORS.length];
});

// -----------------------------------------------------------------------------
// Mobile sidebar drawer state.
// -----------------------------------------------------------------------------
const sidebarOpen = ref(false);
function toggleSidebar() {
    sidebarOpen.value = !sidebarOpen.value;
}
function closeSidebar() {
    sidebarOpen.value = false;
}

// -----------------------------------------------------------------------------
// Sidebar nav items. Each entry pairs a Heroicons-style outline SVG `path`
// with a route + an `active()` predicate used to highlight the current page.
// `disabled` items render greyed-out (Teams / Analytics / Activity are
// placeholders for screens that aren't built yet).
// -----------------------------------------------------------------------------
const navItems = computed(() => [
    {
        key: 'dashboard',
        label: 'Dashboard',
        href: route('dashboard'),
        active: route().current('dashboard'),
        path: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6',
    },
    {
        key: 'workspaces',
        label: 'Workspaces',
        href: route('workspaces.index'),
        active: route().current('workspaces.*'),
        path: 'M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z',
    }, 
    {
        key: 'analytics',
        label: 'Analytics',
        href: '#',
        active: false,
        disabled: true,
        path: 'M3 3v18h18M7 14l4-4 4 4 5-5',
    },
    {
        key: 'activity',
        label: 'Activity',
        href: '#',
        active: false,
        disabled: true,
        path: 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
    },  
    // Sits directly underneath Settings — it's a "configuration" surface
    // that admins manage rather than a per-user preference, so it lives
    // in the same neighbourhood without being a child of Settings.
    {
        key: 'email-templates',
        label: 'Email Templates',
        href: route('email-templates.index'),
        active: route().current('email-templates.*'),
        path: 'M3 8l9 6 9-6M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z',
    },
    {
        key: 'settings',
        label: 'Settings',
        href: route('profile.edit'),
        active: route().current('profile.*'),
        path: 'M10.325 4.317a1.724 1.724 0 013.35 0 1.724 1.724 0 002.591 1.07 1.724 1.724 0 012.37 2.37 1.724 1.724 0 001.07 2.59 1.724 1.724 0 010 3.35 1.724 1.724 0 00-1.07 2.591 1.724 1.724 0 01-2.37 2.37 1.724 1.724 0 00-2.59 1.07 1.724 1.724 0 01-3.35 0 1.724 1.724 0 00-2.591-1.07 1.724 1.724 0 01-2.37-2.37 1.724 1.724 0 00-1.07-2.591 1.724 1.724 0 010-3.35 1.724 1.724 0 001.07-2.59 1.724 1.724 0 012.37-2.37 1.724 1.724 0 002.591-1.07zM15 12a3 3 0 11-6 0 3 3 0 016 0z',
    },
]);

// -----------------------------------------------------------------------------
// Workspace switcher dropdown.
//  - If we're inside a `/workspaces/{id}/boards` or `/boards/{id}` page, show
//    that workspace's name on the trigger; otherwise show "All workspaces".
//  - Dropdown lists every workspace + a link back to the workspaces index.
//    Each workspace entry links to its board listing.
// -----------------------------------------------------------------------------
const currentWorkspaceName = computed(() => {
    const props = page.props;
    return (
        props.workspace?.data?.name ??
        props.board?.data?.workspace?.name ??
        null
    );
});

// -----------------------------------------------------------------------------
// Top-bar search. Stub for now — wires up the input UI; submitting it
// navigates to the workspaces index where filtering will eventually live.
// -----------------------------------------------------------------------------
const searchQuery = ref('');
function onSearchSubmit() {
    // No-op for the moment; just clear the field. The dashboard mockup
    // shows a search box so we render it, but global search is a follow-up
    // feature once the dashboard ships.
}
</script>

<template>
    <div class="min-h-screen bg-slate-100 text-slate-800 dark:bg-slate-900 dark:text-slate-100">
        <!-- =====================================================================
             TOP BAR
             Fixed, full-width, always sits on top. Houses: hamburger (mobile),
             logo, workspace switcher, search, notifications, user avatar.
        ===================================================================== -->
        <header
            class="fixed inset-x-0 top-0 z-30 flex h-16 items-center gap-3 border-b border-slate-200 bg-white px-3 shadow-sm dark:border-slate-700 dark:bg-slate-800 sm:px-4"
        >
            <!-- Mobile hamburger — only visible below md, toggles the sidebar drawer.
                 Hidden when the page has opted out of the sidebar entirely. -->
            <button
                v-if="!props.hideSidebar"
                type="button"
                class="inline-flex h-9 w-9 items-center justify-center rounded-md text-slate-600 hover:bg-slate-100 md:hidden dark:text-slate-300 dark:hover:bg-slate-700"
                aria-label="Toggle navigation"
                @click="toggleSidebar"
            >
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </button>

            <!-- Logo -->
            <Link :href="route('dashboard')" class="flex shrink-0 items-center gap-2">
                <ApplicationLogo class="h-8 w-8 fill-current text-indigo-600 dark:text-indigo-400" />
                <span class="hidden text-base font-semibold text-slate-800 dark:text-slate-100 sm:inline">
                    Kanban
                </span>
            </Link>

            <!-- Workspace switcher -->
            <div class="ml-2 hidden md:block">
                <Dropdown align="left" width="64">
                    <template #trigger>
                        <button
                            type="button"
                            class="inline-flex items-center gap-2 rounded-md border border-slate-200 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200 dark:hover:bg-slate-600"
                        >
                            <svg class="h-4 w-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
                            </svg>
                            <span class="max-w-[12rem] truncate">
                                {{ currentWorkspaceName || 'All workspaces' }}
                            </span>
                            <svg class="h-4 w-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                    </template>

                    <template #content>
                        <DropdownLink :href="route('workspaces.index')">
                            All workspaces
                        </DropdownLink>
                        <div
                            v-if="workspaces.length"
                            class="my-1 border-t border-slate-100 dark:border-slate-700"
                        />
                        <DropdownLink
                            v-for="ws in workspaces"
                            :key="ws.id"
                            :href="`/workspaces/${ws.id}/boards`"
                        >
                            {{ ws.name }}
                        </DropdownLink>
                    </template>
                </Dropdown>
            </div>

            <!-- Search -->
            <form
                class="ml-2 hidden flex-1 max-w-xl items-center md:flex"
                @submit.prevent="onSearchSubmit"
            >
                <div class="relative w-full">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z" />
                        </svg>
                    </span>
                    <input
                        v-model="searchQuery"
                        type="search"
                        placeholder="Search workspaces, boards, tasks…"
                        class="w-full rounded-md border-slate-200 bg-slate-50 py-1.5 pl-9 pr-3 text-sm placeholder-slate-400 focus:border-indigo-500 focus:bg-white focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-700/60 dark:text-slate-200 dark:placeholder-slate-400 dark:focus:bg-slate-700"
                    />
                </div>
            </form>

            <!-- Right cluster: notifications + user -->
            <div class="ml-auto flex items-center gap-2">
                <!-- Notifications (placeholder bell) -->
                <button
                    type="button"
                    class="relative inline-flex h-9 w-9 items-center justify-center rounded-md text-slate-500 transition hover:bg-slate-100 hover:text-slate-700 dark:text-slate-300 dark:hover:bg-slate-700 dark:hover:text-white"
                    aria-label="Notifications"
                    title="Notifications"
                >
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.4-1.4A2 2 0 0118 14.17V11a6 6 0 10-12 0v3.17a2 2 0 01-.6 1.43L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                    </svg>
                </button>

                <!-- User avatar + dropdown (same content as before; keeps profile/logout) -->
                <Dropdown align="right" width="48">
                    <template #trigger>
                        <button
                            type="button"
                            class="inline-flex h-9 w-9 items-center justify-center rounded-full text-xs font-semibold text-white shadow-sm ring-2 ring-white transition hover:opacity-90 focus:outline-none focus:ring-indigo-400 dark:ring-slate-800"
                            :style="{ backgroundColor: userAvatarColor }"
                            :title="authUser?.name"
                        >
                            {{ userInitials }}
                        </button>
                    </template>
                    <template #content>
                        <div class="border-b border-slate-100 px-4 py-2 dark:border-slate-700">
                            <div class="text-sm font-semibold text-slate-800 dark:text-slate-100">
                                {{ authUser?.name }}
                            </div>
                            <div class="truncate text-xs text-slate-500 dark:text-slate-400">
                                {{ authUser?.email }}
                            </div>
                        </div>
                        <DropdownLink :href="route('profile.edit')">Profile</DropdownLink>
                        <DropdownLink :href="route('logout')" method="post" as="button">
                            Log Out
                        </DropdownLink>
                    </template>
                </Dropdown>
            </div>
        </header>

        <!-- =====================================================================
             SIDEBAR
             Persistent on md+, slides in as a drawer on mobile.
             Sits underneath the top bar (top-16) so the logo cluster stays visible.
             Entire block is skipped when `hideSidebar` is set by the page.
        ===================================================================== -->
        <!-- Mobile backdrop -->
        <div
            v-if="sidebarOpen && !props.hideSidebar"
            class="fixed inset-0 z-20 bg-slate-900/50 md:hidden"
            @click="closeSidebar"
        />

        <aside
            v-if="!props.hideSidebar"
            class="fixed inset-y-0 left-0 top-16 z-20 w-64 transform overflow-y-auto border-r border-slate-200 bg-white pb-6 transition-transform duration-200 ease-out dark:border-slate-700 dark:bg-slate-800 md:translate-x-0"
            :class="sidebarOpen ? 'translate-x-0 shadow-2xl' : '-translate-x-full'"
        >
            <nav class="mt-4 space-y-1 px-3">
                <template v-for="item in navItems" :key="item.key">
                    <Link
                        v-if="!item.disabled"
                        :href="item.href"
                        :class="[
                            'flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition',
                            item.active
                                ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300'
                                : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-700 dark:hover:text-white',
                        ]"
                        @click="closeSidebar"
                    >
                        <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" :d="item.path" />
                        </svg>
                        {{ item.label }}
                    </Link>

                    <span
                        v-else
                        class="flex cursor-not-allowed items-center gap-3 rounded-md px-3 py-2 text-sm font-medium text-slate-400 dark:text-slate-500"
                        :title="`${item.label} — coming soon`"
                    >
                        <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" :d="item.path" />
                        </svg>
                        {{ item.label }}
                        <span class="ml-auto rounded-full bg-slate-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wider text-slate-500 dark:bg-slate-700 dark:text-slate-400">
                            Soon
                        </span>
                    </span>
                </template>
            </nav>

            <!-- Workspace shortcuts — quick access to each workspace from the sidebar. -->
            <div v-if="workspaces.length" class="mt-6 px-3">
                <!-- <div class="px-3 pb-2 text-[11px] font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">
                    Your workspaces
                </div>
                <div class="space-y-0.5">
                    <Link
                        v-for="ws in workspaces.slice(0, 6)"
                        :key="ws.id"
                        :href="`/workspaces/${ws.id}/boards`"
                        class="flex items-center gap-2 rounded-md px-3 py-1.5 text-sm text-slate-600 transition hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-700 dark:hover:text-white"
                        @click="closeSidebar"
                    >
                        <span
                            class="inline-flex h-5 w-5 shrink-0 items-center justify-center rounded text-[10px] font-bold text-white"
                            :style="{ backgroundColor: AVATAR_COLORS[ws.id % AVATAR_COLORS.length] }"
                        >
                            {{ (ws.name?.[0] ?? '?').toUpperCase() }}
                        </span>
                        <span class="truncate">{{ ws.name }}</span>
                    </Link>
                </div>
                -->
            </div>
        </aside>

        <!-- =====================================================================
             MAIN CONTENT
             pt-16 clears the fixed top bar; md:pl-64 clears the fixed sidebar
             unless the page has opted out (hideSidebar), in which case the
             main content gets the full viewport width.
        ===================================================================== -->
        <div class="pt-16" :class="props.hideSidebar ? '' : 'md:pl-64'">
            <!-- Optional page-level header band (used by Workspaces / Boards pages
                 via <template #header>...). Renders only when a slot is provided
                 so the Dashboard page can skip it entirely. -->
            <header
                v-if="$slots.header"
                class="border-b border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800"
            >
                <div class="mx-auto px-4 py-4 sm:px-6 lg:px-8">
                    <slot name="header" />
                </div>
            </header>

            <main>
                <slot />
            </main>
        </div>
    </div>
</template>
