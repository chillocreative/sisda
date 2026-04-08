<?php

namespace App\Http\Controllers;

use App\Models\ClaudeSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;

class ClaudeSettingController extends Controller
{
    public function index()
    {
        $settings = ClaudeSetting::first();

        return Inertia::render('Settings/Claude', [
            'settings' => $settings ? [
                'api_key' => $settings->api_key ? '••••••••' : '',
                'model' => $settings->model,
                'max_tokens' => $settings->max_tokens,
                'is_active' => $settings->is_active,
                'has_key' => !empty($settings->api_key),
            ] : null,
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'api_key' => 'nullable|string',
            'model' => 'required|string|max:100',
            'max_tokens' => 'required|integer|min:256|max:128000',
            'is_active' => 'required|boolean',
        ]);

        $settings = ClaudeSetting::first();

        if ($validated['api_key'] === '••••••••' || empty($validated['api_key'])) {
            unset($validated['api_key']);
        }

        if ($settings) {
            $settings->update($validated);
        } else {
            ClaudeSetting::create($validated);
        }

        return redirect()->back()->with('success', 'Tetapan Claude AI berjaya dikemaskini.');
    }

    public function testConnection(Request $request)
    {
        $apiKey = $request->input('api_key');

        if ($apiKey === '••••••••' || empty($apiKey)) {
            $settings = ClaudeSetting::first();
            $apiKey = $settings?->api_key;
        }

        if (!$apiKey) {
            return back()->with('error', 'Sila masukkan API Key.');
        }

        try {
            $response = Http::timeout(15)
                ->withHeaders([
                    'x-api-key' => $apiKey,
                    'anthropic-version' => '2023-06-01',
                ])
                ->acceptJson()
                ->post('https://api.anthropic.com/v1/messages', [
                    'model' => $request->input('model', 'claude-sonnet-4-20250514'),
                    'max_tokens' => 100,
                    'messages' => [
                        ['role' => 'user', 'content' => 'Reply with exactly: "SISDA connection test successful"'],
                    ],
                ]);

            if ($response->successful()) {
                $reply = $response->json('content.0.text') ?? 'OK';
                $model = $response->json('model') ?? 'unknown';
                $usage = $response->json('usage') ?? [];
                return back()->with('success', "Berjaya disambungkan! Model: {$model}. Respons: {$reply}");
            }

            $error = $response->json('error.message') ?? $response->body();
            return back()->with('error', "Sambungan gagal: {$error}");
        } catch (\Exception $e) {
            return back()->with('error', 'Sambungan gagal: ' . $e->getMessage());
        }
    }
}
