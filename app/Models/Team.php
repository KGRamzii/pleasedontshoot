<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Team extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'user_id',
        'personal_team',
        'status',
        'role',
    ];

    protected $casts = [
        'personal_team' => 'boolean',
    ];

    public function users()
    {
        return $this->belongsToMany(User::class)
            ->withPivot('role', 'status', 'rank')
            //->orderBy('pivot.rank')
            ->withTimestamps();
    }
    public function hasUser($user)
    {
        return $this->users()->where('user_id', $user->id)->exists();
    }

    public function teams()
    {
        return $this->belongsToMany(Team::class)->withPivot('role', 'status', 'created_at', 'updated_at');
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

    public function addMember(User $user, string $role = 'member')
    {
        $this->users()->attach($user, ['role' => $role]);
    }

    public function removeMember(User $user)
    {
        $this->users()->detach($user);
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
