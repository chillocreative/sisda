<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserLoginLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id', 'event', 'email_attempted', 'ip', 'user_agent', 'session_id', 'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
