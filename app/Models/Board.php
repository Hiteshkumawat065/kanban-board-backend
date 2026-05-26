<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BoardVisibility;
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

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }
}
