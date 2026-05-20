<script setup>
import { computed, onMounted, ref } from 'vue';
import { useBoardStore } from '@/Composables/useBoardStore';
import List from './List.vue';
import AddListForm from './AddListForm.vue';
import CardModal from './CardModal.vue';

const props = defineProps({
    initialBoard: { type: Object, required: true },
});

const {
    state,
    lists,
    createList,
    createCard,
    updateCard,
    deleteCard,
    moveCard,
    assignUser,
    unassignUser,
    approveUat,
    requestRework,
    subscribe,
} = useBoardStore(props.initialBoard);

onMounted(subscribe);

const activeCardId = ref(null);

// Live reference to the active card pulled from store state so the modal
// reflects updates (assignees added, UAT approved, rework moved, etc.)
// without needing a re-open.
const activeCard = computed(() => {
    if (!activeCardId.value) return null;
    for (const list of lists.value) {
        for (const c of list.cards ?? []) {
            if (c.id === activeCardId.value) return c;
        }
    }
    return null;
});

function openCard(card) {
    activeCardId.value = card.id;
}

function closeCard() {
    activeCardId.value = null;
}

async function handleCardCreated({ listId, payload }) {
    try {
        await createCard(listId, payload);
    } catch (e) {
        alert(extractError(e, 'Failed to create card'));
    }
}

async function handleCardMoved({ card, toListId, newIndex }) {
    try {
        await moveCard(card.id, toListId, newIndex);
    } catch (e) {
        alert(extractError(e, 'Failed to move card'));
    }
}

async function handleCardUpdate({ cardId, payload }) {
    try {
        await updateCard(cardId, payload);
        closeCard();
    } catch (e) {
        alert(extractError(e, 'Failed to update card'));
    }
}

async function handleCardDelete(cardId) {
    try {
        await deleteCard(cardId);
        closeCard();
    } catch (e) {
        alert(extractError(e, 'Failed to delete card'));
    }
}

async function handleAssign({ cardId, userId }) {
    try {
        await assignUser(cardId, userId);
    } catch (e) {
        alert(extractError(e, 'Failed to assign user'));
    }
}

async function handleUnassign({ cardId, userId }) {
    try {
        await unassignUser(cardId, userId);
    } catch (e) {
        alert(extractError(e, 'Failed to unassign user'));
    }
}

async function handleApproveUat(cardId) {
    try {
        await approveUat(cardId);
    } catch (e) {
        alert(extractError(e, 'Failed to approve UAT'));
    }
}

async function handleRequestRework({ cardId, reason }) {
    try {
        await requestRework(cardId, reason);
    } catch (e) {
        alert(extractError(e, 'Failed to send back for rework'));
    }
}

async function handleListCreate(name) {
    try {
        await createList(name);
    } catch (e) {
        alert(extractError(e, 'Failed to create list'));
    }
}

function extractError(e, fallback) {
    return (
        e?.response?.data?.message ||
        Object.values(e?.response?.data?.errors ?? {}).flat().join('\n') ||
        fallback
    );
}
</script>

<template>
    <div class="flex h-full min-h-0 flex-1 gap-3 overflow-x-auto px-4 py-4">
        <List
            v-for="list in lists"
            :key="list.id"
            :list="list"
            @card-moved="handleCardMoved"
            @card-created="handleCardCreated"
            @card-open="openCard"
        />

        <AddListForm @create="handleListCreate" />

        <CardModal
            :card="activeCard"
            :board="state.board"
            :lists="lists"
            @close="closeCard"
            @update="handleCardUpdate"
            @delete="handleCardDelete"
            @assign="handleAssign"
            @unassign="handleUnassign"
            @approve-uat="handleApproveUat"
            @request-rework="handleRequestRework"
        />

        <div
            v-if="state.error"
            class="fixed bottom-4 left-1/2 z-50 -translate-x-1/2 rounded-md bg-rose-600 px-4 py-2 text-sm text-white shadow-lg"
            @click="state.error = null"
        >
            {{ state.error }}
        </div>
    </div>
</template>
