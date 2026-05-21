<?php

use App\Http\Controllers\BoardPageController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\WorkspacePageController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (Auth::check()) {
        return redirect()->route('dashboard');
    }

    return redirect()->route('login');
});

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware(['auth', 'verified'])->group(function () {
    // Workspace listing — top-level "Your Workspaces" page.
    Route::get('/workspaces', [WorkspacePageController::class, 'index'])->name('workspaces.index');

    // Board listing — the boards belonging to a specific workspace. The URL
    // is nested under the workspace because a board listing only makes
    // sense in a workspace context.
    Route::get('/workspaces/{workspace}/boards', [BoardPageController::class, 'index'])->name('boards.index');

    // Kanban board — the actual task list with Backlog / To Do columns.
    Route::get('/boards/{board}', [BoardPageController::class, 'show'])->name('boards.show');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
