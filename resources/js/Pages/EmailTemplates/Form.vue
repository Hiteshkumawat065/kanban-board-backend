<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import RichTextEditor from '@/Components/EmailTemplate/RichTextEditor.vue';
import VariablePalette from '@/Components/EmailTemplate/VariablePalette.vue';
import PreviewPanel from '@/Components/EmailTemplate/PreviewPanel.vue';
import SendTestModal from '@/Components/EmailTemplate/SendTestModal.vue';

function goBack() {
    // From the create / edit form, "back" returns to the templates listing.
    router.visit(route('email-templates.index'));
}

// =============================================================================
//  EmailTemplates / Form.vue
//
//  Single page that handles both "create" and "edit" — the parent
//  controller flags which by passing `mode = 'create' | 'edit'`. The form
//  layout is a 3-column workspace:
//
//    ┌──────────────────────┬──────────────┬────────────┐
//    │  Metadata + editor   │  Live preview │  Variables │
//    └──────────────────────┴──────────────┴────────────┘
//
//  - The editor + preview run a 250ms-debounced /preview round-trip so
//    server-side rendering is the source of truth (handles whitespace,
//    HTML normalisation, unresolved tokens identically to the real send).
//  - For brand-new templates (no `id` yet) we render locally with a
//    simple {{ key }} → value substitution so the preview still works
//    before the first save.
// =============================================================================

const props = defineProps({
    mode: { type: String, default: 'create' },
    template: { type: Object, required: true },
    options: { type: Object, required: true },
});

const isEdit = computed(() => props.mode === 'edit' && !!props.template.id);

// `useForm` gives us errors / processing state automatically. We seed it
// with the template payload from the controller (empty object for create).
const form = useForm({
    template_name: props.template.template_name ?? '',
    template_key: props.template.template_key ?? '',
    slug: props.template.slug ?? '',
    subject: props.template.subject ?? '',
    category: props.template.category ?? 'notification',
    description: props.template.description ?? '',
    email_from_name: props.template.email_from_name ?? '',
    email_from_email: props.template.email_from_email ?? '',
    reply_to: props.template.reply_to ?? '',
    cc: props.template.cc ?? [],
    bcc: props.template.bcc ?? [],
    variables: props.template.variables ?? [],
    body_content: props.template.body_content ?? '',
    plain_text: props.template.plain_text ?? '',
    attachments: props.template.attachments ?? [],
    status: props.template.status ?? 'active',
    is_system_template: props.template.is_system_template ?? false,
    locale: props.template.locale ?? 'en',
});

// CC / BCC are stored as arrays but displayed as comma-separated strings
// in the UI. Two-way computed bridges keep them in sync.
const ccText = computed({
    get: () => (form.cc ?? []).join(', '),
    set: (v) =>
        (form.cc = (v ?? '')
            .split(',')
            .map((x) => x.trim())
            .filter(Boolean)),
});
const bccText = computed({
    get: () => (form.bcc ?? []).join(', '),
    set: (v) =>
        (form.bcc = (v ?? '')
            .split(',')
            .map((x) => x.trim())
            .filter(Boolean)),
});

// -----------------------------------------------------------------------------
// Live preview
// -----------------------------------------------------------------------------
const preview = reactive({
    subject: form.subject || '',
    body: form.body_content || '',
    loading: false,
});

// Local fallback render — used before a template has been saved (no slug)
// or when the user is offline. Mirrors the backend parser closely enough
// that the preview never appears blank.
function localRender(template, context) {
    return String(template ?? '').replace(/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/g, (_, key) =>
        Object.prototype.hasOwnProperty.call(context, key) ? String(context[key]) : `{{ ${key} }}`,
    );
}

function buildContext() {
    const merged = {};
    [...props.options.system_variables, ...(form.variables ?? [])].forEach((v) => {
        if (v?.key && v.sample != null && v.sample !== '') merged[v.key] = v.sample;
    });
    merged.current_date = new Date().toLocaleDateString();
    merged.company_name = merged.company_name ?? 'Kanban';
    return merged;
}

