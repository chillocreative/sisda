<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserActivityAlert extends Model
{
    protected $fillable = [
        'user_id', 'rule_code', 'rule_hash', 'severity',
        'verdict', 'summary', 'details',
        'window_start', 'window_end',
        'whatsapp_status', 'whatsapp_sent_at',
        'acknowledged_at', 'acknowledged_by',
    ];

    protected $casts = [
        'details' => 'array',
        'window_start' => 'datetime',
        'window_end' => 'datetime',
        'whatsapp_sent_at' => 'datetime',
        'acknowledged_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function acknowledgedBy()
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    public function scopeUnacknowledged($query)
    {
        return $query->whereNull('acknowledged_at');
    }

    public function scopeHighPriority($query)
    {
        return $query->whereIn('severity', ['high', 'critical']);
    }
}
