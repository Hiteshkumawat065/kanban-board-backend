<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import SendTestModal from '@/Components/EmailTemplate/SendTestModal.vue';

function goBack() {
    // The email-templates listing sits directly under the dashboard, so
    // "back" always returns the user there — same pattern as Workspaces.
    router.visit('/dashboard');
}

// =============================================================================
//  EmailTemplates / Index.vue
//
//  Admin listing for every email template the workspace owns.
//
//  Features:
//   - Search across name + key + subject + description
//   - Category & status filters
//   - Sortable columns (name / category / status / updated_at)
//   - Per-page selector + numeric pagination
//   - Row actions: edit, duplicate, toggle status, send test, view logs, delete
//
//  Pagination & filters drive the URL via router.get() so deep links work
//  (e.g. sharing a "show me all draft Task templates" filter set).
// =============================================================================

const props = defineProps({
    items: { type: Array, required: true },
    meta: { type: Object, required: true },
    filters: { type: Object, required: true },
    stats: { type: Object, required: true },
    options: { type: Object, required: true },
});

// Reactive copy of incoming filters so the form inputs are debounce-able
// without round-tripping every keystroke through the server.
const form = reactive({
    q: props.filters.q ?? '',
    status: props.filters.status ?? '',
    category: props.filters.category ?? '',
    sort: props.filters.sort ?? 'updated_at',
    direction: props.filters.direction ?? 'desc',
    per_page: props.filters.per_page ?? 15,
});

let searchTimer = null;
function applyFilters(extra = {}) {
    const payload = { ...form, ...extra };
    // Strip blanks so the URL stays tidy.
    Object.keys(payload).forEach((k) => {
        if (payload[k] === '' || payload[k] === null) delete payload[k];
    });
    router.get(route('email-templates.index'), payload, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    });
}

watch(
    () => form.q,
    () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => applyFilters(), 350);
    },
);

watch(
    () => [form.status, form.category, form.per_page],
    () => applyFilters(),
);

function changeSort(column) {
    if (form.sort === column) {
        form.direction = form.direction === 'asc' ? 'desc' : 'asc';
    } else {
        form.sort = column;
        form.direction = 'asc';
    }
    applyFilters();
}

function goToPage(page) {
    applyFilters({ page });
}

function clearFilters() {
    form.q = '';
    form.status = '';
    form.category = '';
    form.sort = 'updated_at';
    form.direction = 'desc';
    form.per_page = 15;
}

// -----------------------------------------------------------------------------
// Row actions
// -----------------------------------------------------------------------------
function duplicate(item) {
    router.post(
        route('email-templates.duplicate', item.slug),
        {},
        { preserveScroll: true },
    );
}

function toggleStatus(item) {
    router.post(
        route('email-templates.toggle-status', item.slug),
        {},
        { preserveScroll: true },
    );
}

function destroy(item) {
    if (!confirm(`Delete template "${item.template_name}"? This cannot be undone.`)) return;
    router.delete(route('email-templates.destroy', item.slug), {
        preserveScroll: true,
    });
}

// Send test modal — opens against a specific template.
const testTarget = ref(null);
function openSendTest(item) {
    testTarget.value = item;
}
function closeSendTest() {
    testTarget.value = null;
}

// -----------------------------------------------------------------------------
// Visual helpers
// -----------------------------------------------------------------------------
const sortableColumns = [
    { key: 'template_name', label: 'Template' },
    { key: 'category', label: 'Category' },
    { key: 'status', label: 'Status' },
    { key: 'updated_at', label: 'Updated' },
];

function sortIcon(col) {
    if (form.sort !== col) return '↕';
    return form.direction === 'asc' ? '↑' : '↓';
}

const categoryClasses = {
    indigo: 'bg-indigo-50 text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300',
    emerald: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300',
    sky: 'bg-sky-50 text-sky-700 dark:bg-sky-500/10 dark:text-sky-300',
    cyan: 'bg-cyan-50 text-cyan-700 dark:bg-cyan-500/10 dark:text-cyan-300',
    amber: 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300',
    violet: 'bg-violet-50 text-violet-700 dark:bg-violet-500/10 dark:text-violet-300',
    pink: 'bg-pink-50 text-pink-700 dark:bg-pink-500/10 dark:text-pink-300',
    slate: 'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-200',
    gray: 'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-200',
    rose: 'bg-rose-50 text-rose-700 dark:bg-rose-500/10 dark:text-rose-300',
};

