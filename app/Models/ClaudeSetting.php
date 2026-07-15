<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClaudeSetting extends Model
{
    protected $fillable = [
        'api_key', 'model', 'document_model', 'max_tokens', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'api_key' => 'encrypted',
    ];

    protected $hidden = ['api_key'];

    public static function current(): ?self
    {
        return self::first();
    }

    /**
     * Model used for reading documents/images (scoresheet & PDF extraction).
     * Falls back to the main model when no dedicated document model is set.
     */
    public function documentModel(): string
    {
        return $this->document_model ?: ($this->model ?: 'claude-sonnet-4-6');
    }
}
