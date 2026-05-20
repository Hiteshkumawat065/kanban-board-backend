<?php

use App\Http\Controllers\BoardPageController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\WorkspacePageController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (Auth::check()) {
        return redirect()->route('workspaces.index');
    }

    return redirect()->route('login');
});

Route::get('/dashboard', function () {
    return redirect()->route('workspaces.index');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/workspaces', [WorkspacePageController::class, 'index'])->name('workspaces.index');
    Route::get('/workspaces/{workspace}', [WorkspacePageController::class, 'show'])->name('workspaces.show');
    Route::get('/boards/{board}', [BoardPageController::class, 'show'])->name('boards.show');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
