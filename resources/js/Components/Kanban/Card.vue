<script setup>
import { computed, onBeforeUnmount, ref } from 'vue';
import DropdownLink from '@/Components/DropdownLink.vue';

const props = defineProps({
    card: { type: Object, required: true },
});

const emit = defineEmits(['open']);

// Pull the first letter of the first 1–2 name parts ("Harish Kumar" -> "HK").
function initialsOf(name) {
    if (!name) return '?';
    return name
        .split(/\s+/)
        .filter(Boolean)
        .map((w) => w[0])
        .slice(0, 2)
        .join('')
        .toUpperCase();
}

// Deterministic per-user color so each assignee always gets the same chip
// color across renders.
const AVATAR_COLORS = [
    '#2563eb',
    '#9333ea',
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

// Build a fake "@handle" from email local-part, e.g. "kerry.jackman@x.com" -> "kerryjackman".
function handleOf(user) {
    if (!user?.email) return '';
    return '@' + user.email.split('@')[0].replace(/[^a-z0-9]/gi, '').toLowerCase();
}

// ---------------------------------------------------------------------------
// Assignee click-popover. We Teleport it to <body> so the parent list's
// overflow-y: auto doesn't clip it, and position it manually based on the
// avatar's bounding rect. Opens on click, closes when clicking outside or
// pressing Escape — and toggles off when the same avatar is clicked again.
//
// To keep the popover glued to its avatar while the list scrolls (horizontal
// or vertical), we listen for scroll/resize events in the capture phase
// while open, and recompute the position from the live trigger element.
// ---------------------------------------------------------------------------
const activeUser = ref(null);
const popoverPos = ref({ left: 0, top: 0 });
let triggerEl = null;

function updatePos() {
    if (!triggerEl) return;
    const rect = triggerEl.getBoundingClientRect();
    // If the avatar has scrolled completely out of view, close the popover —
    // otherwise it'd float around pointing at empty space.
    if (
        rect.bottom < 0 ||
        rect.top > window.innerHeight ||
        rect.right < 0 ||
        rect.left > window.innerWidth
    ) {
        closeProfile();
        return;
    }
    popoverPos.value = {
        left: rect.right,
        top: rect.bottom + 6,
    };
}

function toggleProfile(event, user) {
    if (activeUser.value?.id === user.id) {
        closeProfile();
        return;
    }
    triggerEl = event.currentTarget;
    activeUser.value = user;
    updatePos();

    // Capture=true so we also pick up scrolling on nested containers
    // (the kanban list has overflow-y/x-auto and scroll events don't bubble).
    window.addEventListener('scroll', updatePos, true);
    window.addEventListener('resize', updatePos);
}

function closeProfile() {
    activeUser.value = null;
    triggerEl = null;
    window.removeEventListener('scroll', updatePos, true);
    window.removeEventListener('resize', updatePos);
}

// Outside-click + Escape — only listen while the popover is open so we don't
// keep dangling listeners on every Card.
function onDocClick(event) {
    if (!activeUser.value) return;
    // Clicks inside the popover or on an avatar already stop propagation,
    // so any click that bubbles to <document> means "outside" → close.
    closeProfile();
}

function onKeydown(event) {
    if (event.key === 'Escape') closeProfile();
}

document.addEventListener('click', onDocClick);
document.addEventListener('keydown', onKeydown);
onBeforeUnmount(() => {
    document.removeEventListener('click', onDocClick);
    document.removeEventListener('keydown', onKeydown);
    window.removeEventListener('scroll', updatePos, true);
    window.removeEventListener('resize', updatePos);
});

const due = computed(() => {
    if (!props.card.due_date) return null;
    const d = new Date(props.card.due_date);
    return d.toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
});

const dueClass = computed(() => {
    if (!props.card.due_date) return '';
    if (props.card.is_completed) return 'bg-emerald-100 text-emerald-800';
    if (props.card.is_overdue) return 'bg-rose-100 text-rose-800';
    return 'bg-slate-100 text-slate-700';
});

const priorityClass = computed(() => {
    switch (props.card.priority) {
        case 'high':
            return 'bg-rose-100 text-rose-700';
        case 'low':
            return 'bg-slate-100 text-slate-600';
        case 'medium':
        default:
            return 'bg-blue-100 text-blue-700';
    }
});

// Outer-card ring/border emphasis for cards that need rework — the user
// wants this visually loud ("red marked high priority") on the To Do list.
const cardClass = computed(() => {
    if (props.card.needs_rework) {
        return 'border-rose-400 ring-2 ring-rose-200 dark:border-rose-500 dark:ring-rose-900/40';
    }
    return 'border-slate-200 dark:border-slate-700';
});
</script>

<!--
    IMPORTANT: this component MUST render a single root element. When it
    rendered as a fragment (the card <div> AND a sibling <Teleport>),
    SortableJS / vue-draggable-next would physically move only the <div>
    between lists while leaving the Teleport's anchor comments behind in
    the source list. Vue's reconciliation then got confused about which
    DOM nodes belonged to the moved component, leaving a "ghost" copy of
    the card visible in the source column until the user refreshed.
    Keep the <Teleport> nested INSIDE the root <div> below so the card
    has exactly one element root.
-->
<template>
    <div
        class="group cursor-pointer rounded-md border bg-white p-3 shadow-sm transition hover:shadow dark:bg-slate-800"
        :class="cardClass"
        @click="emit('open', card)"
    >
        <div
            v-if="card.cover_color"
            class="mb-2 -mx-3 -mt-3 h-2 rounded-t-md"
            :style="{ backgroundColor: card.cover_color }"
        />

        <div v-if="card.needs_rework || card.priority === 'high'" class="mb-2 flex flex-wrap gap-1">
            <span
                v-if="card.needs_rework"
                class="inline-flex items-center gap-1 rounded bg-rose-600 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wider text-white"
            >
                Rework
            </span>
            <span
                v-if="card.priority === 'high'"
                class="inline-flex items-center gap-1 rounded px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wider"
                :class="priorityClass"
            >
                High
            </span>
        </div>

        <div v-if="card.labels?.length" class="mb-2 flex flex-wrap gap-1">
            <span
                v-for="label in card.labels"
                :key="label.id"
                class="h-2 w-10 rounded-full"
                :style="{ backgroundColor: label.color }"
                :title="label.name"
            />
        </div>

        <p class="text-sm font-medium text-slate-900 dark:text-slate-100">
            {{ card.title }}
        </p>

        <div class="mt-2 flex flex-wrap items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
            <span
                v-if="due"
                class="inline-flex items-center gap-1 rounded px-1.5 py-0.5 font-medium"
                :class="dueClass"
            >
                <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
                {{ due }}
            </span>

            <span
                v-if="card.comments_count"
                class="inline-flex items-center gap-1"
                :title="`${card.comments_count} comment${card.comments_count > 1 ? 's' : ''}`"
            >
                <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                </svg>
                {{ card.comments_count }}
            </span>

            <span
                v-if="card.attachments_count"
                class="inline-flex items-center gap-1"
                :title="`${card.attachments_count} attachment${card.attachments_count > 1 ? 's' : ''}`"
            >
                <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 10-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                </svg>
                {{ card.attachments_count }}
            </span>

            <div v-if="card.assignees?.length" class="ml-auto flex -space-x-1.5">
                <span
                    v-for="user in card.assignees.slice(0, 3)"
                    :key="user.id"
                    :title="user.name"
                    class="inline-flex h-6 w-6 cursor-pointer items-center justify-center rounded-full text-[10px] font-semibold text-white ring-2 ring-white transition hover:scale-110 dark:ring-slate-800"
                    :style="{ backgroundColor: colorFor(user.id) }"
                    @click.stop="toggleProfile($event, user)"
                >
                    {{ initialsOf(user.name) }}
                </span>
                <span
                    v-if="card.assignees.length > 3"
                    class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-slate-500 text-[10px] font-semibold text-white ring-2 ring-white dark:ring-slate-800"
                    :title="`+${card.assignees.length - 3} more`"
                >
                    +{{ card.assignees.length - 3 }}
                </span>
            </div>
        </div>

        <!--
            Profile popover — Teleported to <body> so the parent list's overflow
            clipping doesn't hide it. Positioned manually via popoverPos so it
            anchors right-edge-aligned, just below the clicked avatar.
            @click.stop on the wrapper prevents the document-level outside-click
            handler from immediately closing it. Nested INSIDE the card root
            so the component has a single element root (see comment above the
            <template> block for why this matters for drag-and-drop).
        -->
        <Teleport to="body">
        <div
            v-if="activeUser"
            class="fixed z-50 w-64 -translate-x-full overflow-hidden rounded-lg bg-white shadow-xl ring-1 ring-black/5 dark:bg-slate-800 dark:ring-white/10"
            :style="{ left: popoverPos.left + 'px', top: popoverPos.top + 'px' }"
            @click.stop
        >
            <div class="relative">
                <!-- Top COLORED block. pl-20 leaves room for the avatar
                     which is absolutely positioned and straddles the
                     colored / white boundary. -->
                <div
                    class="px-3 pt-8 pb-2 pl-20"
                    :style="{ backgroundColor: colorFor(activeUser.id) }"
                >
                    <div
                        class="truncate text-sm font-semibold leading-tight text-white pl-3"
                    >
                        {{ activeUser.name }}
                    </div>
                    <div
                        v-if="activeUser.email"
                        class="mt-0.5 truncate text-xs leading-tight text-white/80 pl-3"
                    >
                        {{ handleOf(activeUser) }}
                    </div>
                </div>

                <!-- Bottom WHITE block — top padding clears the avatar's
                     lower half, then "View profile" in black. -->
                <div class="px-3 pb-3 pt-8"> 
                    <DropdownLink
                        :href="route('profile.edit')"
                        class="text-xs font-medium text-slate-900 hover:underline dark:text-slate-100"
                        @click="closeProfile"
                    >
                        View profile
                    </DropdownLink>

                </div>

                <!-- Avatar — straddles the colored/white boundary, sitting
                     on top of both blocks with a white ring around it. -->
                <span
                    class="absolute left-4 top-9 inline-flex h-14 w-14 items-center justify-center rounded-full text-lg font-bold text-white ring-4 ring-white dark:ring-slate-800"
                    :style="{ backgroundColor: colorFor(activeUser.id) }"
                >
                    {{ initialsOf(activeUser.name) }}
                </span>

                <!-- Close (X) — top right corner, sits on the colored header
                     so we tint it white. -->
                <button
                    type="button"
                    class="absolute right-1.5 top-1.5 inline-flex h-6 w-6 items-center justify-center rounded-full text-white/80 transition hover:bg-white/20 hover:text-white focus:outline-none"
                    title="Close"
                    aria-label="Close"
                    @click="closeProfile"
                >
                    <svg
                        class="h-4 w-4"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M6 18L18 6M6 6l12 12"
                        />
                    </svg>
                </button>
            </div>
        </div>
        </Teleport>
    </div>
</template>
