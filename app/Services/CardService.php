<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ActivityAction;
use App\Enums\CardPriority;
use App\Enums\ListStage;
use App\Enums\UatStatus;
use App\Events\CardCreated;
use App\Events\CardDeleted;
use App\Events\CardMoved;
use App\Events\CardUpdated;
use App\Models\Activity;
use App\Models\BoardList;
use App\Models\Card;
use App\Models\User;
use App\Notifications\CardCompletedNotification;
use App\Notifications\CardStageChangedNotification;
use App\Notifications\CardUatApprovedNotification;
use App\Notifications\CardUatReworkNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

final class CardService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(BoardList $list, User $user, array $data): Card
    {
        return DB::transaction(function () use ($list, $user, $data) {
            $position = PositionCalculator::append($list->cards()->get());

            $card = Card::create([
                'list_id' => $list->id,
                'board_id' => $list->board_id,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'due_date' => $data['due_date'] ?? null,
                'position' => $position,
                'created_by' => $user->id,
            ]);

            Activity::log(
                ActivityAction::CardCreated,
                $card,
                ['title' => $card->title],
                workspaceId: $list->board->workspace_id,
                boardId: $card->board_id,
                cardId: $card->id,
            );

            broadcast(new CardCreated($card))->toOthers();

            return $card;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Card $card, array $data): Card
    {
        return DB::transaction(function () use ($card, $data) {
            $original = $card->only(array_keys($data));
            $card->update($data);

            Activity::log(
                ActivityAction::CardUpdated,
                $card,
                ['changed' => array_keys($data), 'before' => $original],
                workspaceId: $card->board->workspace_id,
                boardId: $card->board_id,
                cardId: $card->id,
            );

            broadcast(new CardUpdated($card->fresh(['assignees'])))->toOthers();

            return $card->fresh(['assignees']);
        });
    }

    /**
     * Move a card to another list (or reorder within the same list).
     *
     * Side effects:
     *  - Logs activity, broadcasts CardMoved (for realtime sync).
     *  - On a *stage transition*, dispatches workflow notifications:
     *      backlog -> todo      => mentor of each assignee
     *      todo -> in_progress  => mentor of each assignee
     *      in_progress -> uat   => mentor of each assignee + sets uat_status=pending
     *      uat -> done          => mentor of each assignee (only allowed if approved)
     *  - Moving from rework state to in_progress clears the rework flag.
     *
     * @throws ValidationException when moving from UAT to a done-stage list
     *                             without a prior mentor approval.
     */
    public function move(Card $card, int $newListId, int $targetIndex, ?User $actor = null): Card
    {
        return DB::transaction(function () use ($card, $newListId, $targetIndex, $actor) {
            $fromList = $card->list;
            $fromListId = $card->list_id;

            $targetList = BoardList::where('board_id', $card->board_id)
                ->where('id', $newListId)
                ->firstOrFail();

            $fromStage = $fromList?->stage;
            $toStage = $targetList->stage;

            $this->guardStageTransition($card, $fromStage, $toStage);

            $siblings = $targetList->cards()
                ->where('id', '!=', $card->id)
                ->get();

            $attrs = [
                'list_id' => $targetList->id,
                'position' => PositionCalculator::between($siblings, $targetIndex),
            ];

            // Workflow side-effects on the card itself based on stage entry.
            if ($toStage !== null && $fromStage !== $toStage) {
                $attrs = array_merge($attrs, $this->stageEntrySideEffects($card, $fromStage, $toStage));
            }

            $card->update($attrs);

            Activity::log(
                ActivityAction::CardMoved,
                $card,
                ['from_list' => $fromListId, 'to_list' => $newListId],
                workspaceId: $card->board->workspace_id,
                boardId: $card->board_id,
                cardId: $card->id,
            );

            broadcast(new CardMoved($card->fresh(['assignees']), $fromListId))->toOthers();

            $fresh = $card->fresh(['assignees']);

            if ($fromStage !== $toStage && $toStage !== null) {
                $this->dispatchStageNotifications($fresh, $fromStage, $toStage, $actor);
            }

            return $fresh;
        });
    }

    /**
     * Mentor approves the card currently sitting in a UAT-stage list.
     * Sends a CardUatApprovedNotification to every assignee.
     */
    public function approveUat(Card $card, User $mentor): Card
    {
        return DB::transaction(function () use ($card, $mentor) {
            if ($card->list?->stage !== ListStage::Uat) {
                throw ValidationException::withMessages([
                    'card' => 'Only cards currently in a UAT-stage list can be approved.',
                ]);
            }

            $card->update([
                'uat_status' => UatStatus::Approved,
                'needs_rework' => false,
                'reviewed_by' => $mentor->id,
                'reviewed_at' => now(),
            ]);

            Activity::log(
                ActivityAction::CardUatApproved,
                $card,
                ['reviewer' => $mentor->id],
                workspaceId: $card->board->workspace_id,
                boardId: $card->board_id,
                cardId: $card->id,
            );

            $fresh = $card->fresh(['assignees']);

            broadcast(new CardUpdated($fresh))->toOthers();

            Notification::send(
                $fresh->assignees,
                new CardUatApprovedNotification($fresh, $mentor),
            );

            return $fresh;
        });
    }

    /**
     * Mentor rejects UAT and requests rework.
     * Moves the card back to the todo-stage list on the same board,
     * flips needs_rework=true, bumps priority to High, and emails the dev.
     */
    public function requestRework(Card $card, User $mentor, ?string $reason = null): Card
    {
        return DB::transaction(function () use ($card, $mentor, $reason) {
            if ($card->list?->stage !== ListStage::Uat) {
                throw ValidationException::withMessages([
                    'card' => 'Only cards currently in a UAT-stage list can be sent back for rework.',
                ]);
            }

            $todoList = BoardList::where('board_id', $card->board_id)
                ->where('stage', ListStage::Todo->value)
                ->whereNull('archived_at')
                ->orderBy('position')
                ->first();

            if (! $todoList) {
                throw ValidationException::withMessages([
                    'card' => 'No To Do list exists on this board to bounce the card back into.',
                ]);
            }

            $fromListId = $card->list_id;

            $card->update([
                'list_id' => $todoList->id,
                'position' => PositionCalculator::append($todoList->cards()->get()),
                'uat_status' => UatStatus::Rework,
                'needs_rework' => true,
                'priority' => CardPriority::High,
                'reviewed_by' => $mentor->id,
                'reviewed_at' => now(),
            ]);

            Activity::log(
                ActivityAction::CardUatRework,
                $card,
                ['reviewer' => $mentor->id, 'reason' => $reason, 'from_list' => $fromListId],
                workspaceId: $card->board->workspace_id,
                boardId: $card->board_id,
                cardId: $card->id,
            );

            $fresh = $card->fresh(['assignees']);

            broadcast(new CardMoved($fresh, $fromListId))->toOthers();
            broadcast(new CardUpdated($fresh))->toOthers();

            Notification::send(
                $fresh->assignees,
                new CardUatReworkNotification($fresh, $mentor, $reason),
            );

            return $fresh;
        });
    }

    /**
     * Assign a user to a card (idempotent — won't duplicate).
     */
    public function assign(Card $card, User $assignee, User $actor): Card
    {
        return DB::transaction(function () use ($card, $assignee, $actor) {
            $card->assignees()->syncWithoutDetaching([
                $assignee->id => [
                    'assigned_by' => $actor->id,
                    'assigned_at' => now(),
                ],
            ]);

            Activity::log(
                ActivityAction::AssigneeAdded,
                $card,
                ['user' => $assignee->id],
                workspaceId: $card->board->workspace_id,
                boardId: $card->board_id,
                cardId: $card->id,
            );

            $fresh = $card->fresh(['assignees']);

            broadcast(new CardUpdated($fresh))->toOthers();

            return $fresh;
        });
    }

    public function unassign(Card $card, User $assignee): Card
    {
        return DB::transaction(function () use ($card, $assignee) {
            $card->assignees()->detach($assignee->id);

            Activity::log(
                ActivityAction::AssigneeRemoved,
                $card,
                ['user' => $assignee->id],
                workspaceId: $card->board->workspace_id,
                boardId: $card->board_id,
                cardId: $card->id,
            );

            $fresh = $card->fresh(['assignees']);

            broadcast(new CardUpdated($fresh))->toOthers();

            return $fresh;
        });
    }

    public function delete(Card $card): void
    {
        DB::transaction(function () use ($card) {
            $card->delete();

            Activity::log(
                ActivityAction::CardArchived,
                $card,
                ['title' => $card->title],
                workspaceId: $card->board->workspace_id,
                boardId: $card->board_id,
                cardId: $card->id,
            );

            broadcast(new CardDeleted($card->id, $card->board_id))->toOthers();
        });
    }

    // ------------------------------------------------------------------
    // Internal helpers
    // ------------------------------------------------------------------

    /**
     * Enforce workflow rules that a transition is allowed.
     *
     * Currently enforced:
     *  - UAT -> Done is only allowed if uat_status === approved.
     */
    private function guardStageTransition(Card $card, ?ListStage $from, ?ListStage $to): void
    {
        if ($from === ListStage::Uat && $to === ListStage::Done
            && $card->uat_status !== UatStatus::Approved
        ) {
            throw ValidationException::withMessages([
                'list_id' => 'Mentor approval is required before this UAT task can be marked done.',
            ]);
        }
    }

    /**
     * Compute the workflow-driven attribute updates when a card enters a stage.
     *
     * @return array<string, mixed>
     */
    private function stageEntrySideEffects(Card $card, ?ListStage $from, ListStage $to): array
    {
        $attrs = [];

        if ($to === ListStage::Uat) {
            // Entering UAT (re)starts the review cycle.
            $attrs['uat_status'] = UatStatus::Pending;
            $attrs['needs_rework'] = false;
            $attrs['reviewed_by'] = null;
            $attrs['reviewed_at'] = null;
        }

        if ($to === ListStage::InProgress && $card->needs_rework) {
            // Dev has re-picked up a rework task — clear the red flag but keep priority.
            $attrs['needs_rework'] = false;
        }

        if ($to === ListStage::Done) {
            $attrs['completed_at'] = now();
        }

        return $attrs;
    }

    /**
     * Dispatch the right notification(s) for a stage transition.
     *
     * @return void
     */
    private function dispatchStageNotifications(Card $card, ?ListStage $from, ListStage $to, ?User $actor): void
    {
        $assignees = $card->assignees;

        if ($assignees->isEmpty()) {
            return;
        }

        // For transitions that notify the mentor: backlog->todo, todo->in_progress, in_progress->uat
        if (in_array($to, [ListStage::Todo, ListStage::InProgress, ListStage::Uat], true)) {
            foreach ($assignees as $developer) {
                $mentor = $developer->mentor;
                if (! $mentor) {
                    continue;
                }
                $mentor->notify(new CardStageChangedNotification(
                    $card,
                    $developer,
                    $from ?? ListStage::Backlog,
                    $to,
                ));
            }
        }

        // For Done transitions: notify each developer's mentor that the work shipped.
        if ($to === ListStage::Done) {
            foreach ($assignees as $developer) {
                $mentor = $developer->mentor;
                if (! $mentor) {
                    continue;
                }
                $mentor->notify(new CardCompletedNotification($card, $developer));
            }
        }
    }
}
