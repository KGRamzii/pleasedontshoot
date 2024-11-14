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
}
