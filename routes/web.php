<?php

declare(strict_types=1);

use App\Http\Controllers\BoardPageController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmailTemplateController;
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

// -------------------------------------------------------------------------
// Email Templates module — admin CRUD + preview/test send/logs. Mounted
// at /email-templates as a top-level resource.
// -------------------------------------------------------------------------
Route::middleware(['auth', 'verified'])->name('email-templates.')->group(function () {
    Route::get('email-templates', [EmailTemplateController::class, 'index'])->name('index');
    Route::get('email-templates/create', [EmailTemplateController::class, 'create'])->name('create');
    Route::post('email-templates', [EmailTemplateController::class, 'store'])->name('store');
    Route::get('email-templates/{email_template}/edit', [EmailTemplateController::class, 'edit'])->name('edit');
    Route::put('email-templates/{email_template}', [EmailTemplateController::class, 'update'])->name('update');
    Route::patch('email-templates/{email_template}', [EmailTemplateController::class, 'update']);
    Route::delete('email-templates/{email_template}', [EmailTemplateController::class, 'destroy'])->name('destroy');

    Route::post('email-templates/{email_template}/duplicate', [EmailTemplateController::class, 'duplicate'])->name('duplicate');
    Route::post('email-templates/{email_template}/toggle-status', [EmailTemplateController::class, 'toggleStatus'])->name('toggle-status');

    Route::post('email-templates/{email_template}/preview', [EmailTemplateController::class, 'preview'])->name('preview');
    Route::post('email-templates/{email_template}/send-test', [EmailTemplateController::class, 'sendTest'])->name('send-test');
    Route::get('email-templates/{email_template}/logs', [EmailTemplateController::class, 'logs'])->name('logs');
});

require __DIR__ . '/auth.php';
