<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Admin\AdminUserController;
use App\Http\Controllers\Api\Admin\PermissionController;
use App\Http\Controllers\Api\Admin\RoleController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BoardController;
use App\Http\Controllers\Api\CardController;
use App\Http\Controllers\Api\ListController;
use App\Http\Controllers\Api\WorkspaceController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.')->group(function () {
    Route::prefix('auth')->name('auth.')->group(function () {
        Route::post('register', [AuthController::class, 'register'])->name('register');
        Route::post('login', [AuthController::class, 'login'])->name('login');

        Route::middleware('auth:sanctum')->group(function () {
            Route::get('me', [AuthController::class, 'me'])->name('me');
            Route::post('logout', [AuthController::class, 'logout'])->name('logout');
        });
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::apiResource('workspaces', WorkspaceController::class);
        Route::post('workspaces/{workspace}/invite', [WorkspaceController::class, 'invite'])->name('workspaces.invite');

        Route::get('workspaces/{workspace}/boards', [BoardController::class, 'index'])->name('workspaces.boards.index');
        Route::post('workspaces/{workspace}/boards', [BoardController::class, 'store'])->name('workspaces.boards.store');

        // MUST be declared before apiResource('boards') below — otherwise
        // /boards/closed gets matched by the {board} route-model binding
        // and tries to resolve a board with id "closed" (404).
        Route::get('boards/closed', [BoardController::class, 'closed'])->name('boards.closed');

        Route::apiResource('boards', BoardController::class)->except(['index', 'store']);

        Route::post('boards/{board}/lists', [ListController::class, 'store'])->name('boards.lists.store');
        Route::patch('lists/{list}', [ListController::class, 'update'])->name('lists.update');
        Route::patch('lists/{list}/move', [ListController::class, 'move'])->name('lists.move');
        Route::delete('lists/{list}', [ListController::class, 'destroy'])->name('lists.destroy');

        Route::post('lists/{list}/cards', [CardController::class, 'store'])->name('lists.cards.store');
        Route::get('cards/{card}', [CardController::class, 'show'])->name('cards.show');
        Route::patch('cards/{card}', [CardController::class, 'update'])->name('cards.update');
        Route::patch('cards/{card}/move', [CardController::class, 'move'])->name('cards.move');
        Route::delete('cards/{card}', [CardController::class, 'destroy'])->name('cards.destroy');

        // Assignment management
        Route::post('cards/{card}/assignees', [CardController::class, 'assign'])->name('cards.assignees.store');
        Route::delete('cards/{card}/assignees/{user}', [CardController::class, 'unassign'])->name('cards.assignees.destroy');

        // Mentor UAT actions
        Route::post('cards/{card}/uat/approve', [CardController::class, 'approveUat'])->name('cards.uat.approve');
        Route::post('cards/{card}/uat/rework', [CardController::class, 'requestRework'])->name('cards.uat.rework');

        // -------------------------------------------------------------
        // RBAC admin surface. Each sub-group requires the matching
        // permission via our EnsurePermission middleware alias.
        // Super Admins bypass via Gate::before so they always pass.
        // -------------------------------------------------------------
        Route::prefix('admin')->name('admin.')->group(function (): void {
            Route::middleware('permission:roles.manage')->group(function (): void {
                Route::apiResource('roles', RoleController::class);
                Route::put('roles/{role}/permissions', [RoleController::class, 'syncPermissions'])
                    ->name('roles.permissions.sync');
            });

            Route::middleware('permission:permissions.manage')->group(function (): void {
                Route::get('permissions', [PermissionController::class, 'index'])->name('permissions.index');
                Route::post('permissions', [PermissionController::class, 'store'])->name('permissions.store');
                Route::delete('permissions/{permission}', [PermissionController::class, 'destroy'])->name('permissions.destroy');
            });

            Route::middleware('permission:users.manage')->group(function (): void {
                Route::get('users', [AdminUserController::class, 'index'])->name('users.index');
                Route::get('users/{user}', [AdminUserController::class, 'show'])->name('users.show');
                Route::put('users/{user}/roles', [AdminUserController::class, 'syncRoles'])->name('users.roles.sync');
                Route::put('users/{user}/workspaces', [AdminUserController::class, 'syncWorkspaces'])->name('users.workspaces.sync');
                Route::put('users/{user}/boards', [AdminUserController::class, 'syncBoards'])->name('users.boards.sync');
            });
        });
    });
});
