<?php

namespace App\Http\Controllers;

use App\Models\SendoraSetting;
use App\Models\WhatsappMessage;
use App\Services\WhatsappService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;

class SendoraSettingController extends Controller
{
    public function index()
    {
        $settings = SendoraSetting::first();
        $recentMessages = WhatsappMessage::latest()->take(20)->get();

        return Inertia::render('Settings/Sendora', [
            'settings' => $settings ? [
                'api_url' => $settings->api_url,
                'api_token' => $settings->api_token ? '••••••••' : '',
                'device_id' => $settings->device_id,
                'admin_phone' => $settings->admin_phone,
                'is_active' => $settings->is_active,
                'has_token' => !empty($settings->api_token),
            ] : null,
            'recentMessages' => $recentMessages,
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'api_url' => 'required|url',
            'api_token' => 'nullable|string',
            'device_id' => 'nullable|integer',
            'admin_phone' => 'nullable|string|max:20',
            'is_active' => 'required|boolean',
        ]);

        $settings = SendoraSetting::first();

        if ($validated['api_token'] === '••••••••' || empty($validated['api_token'])) {
            unset($validated['api_token']);
        }

        if ($settings) {
            $settings->update($validated);
        } else {
            SendoraSetting::create($validated);
        }

        return redirect()->back()->with('success', 'Tetapan Sendora berjaya dikemaskini.');
    }

    public function testConnection(Request $request)
    {
        $apiUrl = $request->input('api_url');
        $apiToken = $request->input('api_token');

        if ($apiToken === '••••••••' || empty($apiToken)) {
            $settings = SendoraSetting::first();
            $apiToken = $settings?->api_token;
        }

        if (!$apiUrl || !$apiToken) {
            return back()->with('error', 'Sila masukkan API URL dan API Token.');
        }

        try {
            $baseUrl = rtrim($apiUrl, '/');

            $profileResponse = Http::timeout(10)
                ->withToken($apiToken)
                ->acceptJson()
                ->get($baseUrl . '/api/v1/profile');

            if (!$profileResponse->successful() || !$profileResponse->json('success')) {
                $status = $profileResponse->status();
                if ($status === 401) {
                    return back()->with('error', 'API token tidak sah.');
                } elseif ($status === 403) {
                    return back()->with('error', 'Akses API tidak dibenarkan. Pastikan akaun Sendora mempunyai pelan Business.');
                }
                return back()->with('error', 'Sambungan gagal: ' . ($profileResponse->json('message') ?? "HTTP {$status}"));
            }

            $devicesResponse = Http::timeout(10)
                ->withToken($apiToken)
                ->acceptJson()
                ->get($baseUrl . '/api/v1/devices');

            $profile = $profileResponse->json('data');
            $devices = $devicesResponse->successful() ? $devicesResponse->json('data') : [];

            return back()->with([
                'success' => "Berjaya disambungkan! Akaun: {$profile['name']}",
                'devices' => $devices,
            ]);
        } catch (\Exception $e) {
            return back()->with('error', 'Sambungan gagal: ' . $e->getMessage());
        }
    }

    public function testSend(Request $request)
    {
        $request->validate([
            'phone' => 'required|string',
            'message' => 'required|string|max:1000',
        ]);

        $result = WhatsappService::send($request->phone, $request->message, 'test');

        return back()->with(
            $result ? 'success' : 'error',
            $result ? 'Mesej ujian berjaya dihantar!' : 'Gagal menghantar. Semak tetapan Sendora.'
        );
    }
}
