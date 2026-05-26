import { reactive, computed, onUnmounted } from 'vue';

/**
 * Reactive Kanban board store.
 *
 * Responsibilities:
 *   - Holds normalized board state (lists + cards) reactively.
 *   - Talks to the Laravel /api/v1 backend with axios (Sanctum SPA cookie auth).
 *   - Subscribes to the private `board.{id}` Echo channel for realtime sync
 *     from other users (CardCreated / CardUpdated / CardMoved / CardDeleted).
 *
 * One instance per Board page; call dispose() (or rely on onUnmounted in the
 * page component) to leave the channel cleanly.
 */
export function useBoardStore(initialBoard) {
    const state = reactive({
        // Deep-clone the Inertia prop (plain JSON) to break it free of Vue's
        // initial reactive proxy from the parent page, then re-wrap with our
        // own `reactive()`. structuredClone() can't traverse Vue proxies.
        board: JSON.parse(JSON.stringify(initialBoard)),
        loading: false,
        error: null,
    });

    const lists = computed(() => state.board?.lists ?? []);

    function findList(listId) {
        return lists.value.find((l) => l.id === listId) ?? null;
    }

    function findCard(cardId) {
        for (const list of lists.value) {
            const cards = list.cards ?? [];
            const card = cards.find((c) => c.id === cardId);
            if (card) return { card, list };
        }
        return { card: null, list: null };
    }

    function removeCardLocally(cardId) {
        for (const list of lists.value) {
            const cards = list.cards ?? [];
            const idx = cards.findIndex((c) => c.id === cardId);
            if (idx !== -1) {
                cards.splice(idx, 1);
                return;
            }
        }
    }

    function insertCardLocally(card, listId, index = null) {
        const list = findList(listId);
        if (!list) return;
        if (!Array.isArray(list.cards)) list.cards = [];
        const cards = list.cards;
        if (index === null || index >= cards.length) cards.push(card);
        else cards.splice(index, 0, card);
    }

    function replaceCardLocally(card) {
        for (const list of lists.value) {
            const cards = list.cards ?? [];
            const idx = cards.findIndex((c) => c.id === card.id);
            if (idx !== -1) {
                cards.splice(idx, 1, { ...cards[idx], ...card });
                return;
            }
        }
    }

    // --- API: Lists ---------------------------------------------------------

    async function createList(name) {
        const { data } = await window.axios.post(
            `/api/v1/boards/${state.board.id}/lists`,
            { name },
        );
        lists.value.push({ ...data.data, cards: [] });
        return data.data;
    }

    async function renameList(listId, name) {
        const list = findList(listId);
        if (!list) return;
        const prev = list.name;
        list.name = name;
        try {
            await window.axios.patch(`/api/v1/lists/${listId}`, { name });
        } catch (e) {
            list.name = prev;
            throw e;
        }
    }

    async function deleteList(listId) {
        const idx = lists.value.findIndex((l) => l.id === listId);
        if (idx === -1) return;
        const [removed] = lists.value.splice(idx, 1);
        try {
            await window.axios.delete(`/api/v1/lists/${listId}`);
        } catch (e) {
            lists.value.splice(idx, 0, removed);
            throw e;
        }
    }

    // --- API: Cards ---------------------------------------------------------

    async function createCard(listId, payload) {
        const { data } = await window.axios.post(
            `/api/v1/lists/${listId}/cards`,
            payload,
        );
        insertCardLocally(data.data, listId);
        return data.data;
    }

    async function updateCard(cardId, payload) {
        const { card } = findCard(cardId);
        if (!card) return;
        const snapshot = { ...card };
        Object.assign(card, payload);
        try {
            const { data } = await window.axios.patch(
                `/api/v1/cards/${cardId}`,
                payload,
            );
            replaceCardLocally(data.data);
            return data.data;
        } catch (e) {
            Object.assign(card, snapshot);
            throw e;
        }
    }

    async function deleteCard(cardId) {
        const { card, list } = findCard(cardId);
        if (!card) return;
        const cards = list.cards;
        const idx = cards.findIndex((c) => c.id === cardId);
        const snapshot = cards.splice(idx, 1)[0];
        try {
            await window.axios.delete(`/api/v1/cards/${cardId}`);
        } catch (e) {
            cards.splice(idx, 0, snapshot);
            throw e;
        }
    }

    /**
     * Persist a drag-drop move. The UI has already mutated the arrays
     * optimistically (vue-draggable mutates `v-model`), so we only need
     * to tell the server the new (list_id, position) and reconcile on error.
     *
     * The server may also mutate the card itself (e.g. on entering UAT it
     * resets uat_status to "pending"), so we merge the returned card back
     * into local state.
     */
    async function moveCard(cardId, toListId, newIndex) {
        try {
            const { data } = await window.axios.patch(
                `/api/v1/cards/${cardId}/move`,
                {
                    list_id: toListId,
                    position: newIndex,
                },
            );
            if (data?.data) replaceCardLocally(data.data);
            return data?.data ?? null;
        } catch (e) {
            state.error =
                e?.response?.data?.errors?.list_id?.[0] ||
                e?.response?.data?.message ||
                'Failed to move card. Reload to resync.';
            throw e;
        }
    }

    // --- API: Assignees -----------------------------------------------------

    async function assignUser(cardId, userId) {
        const { data } = await window.axios.post(
            `/api/v1/cards/${cardId}/assignees`,
            { user_id: userId },
        );
        replaceCardLocally(data.data);
        return data.data;
    }

    async function unassignUser(cardId, userId) {
        const { data } = await window.axios.delete(
            `/api/v1/cards/${cardId}/assignees/${userId}`,
        );
        replaceCardLocally(data.data);
        return data.data;
    }

    // --- API: UAT actions ---------------------------------------------------

    async function approveUat(cardId) {
        const { data } = await window.axios.post(
            `/api/v1/cards/${cardId}/uat/approve`,
        );
        replaceCardLocally(data.data);
        return data.data;
    }

    async function requestRework(cardId, reason) {
        const { data } = await window.axios.post(
            `/api/v1/cards/${cardId}/uat/rework`,
            { reason: reason ?? null },
        );
        // The card moves to To Do server-side; let the realtime CardMoved
        // event resync its position. We just refresh its attributes here.
        replaceCardLocally(data.data);
        return data.data;
    }

    // --- Realtime (Reverb / Pusher protocol via Laravel Echo) ---------------

    let channel = null;

    function subscribe() {
        if (!window.Echo || !state.board?.id) return;
        channel = window.Echo.private(`board.${state.board.id}`);

        channel.listen('.CardCreated', (e) => {
            if (!findCard(e.card.id).card) {
                insertCardLocally(e.card, e.card.list_id);
            }
        });

        channel.listen('.CardUpdated', (e) => {
            replaceCardLocally(e.card);
        });

        channel.listen('.CardMoved', (e) => {
            const { card } = findCard(e.card_id);
            if (!card) return;
            removeCardLocally(e.card_id);
            insertCardLocally(
                { ...card, list_id: e.to_list_id, position: e.position },
                e.to_list_id,
            );
        });

        channel.listen('.CardDeleted', (e) => {
            removeCardLocally(e.card_id);
        });
    }

    function dispose() {
        if (channel && window.Echo) {
            window.Echo.leave(`board.${state.board.id}`);
            channel = null;
        }
    }

    onUnmounted(dispose);

    return {
        state,
        lists,
        // lists
        createList,
        renameList,
        deleteList,
        // cards
        createCard,
        updateCard,
        deleteCard,
        moveCard,
        // assignees
        assignUser,
        unassignUser,
        // mentor UAT actions
        approveUat,
        requestRework,
        // realtime
        subscribe,
        dispose,
    };
}
