<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';

// =============================================================================
//  RichTextEditor.vue
//
//  A self-contained, dependency-free contenteditable editor tuned for
//  authoring HTML email bodies. It uses document.execCommand under the hood
//  which is deprecated-but-universally-supported and Good Enough for the
//  authoring surface we need (bold/italic/lists/links/headings/code/quote).
//
//  Why not pull in CKEditor / TinyMCE / Quill?
//    - Each adds 200KB+ to the bundle and needs a separate license dance.
//    - The email body is plain HTML — we don't need image-drag, tables, etc.
//    - Drop-in replacement is trivial: this component's surface is
//      `v-model` + `placeholder`. Swapping internals later won't ripple.
//
//  The component emits `update:modelValue` whenever the HTML changes so
//  the parent Form keeps its reactive state in sync, and exposes the
//  current selection via `focus()` so the variable palette can insert
//  tokens at the cursor.
// =============================================================================

const props = defineProps({
    modelValue: { type: String, default: '' },
    placeholder: { type: String, default: 'Start writing your email…' },
    minHeight: { type: String, default: '320px' },
});

const emit = defineEmits(['update:modelValue']);

const editorRef = ref(null);
const linkUrl = ref('');
const linkModalOpen = ref(false);
const savedRange = ref(null);

// -----------------------------------------------------------------------------
// Initial mount — write the initial HTML into the contenteditable surface
// once, then let user edits drive the value going forward.
// -----------------------------------------------------------------------------
onMounted(() => {
    if (editorRef.value) {
        editorRef.value.innerHTML = props.modelValue || '';
    }
});

// Keep the editor in sync when the parent swaps the v-model from outside
// (e.g. when the user clicks "Reset" or loads a different template). We
// only write when the value differs to avoid clobbering the user's cursor
// position during normal typing.
watch(
    () => props.modelValue,
    (next) => {
        if (editorRef.value && (next || '') !== editorRef.value.innerHTML) {
            editorRef.value.innerHTML = next || '';
        }
    },
);

function emitChange() {
    if (!editorRef.value) return;
    emit('update:modelValue', editorRef.value.innerHTML);
}

// -----------------------------------------------------------------------------
// Selection bookkeeping. The variable palette + link dialog both lose focus
// from the editor — we stash the active range before opening them and
// restore it before running execCommand so insertions land at the right spot.
// -----------------------------------------------------------------------------
function saveSelection() {
    const sel = window.getSelection();
    if (sel && sel.rangeCount > 0 && editorRef.value?.contains(sel.anchorNode)) {
        savedRange.value = sel.getRangeAt(0).cloneRange();
    }
}

function restoreSelection() {
    if (!savedRange.value) {
        editorRef.value?.focus();
        return;
    }
    editorRef.value?.focus();
    const sel = window.getSelection();
    if (sel) {
        sel.removeAllRanges();
        sel.addRange(savedRange.value);
    }
}

function execCommand(cmd, value = null) {
    restoreSelection();
    document.execCommand(cmd, false, value);
    emitChange();
    saveSelection();
}

function setBlock(tag) {
    execCommand('formatBlock', tag);
}

function insertLink() {
    saveSelection();
    linkUrl.value = '';
    linkModalOpen.value = true;
}

function confirmLink() {
    if (linkUrl.value.trim()) {
        execCommand('createLink', linkUrl.value.trim());
    }
    linkModalOpen.value = false;
}

function cancelLink() {
    linkModalOpen.value = false;
}

function unlink() {
    execCommand('unlink');
}

// -----------------------------------------------------------------------------
// Public API — called from the parent Form so the "Insert variable" buttons
// in the sidebar palette can drop tokens at the cursor.
// -----------------------------------------------------------------------------
function insertText(text) {
    restoreSelection();
    document.execCommand('insertText', false, text);
    emitChange();
    saveSelection();
}

function insertHtml(html) {
    restoreSelection();
    document.execCommand('insertHTML', false, html);
    emitChange();
    saveSelection();
}

defineExpose({ insertText, insertHtml, focus: () => editorRef.value?.focus() });

// -----------------------------------------------------------------------------
// Toolbar definition — kept declarative so adding a new command is a
// one-liner. Each item is either a shortcut to execCommand or an inline
// handler.
// -----------------------------------------------------------------------------
const toolbar = [
    { icon: 'B', title: 'Bold', cmd: () => execCommand('bold'), bold: true },
    { icon: 'I', title: 'Italic', cmd: () => execCommand('italic'), italic: true },
    { icon: 'U', title: 'Underline', cmd: () => execCommand('underline'), underline: true },
    { icon: 'S', title: 'Strikethrough', cmd: () => execCommand('strikeThrough'), strike: true },
    { type: 'divider' },
    { icon: 'H1', title: 'Heading 1', cmd: () => setBlock('h1') },
    { icon: 'H2', title: 'Heading 2', cmd: () => setBlock('h2') },
    { icon: 'P', title: 'Paragraph', cmd: () => setBlock('p') },
    { type: 'divider' },
    { icon: '• List', title: 'Bulleted list', cmd: () => execCommand('insertUnorderedList') },
    { icon: '1. List', title: 'Numbered list', cmd: () => execCommand('insertOrderedList') },
    { icon: '“ Quote', title: 'Blockquote', cmd: () => setBlock('blockquote') },
    { icon: '</> Code', title: 'Code', cmd: () => setBlock('pre') },
    { type: 'divider' },
    { icon: '🔗', title: 'Insert link', cmd: insertLink },
    { icon: '✖🔗', title: 'Remove link', cmd: unlink },
    { type: 'divider' },
    { icon: '↺', title: 'Undo', cmd: () => execCommand('undo') },
    { icon: '↻', title: 'Redo', cmd: () => execCommand('redo') },
];

