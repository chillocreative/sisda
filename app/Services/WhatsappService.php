<?php

namespace App\Services;

use App\Models\NotificationTemplate;
use App\Models\SendoraSetting;
use App\Models\WhatsappMessage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsappService
{
    protected static function getConfig(): ?SendoraSetting
    {
        return SendoraSetting::current();
    }

    protected static function formatPhone($phone): string
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);

        if (strlen($phone) >= 10 && strlen($phone) <= 11 && strpos($phone, '01') === 0) {
            $phone = '60' . substr($phone, 1);
        }

        return $phone;
    }

    public static function send($phone, $message, $type = 'notification'): bool
    {
        $config = self::getConfig();

        if (!$config || !$config->is_active) {
            Log::warning("Sendora not configured or inactive. Message to {$phone} skipped.");
            return false;
        }

        $phone = self::formatPhone($phone);

        try {
            $response = Http::timeout(15)
                ->withToken($config->api_token)
                ->acceptJson()
                ->post(rtrim($config->api_url, '/') . '/api/v1/send-message', [
                    'device_id' => $config->device_id,
                    'to' => $phone,
                    'message' => $message,
                ]);

            $success = $response->successful() && ($response->json('success') === true);

            WhatsappMessage::create([
                'phone' => $phone,
                'message' => substr($message, 0, 255),
                'status' => $success ? 'sent' : 'failed',
                'type' => $type,
                'error' => $success ? null : ($response->json('message') ?? $response->body()),
            ]);

            if ($success) {
                Log::info("WhatsApp sent to {$phone} via Sendora");
            } else {
                Log::error("WhatsApp failed to {$phone}. Status: {$response->status()}");
            }

            return $success;
        } catch (\Exception $e) {
            WhatsappMessage::create([
                'phone' => $phone,
                'message' => substr($message, 0, 255),
                'status' => 'failed',
                'type' => $type,
                'error' => $e->getMessage(),
            ]);
            Log::error("Sendora error to {$phone}: " . $e->getMessage());
            return false;
        }
    }

    public static function notifyAdmin($message, $type = 'admin_notify'): bool
    {
        $config = self::getConfig();
        $adminPhone = $config?->admin_phone;

        if (!$adminPhone) return false;

        return self::send($adminPhone, $message, $type);
    }

    /**
     * Send using a stored NotificationTemplate code. Variables are substituted
     * with the provided array. Returns false silently if the template is missing
     * or inactive — caller can fall back to a hard-coded message.
     */
    public static function sendTemplate(string $code, string $phone, array $vars = [], ?string $type = null): bool
    {
        $template = NotificationTemplate::findByCode($code);

        if (!$template || !$template->is_active) {
            Log::warning("WhatsApp template '{$code}' not found or inactive.");
            return false;
        }

        $message = $template->render($vars);
        return self::send($phone, $message, $type ?? ('template:' . $code));
    }

    /**
     * Send the default template for a given category.
     */
    public static function sendCategoryDefault(string $category, string $phone, array $vars = [], ?string $type = null): bool
    {
        $template = NotificationTemplate::defaultFor($category);

        if (!$template) {
            Log::warning("No default WhatsApp template for category '{$category}'.");
            return false;
        }

        $message = $template->render($vars);
        return self::send($phone, $message, $type ?? ('category:' . $category));
    }
}
