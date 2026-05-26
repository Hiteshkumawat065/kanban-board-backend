<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreListRequest;
use App\Http\Resources\BoardListResource;
use App\Models\Board;
use App\Models\BoardList;
use App\Services\PositionCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ListController extends Controller
{
    public function store(StoreListRequest $request, Board $board): JsonResponse
    {
        $this->authorize('update', $board);

        $position = PositionCalculator::append($board->lists()->get());

        $list = $board->lists()->create([
            ...$request->validated(),
            'position' => $position,
        ]);

        return (new BoardListResource($list))
            ->response()
            ->setStatusCode(201);
    }

    public function update(StoreListRequest $request, BoardList $list): BoardListResource
    {
        $this->authorize('update', $list->board);
        $list->update($request->validated());

        return new BoardListResource($list);
    }

    public function move(Request $request, BoardList $list): BoardListResource
    {
        $this->authorize('update', $list->board);
        $data = $request->validate(['position' => ['required', 'integer', 'min:0']]);

        $siblings = $list->board->lists()
            ->where('id', '!=', $list->id)
            ->orderBy('position')
            ->get();

        $list->update([
            'position' => PositionCalculator::between($siblings, (int) $data['position']),
        ]);

        return new BoardListResource($list);
    }

    public function destroy(BoardList $list): JsonResponse
    {
        $this->authorize('update', $list->board);
        $list->delete();

        return response()->json(['message' => 'List deleted']);
    }
}
