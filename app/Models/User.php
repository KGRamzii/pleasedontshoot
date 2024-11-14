<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'alias',
        'rank',
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

    public function challengesIssued() // Corrected spelling
    {
        return $this->hasMany(Challenge::class, 'challenger_id');
    }

    public function challengesReceived() // Corrected spelling
    {
        return $this->hasMany(Challenge::class, 'opponent_id');
    }

    public function rankHistory() // Corrected spelling
    {
        return $this->hasMany(RankHistory::class);
    }

    //Team support

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
            ->withPivot('role', 'status')
            ->withTimestamps();
    }

    public function pendingInvitations()
    {
        return $this->teams()->wherePivot('status', 'pending');
    }

    public function allTeams()
    {
        return $this->ownedTeams->merge($this->teams);
    }

    // protected static function booted()
    // {
    //     static::created(function ($user) {
    //         $user->ownedTeams()->create([
    //             'name' => $user->name . "'s Team",
    //             'personal_team' => true,
    //         ]);
    //     });
    // }
}
