<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotificationTemplate extends Model
{
    use HasFactory;

    public const CATEGORY_WHATSAPP = 'whatsapp';
    public const CATEGORY_PASSWORD_RESET = 'password_reset';
    public const CATEGORY_SYSTEM = 'system';

    public const CATEGORIES = [
        self::CATEGORY_WHATSAPP => 'WhatsApp',
        self::CATEGORY_PASSWORD_RESET => 'Set Semula Kata Laluan',
        self::CATEGORY_SYSTEM => 'Notifikasi Sistem',
    ];

    protected $fillable = [
        'category',
        'code',
        'name',
        'description',
        'body',
        'variables',
        'is_active',
        'is_default',
        'sort_order',
    ];

    protected $casts = [
        'variables' => 'array',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function scopeCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public static function findByCode(string $code): ?self
    {
        return static::where('code', $code)->first();
    }

    public static function defaultFor(string $category): ?self
    {
        return static::category($category)
            ->active()
            ->where('is_default', true)
            ->orderBy('sort_order')
            ->first()
            ?? static::category($category)->active()->orderBy('sort_order')->first();
    }

    public function render(array $vars = []): string
    {
        $body = (string) $this->body;

        foreach ($vars as $key => $value) {
            $placeholder = '{{' . $key . '}}';
            $body = str_replace($placeholder, (string) $value, $body);
            $body = str_replace('{{ ' . $key . ' }}', (string) $value, $body);
        }

        return $body;
    }

    public function extractVariables(): array
    {
        preg_match_all('/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/', (string) $this->body, $m);
        return array_values(array_unique($m[1] ?? []));
    }
}
