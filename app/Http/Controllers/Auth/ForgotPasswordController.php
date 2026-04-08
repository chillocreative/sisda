<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\WhatsappService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class ForgotPasswordController extends Controller
{
    public function create()
    {
        return Inertia::render('Auth/ForgotPassword');
    }

    public function store(Request $request)
    {
        $request->validate([
            'telephone' => 'required|string',
        ]);

        $user = User::where('telephone', $request->telephone)->first();

        if (!$user) {
            return back()->withErrors([
                'telephone' => 'Nombor telefon tidak dijumpai dalam sistem.',
            ]);
        }

        // Generate random 8-character password
        $newPassword = Str::random(8);

        // Update user password and flag for change
        $user->update([
            'password' => $newPassword,
            'must_change_password' => true,
        ]);

        // Send via WhatsApp
        $message = "*SISDA - Set Semula Kata Laluan*\n\n"
            . "Kata laluan baharu anda ialah:\n"
            . "`{$newPassword}`\n\n"
            . "Sila log masuk dan tukar kata laluan anda segera.\n\n"
            . "_Mesej ini dijana secara automatik._";

        $sent = WhatsappService::send($user->telephone, $message, 'password_reset');

        if ($sent) {
            return back()->with('status', 'Kata laluan baharu telah dihantar ke WhatsApp anda. Sila semak telefon anda.');
        }

        // Password was still changed even if WhatsApp failed
        return back()->with('status', 'Kata laluan baharu telah ditetapkan. Kata laluan: ' . $newPassword . '. Sila simpan dan tukar selepas log masuk.');
    }
}
