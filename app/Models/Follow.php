<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Follow extends Model
{
    protected $fillable = [
        'follower_id',
        'following_id',
    ];

    /**
     * Get the follower (user who follows)
     */
    public function follower()
    {
        return $this->belongsTo(User::class, 'follower_id');
    }

    /**
     * Get the following (user being followed)
     */
    public function following()
    {
        return $this->belongsTo(User::class, 'following_id');
    }
}