// Save selection continuously while the user is interacting so the
// variable palette can insert into the last known position even after
// the editor loses focus.
function onSelectionChanged() {
    saveSelection();
}

onMounted(() => {
    document.addEventListener('selectionchange', onSelectionChanged);
});

onBeforeUnmount(() => {
    document.removeEventListener('selectionchange', onSelectionChanged);
});
</script>

<template>
    <div
        class="rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800"
    >
        <!-- Toolbar -->
        <div
            class="flex flex-wrap items-center gap-1 border-b border-slate-200 bg-slate-50 px-2 py-1.5 dark:border-slate-700 dark:bg-slate-900/50"
        >
            <template v-for="(item, idx) in toolbar" :key="idx">
                <span
                    v-if="item.type === 'divider'"
                    class="mx-1 h-5 w-px bg-slate-200 dark:bg-slate-700"
                />
                <button
                    v-else
                    type="button"
                    :title="item.title"
                    class="rounded px-2 py-1 text-xs font-semibold text-slate-700 transition hover:bg-slate-200 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-700 dark:hover:text-white"
                    :class="{
                        'font-bold': item.bold,
                        italic: item.italic,
                        underline: item.underline,
                        'line-through': item.strike,
                    }"
                    @mousedown.prevent="item.cmd"
                >
                    {{ item.icon }}
                </button>
            </template>
        </div>

        <!-- Editable surface -->
        <div
            ref="editorRef"
            contenteditable="true"
            class="prose prose-sm max-w-none px-4 py-3 text-slate-800 focus:outline-none dark:prose-invert dark:text-slate-100"
            :style="{ minHeight }"
            :data-placeholder="placeholder"
            @input="emitChange"
            @blur="saveSelection"
            @keyup="saveSelection"
            @mouseup="saveSelection"
        />

        <!-- Inline link dialog -->
        <div
            v-if="linkModalOpen"
            class="border-t border-slate-200 bg-slate-50 px-4 py-3 dark:border-slate-700 dark:bg-slate-900/50"
        >
            <div class="flex flex-wrap items-center gap-2">
                <input
                    v-model="linkUrl"
                    type="url"
                    placeholder="https://example.com"
                    class="flex-1 min-w-[12rem] rounded-md border-slate-300 bg-white px-3 py-1.5 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100"
                    @keydown.enter.prevent="confirmLink"
                />
                <button
                    type="button"
                    class="rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white shadow-sm transition hover:bg-indigo-500"
                    @click="confirmLink"
                >
                    Insert
                </button>
                <button
                    type="button"
                    class="rounded-md border border-slate-200 bg-white px-3 py-1.5 text-sm font-medium text-slate-600 transition hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200"
                    @click="cancelLink"
                >
                    Cancel
                </button>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* contenteditable doesn't render its own placeholder — fake one with a
   ::before that only shows when the editor is empty. */
[contenteditable='true']:empty::before {
    content: attr(data-placeholder);
    color: #94a3b8;
    pointer-events: none;
}

/* Basic typography for the authoring surface so headings / lists actually
   look like headings while you write them. Email-side CSS is what end users
   see — this is just for the editor. */
[contenteditable] :deep(h1) {
    font-size: 1.5rem;
    font-weight: 700;
    margin: 0.5em 0;
}
[contenteditable] :deep(h2) {
    font-size: 1.25rem;
    font-weight: 700;
    margin: 0.5em 0;
}
[contenteditable] :deep(p) {
    margin: 0.4em 0;
}
[contenteditable] :deep(ul),
[contenteditable] :deep(ol) {
    padding-left: 1.5rem;
    margin: 0.4em 0;
}
[contenteditable] :deep(blockquote) {
    border-left: 3px solid #cbd5e1;
    padding-left: 0.75rem;
    color: #475569;
    margin: 0.5em 0;
}
[contenteditable] :deep(pre) {
    background: #f1f5f9;
    color: #0f172a;
    padding: 0.5rem 0.75rem;
    border-radius: 0.375rem;
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    font-size: 0.85rem;
    overflow-x: auto;
}
[contenteditable] :deep(a) {
    color: #4f46e5;
    text-decoration: underline;
}
</style>
