<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserPageView extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id', 'route_name', 'url', 'method', 'ip', 'user_agent', 'params', 'created_at',
    ];

    protected $casts = [
        'params' => 'array',
        'created_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
