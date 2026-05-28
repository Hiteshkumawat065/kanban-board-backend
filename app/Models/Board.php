<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BoardVisibility;
use App\Enums\WorkspaceRole;
use Database\Factories\BoardFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Board extends Model
{
    /** @use HasFactory<BoardFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'workspace_id',
        'name',
        'description',
        'background_color',
        'background_url',
        'visibility',
        'position',
        'created_by',
        'archived_at',
    ];

    /**
     * Defaults applied to new instances so freshly-created boards have a
     * sane in-memory `visibility` before the row is refreshed from the DB.
     * Mirrors the migration default.
     */
    protected $attributes = [
        'visibility' => 'workspace',
    ];

    protected function casts(): array
    {
        return [
            'visibility' => BoardVisibility::class,
            'archived_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Workspace, $this> */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<BoardList, $this> */
    public function lists(): HasMany
    {
        return $this->hasMany(BoardList::class)->orderBy('position');
    }

    /** @return HasMany<Card, $this> */
    public function cards(): HasMany
    {
        return $this->hasMany(Card::class);
    }

    /** @return HasMany<Label, $this> */
    public function labels(): HasMany
    {
        return $this->hasMany(Label::class);
    }

    /** @return BelongsToMany<User, $this> */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'board_members')
            ->withPivot('role')
            ->withTimestamps();
    }

    /** @return HasMany<Activity, $this> */
    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('archived_at');
    }

    /**
     * Loose scope - any board in a workspace the user is a member of. Kept
     * for backward compatibility but {@see scopeVisibleTo()} is the one to
     * use for permission-aware listings.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query->whereHas(
            'workspace.members',
            fn (Builder $q) => $q->where('users.id', $user->id)
        );
    }

    /**
     * RBAC-aware scope. Returns the boards the given user is actually
     * allowed to *see*. The rules, in order:
     *
     *  1. Super Admin / Admin (i.e. anyone with a global-scope role)
     *     sees every board - no filter applied.
     *  2. Workspace Owner / Admin (workspace_members.role IN ('owner','admin'))
     *     sees every board inside that workspace. They are the workspace's
     *     managers, so per-board scoping does not apply to them.
     *  3. Everyone else (Member workspace role + functional roles like
     *     Developer / Designer / QA / Mentor) sees ONLY the boards they
     *     are explicitly attached to via `board_members`.
     *
     * Usage:
     *     $workspace->boards()->visibleTo($user)->get();
     *     Board::visibleTo($user)->whereNull('archived_at')->get();
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        // Rule 1: global-scope roles bypass per-board filtering entirely.
        if ($user->hasGlobalAccess()) {
            return $query;
        }

        // Rules 2 + 3 are combined as an OR on the same query so a single
        // user who manages workspace A and is only a board member in
        // workspace B still sees the correct subset across both workspaces
        // in cross-workspace listings (Dashboard / closed-boards modal).
        return $query->where(function (Builder $q) use ($user) {
            $q
                // (a) Workspace Owner / Admin -> every board in that workspace.
                ->whereHas('workspace.members', function (Builder $w) use ($user) {
                    $w->where('users.id', $user->id)
                        ->whereIn('workspace_members.role', [
                            WorkspaceRole::Owner->value,
                            WorkspaceRole::Admin->value,
                        ]);
                })
                // (b) Workspace Member (or no workspace membership at all) ->
                //     only boards with an explicit board_members row.
                ->orWhereHas('members', function (Builder $m) use ($user) {
                    $m->where('users.id', $user->id);
                });
        });
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }
}
