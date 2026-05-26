<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AssignCardRequest;
use App\Http\Requests\MoveCardRequest;
use App\Http\Requests\RequestReworkRequest;
use App\Http\Requests\StoreCardRequest;
use App\Http\Requests\UpdateCardRequest;
use App\Http\Resources\CardResource;
use App\Models\BoardList;
use App\Models\Card;
use App\Models\User;
use App\Services\CardService;
use Illuminate\Http\JsonResponse;

final class CardController extends Controller
{
    public function __construct(private readonly CardService $service) {}

    public function store(StoreCardRequest $request, BoardList $list): JsonResponse
    {
        $this->authorize('update', $list->board);

        $card = $this->service->create($list, $request->user(), $request->validated());

        return (new CardResource($card))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Card $card): CardResource
    {
        $this->authorize('view', $card);

        $card->load([
            'assignees',
            'labels',
            'comments.author',
            'checklists.items',
            'attachments.uploader',
        ]);

        return new CardResource($card);
    }

    public function update(UpdateCardRequest $request, Card $card): CardResource
    {
        $this->authorize('update', $card);
        $card = $this->service->update($card, $request->validated());

        return new CardResource($card);
    }

    public function move(MoveCardRequest $request, Card $card): CardResource
    {
        $this->authorize('update', $card);

        $card = $this->service->move(
            $card,
            (int) $request->integer('list_id'),
            (int) $request->integer('position'),
            $request->user(),
        );

        return new CardResource($card->load('assignees'));
    }

    public function destroy(Card $card): JsonResponse
    {
        $this->authorize('delete', $card);
        $this->service->delete($card);

        return response()->json(['message' => 'Card deleted']);
    }

    public function assign(AssignCardRequest $request, Card $card): CardResource
    {
        $this->authorize('update', $card);

        /** @var User $user */
        $user = User::findOrFail((int) $request->integer('user_id'));

        $card = $this->service->assign($card, $user, $request->user());

        return new CardResource($card);
    }

    public function unassign(Card $card, User $user): CardResource
    {
        $this->authorize('update', $card);

        $card = $this->service->unassign($card, $user);

        return new CardResource($card);
    }

    public function approveUat(Card $card): CardResource
    {
        $this->authorize('approveUat', $card);

        $card = $this->service->approveUat($card, request()->user());

        return new CardResource($card);
    }

    public function requestRework(RequestReworkRequest $request, Card $card): CardResource
    {
        $this->authorize('approveUat', $card);

        $card = $this->service->requestRework(
            $card,
            $request->user(),
            $request->input('reason'),
        );

        return new CardResource($card);
    }
}