function badgeClass(color) {
    return categoryClasses[color] ?? categoryClasses.slate;
}

// Compact 1 … 4 5 6 … 10 page list.
const pageNumbers = computed(() => {
    const pages = [];
    const last = props.meta.last_page ?? 1;
    const current = props.meta.current_page ?? 1;
    const window = 1;
    for (let p = 1; p <= last; p++) {
        if (
            p === 1
            || p === last
            || (p >= current - window && p <= current + window)
        ) {
            pages.push(p);
        } else if (pages[pages.length - 1] !== '…') {
            pages.push('…');
        }
    }
    return pages;
});
</script>

<template>
    <Head title="Email Templates" />
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
                                Email Templates
                            </span>
                        </nav>
                        <h2 class="mt-0.5 truncate text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                            Email Templates
                        </h2>
                    </div>
                </div>

                <Link
                    :href="route('email-templates.create')"
                    class="rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white shadow-sm transition hover:bg-indigo-700"
                >
                    + New template
                </Link>
            </div>
        </template>

        <div class="mx-auto px-4 py-6 sm:px-6 lg:px-8">
            <!-- Filters + Table -->
            <div class="rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800">
                <div class="flex flex-wrap items-center gap-2 border-b border-slate-200 p-3 dark:border-slate-700">
                    <div class="relative flex-1 min-w-[14rem]">
                        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z" />
                            </svg>
                        </span>
                        <input
                            v-model="form.q"
                            type="search"
                            placeholder="Search by name, key, subject…"
                            class="w-full rounded-md border-slate-200 bg-slate-50 py-1.5 pl-9 pr-3 text-sm placeholder-slate-400 focus:border-indigo-500 focus:bg-white focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-900/40 dark:text-slate-100"
                        />
                    </div>

                    <select
                        v-model="form.category"
                        class="rounded-md border-slate-200 bg-white py-1.5 pl-3 pr-8 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-900/40 dark:text-slate-100"
                    >
                        <option value="">All categories</option>
                        <option v-for="c in options.categories" :key="c.value" :value="c.value">
                            {{ c.label }}
                        </option>
                    </select>

                    <select
                        v-model="form.status"
                        class="rounded-md border-slate-200 bg-white py-1.5 pl-3 pr-8 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-900/40 dark:text-slate-100"
                    >
                        <option value="">All statuses</option>
                        <option v-for="s in options.statuses" :key="s.value" :value="s.value">
                            {{ s.label }}
                        </option>
                    </select>

                    <select
                        v-model.number="form.per_page"
                        class="rounded-md border-slate-200 bg-white py-1.5 pl-3 pr-8 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-900/40 dark:text-slate-100"
                    >
                        <option :value="10">10 / page</option>
                        <option :value="15">15 / page</option>
                        <option :value="25">25 / page</option>
                        <option :value="50">50 / page</option>
                    </select>

                    <button
                        type="button"
                        class="rounded-md border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-medium text-slate-600 transition hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-300"
                        @click="clearFilters"
                    >
                        Reset
                    </button>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-700">
                        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wider text-slate-500 dark:bg-slate-900/30 dark:text-slate-400">
                            <tr>
                                <th
                                    v-for="col in sortableColumns"
                                    :key="col.key"
                                    class="cursor-pointer select-none px-4 py-3 transition hover:text-slate-700 dark:hover:text-slate-200"
                                    @click="changeSort(col.key)"
                                >
                                    <div class="inline-flex items-center gap-1">
                                        {{ col.label }}
                                        <span class="text-slate-400">{{ sortIcon(col.key) }}</span>
                                    </div>
                                </th>
                                <th class="px-4 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                            <tr v-if="!items.length">
                                <td colspan="5" class="px-4 py-10 text-center text-slate-400">
                                    No templates match your filters.
                                </td>
                            </tr>
                            <tr
                                v-for="item in items"
                                :key="item.id"
                                class="transition hover:bg-slate-50 dark:hover:bg-slate-700/30"
                            >
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        <Link
                                            :href="route('email-templates.edit', item.slug)"
                                            class="font-medium text-slate-900 hover:text-indigo-600 dark:text-slate-100 dark:hover:text-indigo-300"
                                        >
                                            {{ item.template_name }}
                                        </Link>
                                        <span
                                            v-if="item.is_system_template"
                                            class="rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wider text-slate-500 dark:bg-slate-700 dark:text-slate-300"
                                            title="Shipped with the platform"
                                        >
                                            System
                                        </span>
                                    </div>
                                    <div class="mt-0.5 font-mono text-[11px] text-slate-500 dark:text-slate-400">
                                        {{ item.template_key }}
                                    </div>
                                    <div class="mt-0.5 truncate text-xs text-slate-500 dark:text-slate-400" :title="item.subject">
                                        {{ item.subject }}
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <span
                                        class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-semibold"
                                        :class="badgeClass(item.category_color)"
                                    >
                                        {{ item.category_label }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-[11px] font-semibold transition hover:opacity-80"
                                        :class="badgeClass(item.status_color)"
                                        @click="toggleStatus(item)"
                                        title="Toggle status"
                                    >
                                        <span class="h-1.5 w-1.5 rounded-full bg-current" />
                                        {{ item.status_label }}
                                    </button>
                                </td>
                                <td class="px-4 py-3 text-slate-500 dark:text-slate-400">
                                    <div>{{ item.updated_at ?? '—' }}</div>
                                    <div class="text-[11px]">
                                        by {{ item.updated_by_name ?? '—' }}
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div class="inline-flex items-center gap-1">
                                        <Link
                                            :href="route('email-templates.edit', item.slug)"
                                            class="rounded-md border border-slate-200 px-2 py-1 text-xs font-medium text-slate-600 transition hover:bg-slate-50 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-700"
                                        >
                                            Edit
                                        </Link>
                                        <button
                                            type="button"
                                            class="rounded-md border border-slate-200 px-2 py-1 text-xs font-medium text-slate-600 transition hover:bg-slate-50 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-700"
                                            @click="openSendTest(item)"
                                        >
                                            Send test
                                        </button>
                                        <button
                                            type="button"
                                            class="rounded-md border border-slate-200 px-2 py-1 text-xs font-medium text-slate-600 transition hover:bg-slate-50 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-700"
                                            @click="duplicate(item)"
                                        >
                                            Duplicate
                                        </button>
                                        <Link
                                            :href="route('email-templates.logs', item.slug)"
                                            class="rounded-md border border-slate-200 px-2 py-1 text-xs font-medium text-slate-600 transition hover:bg-slate-50 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-700"
                                        >
                                            Logs
                                        </Link>
                                        <button
                                            v-if="!item.is_system_template"
                                            type="button"
                                            class="rounded-md border border-rose-200 bg-rose-50 px-2 py-1 text-xs font-medium text-rose-600 transition hover:bg-rose-100 dark:border-rose-700 dark:bg-rose-900/30 dark:text-rose-300"
                                            @click="destroy(item)"
                                        >
                                            Delete
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div
                    v-if="meta.last_page > 1"
                    class="flex items-center justify-between gap-3 border-t border-slate-200 px-4 py-3 text-xs text-slate-500 dark:border-slate-700 dark:text-slate-400"
                >
                    <div>
                        Showing {{ meta.from ?? 0 }}–{{ meta.to ?? 0 }} of {{ meta.total }}
                    </div>
                    <div class="inline-flex items-center gap-1">
                        <button
                            type="button"
                            class="rounded-md border border-slate-200 px-2 py-1 transition disabled:opacity-40 hover:bg-slate-50 dark:border-slate-600 dark:hover:bg-slate-700"
                            :disabled="meta.current_page <= 1"
                            @click="goToPage(meta.current_page - 1)"
                        >
                            ← Prev
                        </button>
                        <template v-for="(p, i) in pageNumbers" :key="i">
                            <span v-if="p === '…'" class="px-2">…</span>
                            <button
                                v-else
                                type="button"
                                class="rounded-md border px-2 py-1 transition"
                                :class="p === meta.current_page
                                    ? 'border-indigo-600 bg-indigo-600 text-white'
                                    : 'border-slate-200 hover:bg-slate-50 dark:border-slate-600 dark:hover:bg-slate-700'"
                                @click="goToPage(p)"
                            >
                                {{ p }}
                            </button>
                        </template>
                        <button
                            type="button"
                            class="rounded-md border border-slate-200 px-2 py-1 transition disabled:opacity-40 hover:bg-slate-50 dark:border-slate-600 dark:hover:bg-slate-700"
                            :disabled="meta.current_page >= meta.last_page"
                            @click="goToPage(meta.current_page + 1)"
                        >
                            Next →
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <SendTestModal
            v-if="testTarget"
            :show="!!testTarget"
            :template="testTarget"
            :system-variables="options.system_variables"
            :template-variables="[]"
            @close="closeSendTest"
        />
    </AuthenticatedLayout>
</template>
