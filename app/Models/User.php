<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\JobTitle;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

final class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'avatar_url',
        'timezone',
        'mentor_id',
        'job_title',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'job_title' => JobTitle::class,
        ];
    }

    /** @return BelongsToMany<Workspace, $this> */
    public function workspaces(): BelongsToMany
    {
        return $this->belongsToMany(Workspace::class, 'workspace_members')
            ->withPivot('role', 'joined_at')
            ->withTimestamps();
    }

    /** @return HasMany<Workspace, $this> */
    public function ownedWorkspaces(): HasMany
    {
        return $this->hasMany(Workspace::class, 'owner_id');
    }

    /** @return BelongsToMany<Card, $this> */
    public function assignedCards(): BelongsToMany
    {
        return $this->belongsToMany(Card::class, 'card_assignees')
            ->withPivot('assigned_by', 'assigned_at');
    }

    /**
     * The senior user who reviews this user's work in UAT and receives
     * email notifications on workflow transitions.
     *
     * @return BelongsTo<User, $this>
     */
    public function mentor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mentor_id');
    }

    /**
     * Developers this user mentors (inverse of {@see mentor()}).
     *
     * @return HasMany<User, $this>
     */
    public function mentees(): HasMany
    {
        return $this->hasMany(User::class, 'mentor_id');
    }

    public function gravatar(int $size = 80): string
    {
        if ($this->avatar_url) {
            return $this->avatar_url;
        }

        $hash = md5(strtolower(trim($this->email)));

        return "https://www.gravatar.com/avatar/{$hash}?s={$size}&d=identicon";
    }
}
