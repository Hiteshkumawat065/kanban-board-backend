<script setup>
import { computed, ref } from 'vue';

// =============================================================================
//  PreviewPanel.vue
//
//  Live email preview rendered inside an isolated <iframe>. The iframe is
//  important — email-side CSS uses generic selectors (body, table, td) that
//  would otherwise stomp the admin UI. Sandboxing inside an iframe also
//  matches how email clients render HTML.
//
//  Two viewport presets:
//   - desktop (600px wide, typical email rendering width)
//   - mobile  (375px wide, iPhone-ish)
//
//  The component is intentionally dumb: the parent Form precomputes the
//  rendered subject + body (either locally via the system-variable map
//  or — for the highest fidelity — by POSTing to /preview on the backend)
//  and we just paint it.
// =============================================================================

const props = defineProps({
    subject: { type: String, default: '' },
    body: { type: String, default: '' },
    fromName: { type: String, default: '' },
    fromEmail: { type: String, default: '' },
    to: { type: String, default: 'recipient@example.com' },
    loading: { type: Boolean, default: false },
});

const viewport = ref('desktop');

// Wrap the rendered body in a minimal HTML shell that mirrors the actual
// outgoing email layout. We embed the same gradient header + footer the
// real `emails.layout.blade.php` produces so the author sees a faithful
// representation of what recipients will get.
const documentHtml = computed(() => `
<!doctype html>
<html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<style>
body { margin:0; padding:24px; background:#f1f5f9; font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif; color:#0f172a; }
.preview-shell { max-width: ${viewport.value === 'mobile' ? '375px' : '600px'}; margin: 0 auto; background:#ffffff; border-radius:16px; overflow:hidden; box-shadow: 0 4px 24px rgba(15,23,42,0.08); }
.preview-header { background: linear-gradient(135deg,#4f46e5 0%,#7c3aed 100%); color:#fff; padding:24px 28px; }
.preview-header h1 { margin:0; font-size:20px; }
.preview-header p { margin:4px 0 0; font-size:12px; opacity:0.85; }
.preview-body { padding:24px 28px; font-size:14px; line-height:1.6; }
.preview-body p { margin:0 0 14px; }
.preview-body h1 { font-size:20px; }
.preview-body h2 { font-size:17px; }
.preview-body a { color:#4f46e5; }
.preview-body .btn { display:inline-block; background:#4f46e5; color:#fff; padding:10px 18px; border-radius:8px; text-decoration:none; font-weight:600; font-size:13px; }
.preview-body .card { background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:14px 16px; margin:12px 0; }
.preview-footer { background:#f8fafc; border-top:1px solid #e2e8f0; padding:18px 28px; color:#64748b; font-size:11px; text-align:center; }
</style></head>
<body>
    <div class="preview-shell">
        <div class="preview-header">
            <h1>${escape(props.fromName || 'Kanban')}</h1>
            <p>${escape(props.subject || '(no subject)')}</p>
        </div>
        <div class="preview-body">${props.body || '<em style="color:#94a3b8">Your email body preview will appear here.</em>'}</div>
        <div class="preview-footer">
            From ${escape(props.fromName || 'Kanban')}${props.fromEmail ? ' &lt;'+escape(props.fromEmail)+'&gt;' : ''} · To ${escape(props.to)}
        </div>
    </div>
</body></html>
`);

function escape(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}
</script>

<template>
    <div class="flex h-full flex-col rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800">
        <div class="flex items-center justify-between border-b border-slate-200 px-3 py-2 dark:border-slate-700">
            <div>
                <div class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Live preview</div>
                <div class="mt-0.5 truncate text-[11px] text-slate-500 dark:text-slate-400">
                    {{ subject || '(no subject)' }}
                </div>
            </div>
            <div class="inline-flex overflow-hidden rounded-md border border-slate-200 text-xs dark:border-slate-600">
                <button
                    type="button"
                    class="px-2 py-1 transition"
                    :class="viewport === 'desktop' ? 'bg-indigo-600 text-white' : 'bg-white text-slate-600 hover:bg-slate-50 dark:bg-slate-800 dark:text-slate-300'"
                    @click="viewport = 'desktop'"
                    title="Desktop preview"
                >
                    🖥
                </button>
                <button
                    type="button"
                    class="px-2 py-1 transition"
                    :class="viewport === 'mobile' ? 'bg-indigo-600 text-white' : 'bg-white text-slate-600 hover:bg-slate-50 dark:bg-slate-800 dark:text-slate-300'"
                    @click="viewport = 'mobile'"
                    title="Mobile preview"
                >
                    📱
                </button>
            </div>
        </div>
        <div class="relative flex-1 bg-slate-100 dark:bg-slate-900">
            <iframe
                class="h-full w-full border-0"
                sandbox=""
                :srcdoc="documentHtml"
                title="Email preview"
            />
            <div
                v-if="loading"
                class="absolute inset-0 flex items-center justify-center bg-white/60 backdrop-blur-sm dark:bg-slate-900/60"
            >
                <div class="rounded-md bg-white px-3 py-1.5 text-xs font-medium text-slate-600 shadow dark:bg-slate-800 dark:text-slate-200">
                    Refreshing preview…
                </div>
            </div>
        </div>
    </div>
</template>
