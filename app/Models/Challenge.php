<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Challenge extends Model
{
    protected $fillable = [
        'challenger_id',
        'opponent_id',
        'winner_id',        // <- This was missing!
        'status',
        'banned_agent',
        'witness_id',
        'team_id',
        'completed_at',     // <- Add this too for completeness
    ];

    protected $casts = [
        'completed_at' => 'datetime',
    ];

    public function challenger()
    {
        return $this->belongsTo(User::class, 'challenger_id');
    }

    public function opponent()
    {
        return $this->belongsTo(User::class, 'opponent_id');
    }

    public function winner()
    {
        return $this->belongsTo(User::class, 'winner_id');
    }
    public function loser()
    {
        return $this->belongsTo(User::class, 'loser_id');
    }

    public function witness()
    {
        return $this->belongsTo(User::class, 'witness_id');
    }

    public function rankHistories()
    {
        return $this->hasMany(RankHistory::class);
    }

    public function team()
    {
        return $this->belongsTo(Team::class);
    }
}
