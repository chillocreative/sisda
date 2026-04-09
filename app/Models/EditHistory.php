<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EditHistory extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'model_type', 'model_id', 'user_id', 'action', 'changes',
    ];

    protected $casts = [
        'changes' => 'array',
        'created_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public static function log(string $modelType, int $modelId, string $action, ?array $changes = null): self
    {
        return self::create([
            'model_type' => $modelType,
            'model_id' => $modelId,
            'user_id' => auth()->id(),
            'action' => $action,
            'changes' => $changes,
        ]);
    }
}
