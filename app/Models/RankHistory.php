<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RankHistory extends Model
{
    protected $fillable = [
        'user_id',
        'previous_rank',
        'new_rank',
        'challenge_id', // Corrected spelling
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function challenge()
    {
        return $this->belongsTo(Challenge::class);
    }
}
