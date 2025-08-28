<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'alias',
        'discord_id',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    // 🏆 Challenges
    public function challengesIssued()
    {
        return $this->hasMany(Challenge::class, 'challenger_id');
    }

    public function challengesReceived()
    {
        return $this->hasMany(Challenge::class, 'opponent_id');
    }

    public function rankHistory()
    {
        return $this->hasMany(RankHistory::class);
    }

    // 👥 Teams
    public function currentTeam()
    {
        return $this->belongsTo(Team::class, 'current_team_id');
    }

    public function ownedTeams()
    {
        return $this->hasMany(Team::class);
    }

    public function teams()
    {
        return $this->belongsToMany(Team::class)
            ->withPivot(['role', 'status', 'rank'])
            ->withTimestamps();
    }

    public function pendingInvitations()
    {
        return $this->teams()->wherePivot('status', 'pending');
    }

    public function allTeams()
    {
        // Uses collections but makes sure both relations are loaded
        return $this->ownedTeams()->get()->merge($this->teams()->get());
    }
}
