<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Challenge extends Model
{
    protected $fillable = [
        'challenger_id',
        'opponent_id',
        'status',
        'banned_agent',
        'witness_id',
        'team_id',
    ];

    public function challenger()
    {
        return $this->belongsTo(User::class, 'challenger_id');
    }

    public function opponent()
    {
        return $this->belongsTo(User::class, 'opponent_id');
    }

    public function witness()
    {
        return $this->belongsTo(User::class, 'witness_id'); // Capitalized 'User'
    }
    public function rankHistories()
    {
        return $this->hasMany(RankHistory::class);
    }
    // winner and loser relationships
    public function winner()
    {
        return $this->belongsTo(User::class, 'winner_id');
    }

    public function loser()
    {
        return $this->belongsTo(User::class, 'loser_id');
    }

    //teams


    public function team()
    {
        return $this->belongsTo(Team::class);
    }
}