let previewTimer = null;
async function refreshPreview() {
    const context = buildContext();

    // Always paint a local snapshot first so the UI never feels stuck while
    // we round-trip to the server. This is also what gets shown for brand-
    // new (unsaved) templates.
    preview.subject = localRender(form.subject, context);
    preview.body = localRender(form.body_content, context);

    if (!props.template.slug) return;

    clearTimeout(previewTimer);
    previewTimer = setTimeout(async () => {
        preview.loading = true;
        try {
            // Send the CURRENT DRAFT subject/body/variables to the server
            // — otherwise the endpoint would re-render whatever's saved on
            // the model and silently clobber the user's unsaved changes.
            const { data } = await window.axios.post(
                route('email-templates.preview', props.template.slug),
                {
                    context,
                    subject: form.subject,
                    body: form.body_content,
                    variables: form.variables ?? [],
                },
            );
            preview.subject = data.subject;
            preview.body = data.body;
        } catch (e) {
            // Local render already painted — silently ignore the network
            // hiccup, no need to scare the operator.
        } finally {
            preview.loading = false;
        }
    }, 250);
}

watch(
    () => [form.subject, form.body_content, form.variables],
    refreshPreview,
    { deep: true, immediate: true },
);

// -----------------------------------------------------------------------------
// Editor controls
// -----------------------------------------------------------------------------
const editorRef = ref(null);
function onInsertVariable(token) {
    editorRef.value?.insertText(token);
}

// -----------------------------------------------------------------------------
// Submit
// -----------------------------------------------------------------------------
function submit() {
    if (isEdit.value) {
        form.put(route('email-templates.update', props.template.slug), {
            preserveScroll: true,
        });
    } else {
        form.post(route('email-templates.store'), { preserveScroll: true });
    }
}

// Test send modal state
const showTestModal = ref(false);

// -----------------------------------------------------------------------------
// Helpers
// -----------------------------------------------------------------------------
const categoryClasses = {
    indigo: 'bg-indigo-50 text-indigo-700',
    emerald: 'bg-emerald-50 text-emerald-700',
    sky: 'bg-sky-50 text-sky-700',
    cyan: 'bg-cyan-50 text-cyan-700',
    amber: 'bg-amber-50 text-amber-700',
    violet: 'bg-violet-50 text-violet-700',
    pink: 'bg-pink-50 text-pink-700',
    slate: 'bg-slate-100 text-slate-700',
    gray: 'bg-slate-100 text-slate-700',
    rose: 'bg-rose-50 text-rose-700',
};

function categoryColor(value) {
    return props.options.categories.find((c) => c.value === value)?.color ?? 'slate';
}

// Vue's template compiler is greedy on `{{` — embedding a literal mustache
// pair inside an interpolation throws "unterminated string". We expose the
// pre-formatted example token here so the help text in the subject hint
// renders cleanly via plain `{{ exampleToken }}`.
const exampleToken = '{' + '{ user_name }' + '}';
</script>

