<script setup>
import { computed } from 'vue';
import { VueDraggableNext } from 'vue-draggable-next';
import Card from './Card.vue';
import AddCardForm from './AddCardForm.vue';

const props = defineProps({
    list: { type: Object, required: true },
});

const emit = defineEmits([
    'card-moved',
    'card-created',
    'card-open',
]);

const cards = computed({
    get: () => props.list.cards ?? [],
    set: (value) => {
        props.list.cards = value;
    },
});

/**
 * vue-draggable-next emits `change` with one of:
 *   { added: { element, newIndex } }
 *   { removed: { element, oldIndex } }
 *   { moved:   { element, newIndex, oldIndex } }
 *
 * We only need `added` and `moved` — they correspond to "card landed here".
 * `removed` is a no-op (the receiving list already fires `added`).
 */
function onChange(evt) {
    if (evt.added) {
        emit('card-moved', {
            card: evt.added.element,
            toListId: props.list.id,
            newIndex: evt.added.newIndex,
        });
    } else if (evt.moved) {
        emit('card-moved', {
            card: evt.moved.element,
            toListId: props.list.id,
            newIndex: evt.moved.newIndex,
        });
    }
}

function onCreate(payload) {
    emit('card-created', { listId: props.list.id, payload });
}
</script>

<template>
    <div
        class="flex max-h-full w-72 shrink-0 flex-col self-start rounded-lg bg-slate-100/95 shadow-sm backdrop-blur dark:bg-slate-800/90"
    >
        <header class="flex items-center gap-2 px-3 py-2">
            <h3 class="flex-1 text-sm font-semibold text-slate-700 dark:text-slate-200">
                {{ list.name }}
                <span class="ml-1 text-xs font-normal text-slate-400">{{ cards.length }}</span>
            </h3>
        </header>

        <VueDraggableNext
            :list="cards"
            group="kanban-cards"
            item-key="id"
            class="flex min-h-[20px] flex-1 flex-col gap-2 overflow-y-auto px-2 py-1"
            ghost-class="kanban-ghost"
            drag-class="kanban-drag"
            @change="onChange"
        >
            <Card
                v-for="card in cards"
                :key="card.id"
                :card="card"
                @open="(c) => emit('card-open', c)"
            />
        </VueDraggableNext>

        <AddCardForm :list-id="list.id" @create="onCreate" />
    </div>
</template>

<style>
.kanban-ghost {
    opacity: 0.4;
}

.kanban-drag {
    transform: rotate(2deg);
    box-shadow:
        0 10px 15px -3px rgb(0 0 0 / 0.1),
        0 4px 6px -4px rgb(0 0 0 / 0.1);
}
</style>
