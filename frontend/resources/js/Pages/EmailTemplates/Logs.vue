<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

// =============================================================================
//  EmailTemplates / Logs.vue
//
//  Read-only delivery history for a single template — sourced from
//  EmailTemplateController@logs. Powers operator debugging when a customer
//  reports "I never got the email".
// =============================================================================

const props = defineProps({
    template: { type: Object, required: true },
    items: { type: Array, required: true },
    meta: { type: Object, required: true },
});

function goBack() {
    // From the logs page, "back" goes to the parent template editor —
    // matching the same intent as Boards.Show going back to Boards.Index.
    router.visit(route('email-templates.edit', props.template.slug));
}

const statusBadges = {
    sent: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300',
    queued: 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300',
    failed: 'bg-rose-50 text-rose-700 dark:bg-rose-500/10 dark:text-rose-300',
};
</script>

<template>
    <Head :title="`Logs · ${template.template_name}`" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex min-w-0 items-center gap-3">
                    <!-- Back button -> template editor -->
                    <button
                        type="button"
                        class="inline-flex h-9 items-center gap-1.5 rounded-md border border-slate-200 bg-white px-3 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200 dark:hover:bg-slate-600"
                        title="Back to template"
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
                            <Link :href="route('email-templates.index')" class="hover:text-slate-700 dark:hover:text-slate-200">
                                Email Templates
                            </Link>
                            <span class="text-slate-400 dark:text-slate-500">/</span>
                            <Link :href="route('email-templates.edit', template.slug)" class="max-w-[12rem] truncate hover:text-slate-700 dark:hover:text-slate-200">
                                {{ template.template_name }}
                            </Link>
                            <span class="text-slate-400 dark:text-slate-500">/</span>
                            <span class="font-medium text-slate-700 dark:text-slate-200">Logs</span>
                        </nav>
                        <h2 class="mt-0.5 truncate text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                            Delivery history
                        </h2>
                        <p class="mt-0.5 text-[11px] text-slate-500 dark:text-slate-400">
                            Every dispatch through <span class="font-mono">{{ template.template_key }}</span> — successes, failures and queued attempts.
                        </p>
                    </div>
                </div>

                <Link
                    :href="route('email-templates.index')"
                    class="inline-flex h-9 items-center gap-1.5 rounded-md border border-slate-200 bg-white px-3 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200 dark:hover:bg-slate-600"
                >
                    All templates
                </Link>
            </div>
        </template>

        <div class="mx-auto px-4 py-6 sm:px-6 lg:px-8">
            <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-700">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wider text-slate-500 dark:bg-slate-900/30 dark:text-slate-400">
                        <tr>
                            <th class="px-4 py-3">Recipient</th>
                            <th class="px-4 py-3">Subject</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Sent</th>
                            <th class="px-4 py-3">By</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                        <tr v-if="!items.length">
                            <td colspan="5" class="px-4 py-10 text-center text-slate-400">
                                No logs yet — try sending a test email from the editor.
                            </td>
                        </tr>
                        <tr v-for="row in items" :key="row.id" class="hover:bg-slate-50 dark:hover:bg-slate-700/30">
                            <td class="px-4 py-3 text-slate-700 dark:text-slate-200">{{ row.to_email }}</td>
                            <td class="px-4 py-3 truncate text-slate-500 dark:text-slate-400">{{ row.subject }}</td>
                            <td class="px-4 py-3">
                                <span
                                    class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-[11px] font-semibold"
                                    :class="statusBadges[row.status] ?? statusBadges.queued"
                                >
                                    <span class="h-1.5 w-1.5 rounded-full bg-current" />
                                    {{ row.status }}
                                </span>
                                <div v-if="row.error" class="mt-1 truncate text-[11px] text-rose-600" :title="row.error">
                                    {{ row.error }}
                                </div>
                            </td>
                            <td class="px-4 py-3 text-slate-500 dark:text-slate-400">
                                {{ row.sent_at ?? row.created_at }}
                            </td>
                            <td class="px-4 py-3 text-slate-500 dark:text-slate-400">
                                {{ row.sender_name ?? '—' }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