<template>
    <Head :title="isEdit ? `Edit · ${form.template_name || 'Email template'}` : 'New email template'" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex min-w-0 items-center gap-3">
                    <!-- Back button -> templates listing -->
                    <button
                        type="button"
                        class="inline-flex h-9 items-center gap-1.5 rounded-md border border-slate-200 bg-white px-3 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200 dark:hover:bg-slate-600"
                        title="Back to email templates"
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
                            <span class="max-w-[14rem] truncate font-medium text-slate-700 dark:text-slate-200">
                                {{ isEdit ? form.template_name || 'Edit' : 'New' }}
                            </span>
                        </nav>
                        <h2 class="mt-0.5 truncate text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                            {{ isEdit ? form.template_name || 'Untitled template' : 'New email template' }}
                        </h2>
                        <div v-if="isEdit" class="mt-0.5 flex flex-wrap items-center gap-2 text-[11px] text-slate-500 dark:text-slate-400">
                            <span class="font-mono">{{ form.template_key }}</span>
                            <span>·</span>
                            <span>v{{ template.version }}</span>
                            <span v-if="template.updated_at">· updated {{ template.updated_at }}</span>
                            <span v-if="template.updated_by_name">by {{ template.updated_by_name }}</span>
                        </div>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <Link
                        v-if="isEdit"
                        :href="route('email-templates.logs', template.slug)"
                        class="inline-flex h-9 items-center gap-1.5 rounded-md border border-slate-200 bg-white px-3 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200 dark:hover:bg-slate-600"
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                        </svg>
                        Logs
                    </Link>
                    <button
                        v-if="isEdit"
                        type="button"
                        class="inline-flex h-9 items-center gap-1.5 rounded-md border border-slate-200 bg-white px-3 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200 dark:hover:bg-slate-600"
                        @click="showTestModal = true"
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12l-2 9 20-9L3 3l2 9zm0 0h9" />
                        </svg>
                        Send test
                    </button>
                    <button
                        type="button"
                        class="inline-flex h-9 items-center gap-1.5 rounded-md bg-indigo-600 px-3 text-sm font-medium text-white shadow-sm transition hover:bg-indigo-700 disabled:opacity-60"
                        :disabled="form.processing"
                        @click="submit"
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        {{ form.processing ? 'Saving…' : isEdit ? 'Save changes' : 'Create template' }}
                    </button>
                </div>
            </div>
        </template>

        <div class="mx-auto max-w-[110rem] px-4 py-5 sm:px-6 lg:px-8">
            <!-- Top-of-page validation summary. Inertia returns nested errors
                 (e.g. `variables.0.key`) that aren't always visible inline if
                 the variable palette is collapsed — surface them here so
                 the save button never feels like it silently failed. -->
            <div
                v-if="Object.keys(form.errors).length"
                class="mb-4 rounded-md border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700 dark:border-rose-700 dark:bg-rose-900/30 dark:text-rose-300"
            >
                <div class="font-semibold">Couldn't save the template — please review the highlighted fields:</div>
                <ul class="mt-1 list-disc pl-5 text-xs">
                    <li v-for="(msg, field) in form.errors" :key="field">
                        <span class="font-mono">{{ field }}</span>: {{ msg }}
                    </li>
                </ul>
            </div>

            <form class="grid grid-cols-12 gap-4" @submit.prevent="submit">
                <!-- Left + middle: metadata + editor + preview -->
                <div class="col-span-12 space-y-4 xl:col-span-9">
                    <!-- Metadata card -->
                    <div class="rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800">
                        <div class="border-b border-slate-200 px-4 py-3 dark:border-slate-700">
                            <div class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                Metadata
                            </div>
                        </div>
                        <div class="grid grid-cols-1 gap-3 p-4 sm:grid-cols-2">
                            <div>
                                <label class="text-xs font-medium text-slate-600 dark:text-slate-300">Template name *</label>
                                <input
                                    v-model="form.template_name"
                                    type="text"
                                    class="mt-1 w-full rounded-md border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100"
                                    :class="{ 'border-rose-300 focus:border-rose-500 focus:ring-rose-500': form.errors.template_name }"
                                />
                                <p v-if="form.errors.template_name" class="mt-1 text-xs text-rose-600">
                                    {{ form.errors.template_name }}
                                </p>
                            </div>
                            <div>
                                <label class="text-xs font-medium text-slate-600 dark:text-slate-300">
                                    Template key
                                    <span class="font-normal text-slate-400">(used by application code)</span>
                                </label>
                                <input
                                    v-model="form.template_key"
                                    type="text"
                                    placeholder="e.g. welcome.user"
                                    class="mt-1 w-full rounded-md border-slate-300 bg-white px-3 py-2 font-mono text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100"
                                    :class="{ 'border-rose-300 focus:border-rose-500 focus:ring-rose-500': form.errors.template_key }"
                                    :disabled="form.is_system_template"
                                />
                                <p v-if="form.errors.template_key" class="mt-1 text-xs text-rose-600">
                                    {{ form.errors.template_key }}
                                </p>
                            </div>

                            <div class="sm:col-span-2">
                                <label class="text-xs font-medium text-slate-600 dark:text-slate-300">Subject *</label>
                                <input
                                    v-model="form.subject"
                                    type="text"
                                    class="mt-1 w-full rounded-md border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100"
                                    :class="{ 'border-rose-300 focus:border-rose-500 focus:ring-rose-500': form.errors.subject }"
                                />
                                <p v-if="form.errors.subject" class="mt-1 text-xs text-rose-600">
                                    {{ form.errors.subject }}
                                </p>
                                <p class="mt-1 text-[11px] text-slate-400">
                                    Placeholders like <span class="font-mono">{{ exampleToken }}</span> are resolved at send-time.
                                </p>
                            </div>

                            <div>
                                <label class="text-xs font-medium text-slate-600 dark:text-slate-300">Category *</label>
                                <select
                                    v-model="form.category"
                                    class="mt-1 w-full rounded-md border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100"
                                >
                                    <option v-for="c in options.categories" :key="c.value" :value="c.value">
                                        {{ c.label }}
                                    </option>
                                </select>
                                <div class="mt-1 inline-block">
                                    <span
                                        class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase"
                                        :class="categoryClasses[categoryColor(form.category)] ?? categoryClasses.slate"
                                    >
                                        Preview · {{ options.categories.find((c) => c.value === form.category)?.label }}
                                    </span>
                                </div>
                            </div>
                            <div>
                                <label class="text-xs font-medium text-slate-600 dark:text-slate-300">Status</label>
                                <select
                                    v-model="form.status"
                                    class="mt-1 w-full rounded-md border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100"
                                >
                                    <option v-for="s in options.statuses" :key="s.value" :value="s.value">
                                        {{ s.label }}
                                    </option>
                                </select>
                            </div>

                            <div class="sm:col-span-2">
                                <label class="text-xs font-medium text-slate-600 dark:text-slate-300">Description</label>
                                <textarea
                                    v-model="form.description"
                                    rows="2"
                                    class="mt-1 w-full rounded-md border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100"
                                    placeholder="Internal description — what triggers this email?"
                                />
                            </div>
                        </div>

                        <!-- Sender + recipients -->
                        <div class="grid grid-cols-1 gap-3 border-t border-slate-200 p-4 dark:border-slate-700 sm:grid-cols-2">
                            <div>
                                <label class="text-xs font-medium text-slate-600 dark:text-slate-300">From name</label>
                                <input
                                    v-model="form.email_from_name"
                                    type="text"
                                    placeholder="Kanban"
                                    class="mt-1 w-full rounded-md border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100"
                                />
                            </div>
                            <div>
                                <label class="text-xs font-medium text-slate-600 dark:text-slate-300">From email</label>
                                <input
                                    v-model="form.email_from_email"
                                    type="email"
                                    placeholder="hello@kanban.app"
                                    class="mt-1 w-full rounded-md border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100"
                                />
                            </div>
                            <div>
                                <label class="text-xs font-medium text-slate-600 dark:text-slate-300">Reply-To</label>
                                <input
                                    v-model="form.reply_to"
                                    type="email"
                                    class="mt-1 w-full rounded-md border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100"
                                />
                            </div>
                            <div>
                                <label class="text-xs font-medium text-slate-600 dark:text-slate-300">Locale</label>
                                <input
                                    v-model="form.locale"
                                    type="text"
                                    placeholder="en"
                                    class="mt-1 w-full rounded-md border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100"
                                />
                            </div>
                            <div class="sm:col-span-2">
                                <label class="text-xs font-medium text-slate-600 dark:text-slate-300">
                                    CC <span class="font-normal text-slate-400">(comma-separated)</span>
                                </label>
                                <input
                                    v-model="ccText"
                                    type="text"
                                    placeholder="manager@example.com, ops@example.com"
                                    class="mt-1 w-full rounded-md border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100"
                                />
                            </div>
                            <div class="sm:col-span-2">
                                <label class="text-xs font-medium text-slate-600 dark:text-slate-300">
                                    BCC <span class="font-normal text-slate-400">(comma-separated)</span>
                                </label>
                                <input
                                    v-model="bccText"
                                    type="text"
                                    placeholder="audit@example.com"
                                    class="mt-1 w-full rounded-md border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Editor + Preview -->
                    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                        <div>
                            <div class="mb-1.5 flex items-center justify-between">
                                <label class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                    Email body
                                </label>
                                <span class="text-[11px] text-slate-400">
                                    HTML output is wrapped in the branded email layout.
                                </span>
                            </div>
                            <RichTextEditor
                                ref="editorRef"
                                v-model="form.body_content"
                                min-height="420px"
                                placeholder="Hi {{ user_name }} — write your email…"
                            />
                            <p v-if="form.errors.body_content" class="mt-1 text-xs text-rose-600">
                                {{ form.errors.body_content }}
                            </p>

                            <div class="mt-3">
                                <label class="text-xs font-medium text-slate-600 dark:text-slate-300">
                                    Plain-text fallback
                                    <span class="font-normal text-slate-400">(optional)</span>
                                </label>
                                <textarea
                                    v-model="form.plain_text"
                                    rows="4"
                                    placeholder="Plain-text version delivered to clients that block HTML."
                                    class="mt-1 w-full rounded-md border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100"
                                />
                            </div>
                        </div>

                        <div class="min-h-[480px]">
                            <PreviewPanel
                                :subject="preview.subject"
                                :body="preview.body"
                                :from-name="form.email_from_name"
                                :from-email="form.email_from_email"
                                :loading="preview.loading"
                            />
                        </div>
                    </div>
                </div>

                <!-- Right sidebar: variables -->
                <div class="col-span-12 xl:col-span-3">
                    <VariablePalette
                        :system-variables="options.system_variables"
                        :template-variables="form.variables"
                        @insert="onInsertVariable"
                        @update:template-variables="(next) => (form.variables = next)"
                    />
                </div>
            </form>
        </div>

        <SendTestModal
            v-if="showTestModal && isEdit"
            :show="showTestModal"
            :template="template"
            :system-variables="options.system_variables"
            :template-variables="form.variables"
            @close="showTestModal = false"
        />
    </AuthenticatedLayout>
</template>
