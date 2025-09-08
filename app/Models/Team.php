<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\CacheHelper;

class Team extends Model
{
    use HasFactory, CacheHelper;

    protected $fillable = [
        'name',
        'user_id',
        'personal_team',
        'discord_team_id',
    ];

    protected $casts = [
        'personal_team' => 'boolean',
    ];

    /**
     * The relationships that should always be eager loaded.
     *
     * @var array
     */
    protected $with = ['owner'];

    /**
     * Get the cache key for rankings
     */
    protected function getRankingsCacheKey(): string
    {
        return "team_{$this->id}_rankings";
    }

    public function users()
    {
        return $this->belongsToMany(User::class)
            ->withPivot(['role', 'status', 'rank'])
            ->withTimestamps()
            ->orderByPivot('rank', 'asc');
    }

    /**
     * Get team rankings with caching
     */
    public function getRankings()
    {
        return $this->cacheData(
            $this->getCacheKey('rankings'),
            300, // Cache for 5 minutes
            function () {
                return $this->users()
                    ->with(['rankHistories' => function ($query) {
                        $query->latest()->take(5);
                    }])
                    ->orderByPivot('rank', 'asc')
                    ->get()
                    ->map(function ($user) {
                        $user->recent_rank_changes = $user->rankHistories;
                        return $user;
                    });
            }
        );
    }

    /**
     * Clear rankings cache when rankings are updated
     */
    public function clearRankingsCache(): void
    {
        $this->clearCache($this->getCacheKey('rankings'));
    }

    public function hasUser($user)
    {
        return $this->users()->where('user_id', $user->id)->exists();
    }

    public function inviteUser(User $user)
    {
        return $this->users()->attach($user->id, ['role' => 'member', 'status' => 'pending']);
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function challenges()
    {
        return $this->hasMany(Challenge::class);
    }

    public function addMember(User $user, string $role = 'member', int $rank = null)
    {
        return $this->users()->attach($user->id, [
            'role'  => $role,
            'status' => 'active',
            'rank'  => $rank,
        ]);
    }

    public function removeMember(User $user)
    {
        return $this->users()->detach($user->id);
    }

    public function isMember(User $user): bool
    {
        return $this->users->contains($user) || $this->user_id === $user->id;
    }

    public function isOwner(User $user): bool
    {
        return $this->user_id === $user->id;
    }
}
