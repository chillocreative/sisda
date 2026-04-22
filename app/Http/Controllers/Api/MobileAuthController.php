<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NotificationTemplate;
use App\Models\User;
use App\Services\WhatsappService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;

class MobileAuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'telephone' => 'required|string',
            'password' => 'required|string',
        ]);

        $throttleKey = Str::lower($request->telephone).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            return response()->json([
                'success' => false,
                'errors' => ['telephone' => ["Terlalu banyak percubaan. Sila cuba lagi dalam {$seconds} saat."]],
            ], 429);
        }

        $user = User::where('telephone', $request->telephone)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            RateLimiter::hit($throttleKey);

            return response()->json([
                'success' => false,
                'errors' => ['telephone' => ['Nombor telefon atau kata laluan tidak sah.']],
            ], 422);
        }

        RateLimiter::clear($throttleKey);

        // Check approval status
        if (! $user->isSuperAdmin() && $user->status !== 'approved') {
            if ($user->status === 'pending') {
                return response()->json([
                    'success' => false,
                    'errors' => ['telephone' => ['Akaun anda masih menunggu kelulusan daripada pentadbir.']],
                ], 422);
            }

            return response()->json([
                'success' => false,
                'errors' => ['telephone' => ['Akaun anda telah ditolak. Sila hubungi pentadbir.']],
            ], 422);
        }

        $user->update(['last_login' => now()]);

        // Revoke old tokens and create a new one
        $user->tokens()->delete();
        $token = $user->createToken('mobile')->plainTextToken;

        return response()->json([
            'success' => true,
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'telephone' => $user->telephone,
                'role' => $user->role,
                'must_change_password' => (bool) $user->must_change_password,
            ],
        ]);
    }

    public function register(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'telephone' => 'required|string|max:255|unique:users,telephone',
            'email' => 'nullable|string|lowercase|email|max:255|unique:users,email',
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'negeri_id' => 'required|exists:negeri,id',
            'bandar_id' => 'required|exists:bandar,id',
            'kadun_id' => 'required|exists:kadun,id',
        ]);

        User::create([
            'name' => $request->name,
            'telephone' => $request->telephone,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => 'user',
            'negeri_id' => $request->negeri_id,
            'bandar_id' => $request->bandar_id,
            'kadun_id' => $request->kadun_id,
            'status' => 'pending',
        ]);

        return response()->json(['success' => true]);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate([
            'telephone' => 'required|string',
        ]);

        $user = User::where('telephone', $request->telephone)->first();

        if (! $user) {
            return response()->json([
                'success' => false,
                'errors' => ['telephone' => ['Nombor telefon tidak dijumpai dalam sistem.']],
            ], 422);
        }

        $newPassword = Str::random(8);

        $user->update([
            'password' => Hash::make($newPassword),
            'must_change_password' => true,
        ]);

        $vars = [
            'nama' => $user->name,
            'password' => $newPassword,
            'pautan' => url('/login'),
            'tempoh' => 24,
            'tarikh_luput' => now()->addDay()->format('d/m/Y H:i'),
            'username' => $user->telephone,
            'peranan' => $user->role,
            'admin_nama' => 'Pentadbir Sistem',
            'masa' => now()->format('d/m/Y H:i'),
            'tahun' => now()->year,
        ];

        $sent = WhatsappService::sendCategoryDefault(
            NotificationTemplate::CATEGORY_PASSWORD_RESET,
            $user->telephone,
            $vars,
            'password_reset'
        );

        if (!$sent) {
            $fallback = "*SISDA - Set Semula Kata Laluan*\n\n"
                ."Kata laluan baharu anda ialah:\n"
                ."`{$newPassword}`\n\n"
                ."Sila log masuk dan tukar kata laluan anda segera.\n\n"
                .'_Mesej ini dijana secara automatik._';
            $sent = WhatsappService::send($user->telephone, $fallback, 'password_reset');
        }

        if ($sent) {
            return response()->json([
                'success' => true,
                'message' => 'Kata laluan baharu telah dihantar ke WhatsApp anda.',
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Kata laluan baharu: '.$newPassword.'. Sila simpan dan tukar selepas log masuk.',
            'password' => $newPassword,
        ]);
    }

    public function webAuthToken(Request $request): JsonResponse
    {
        $token = Str::random(64);
        Cache::put("mobile_web_auth:{$token}", $request->user()->id, 60);

        return response()->json(['token' => $token]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['success' => true]);
    }
}
