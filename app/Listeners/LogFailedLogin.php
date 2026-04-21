<?php

namespace App\Listeners;

use App\Models\User;
use App\Models\UserLoginLog;
use Illuminate\Auth\Events\Failed;
use Illuminate\Http\Request;

class LogFailedLogin
{
    public function __construct(protected Request $request) {}

    public function handle(Failed $event): void
    {
        $credentials = $event->credentials ?? [];
        $email = $credentials['email'] ?? null;
        $telephone = $credentials['telephone'] ?? null;

        $user = $event->user instanceof User ? $event->user : null;

        if (! $user && $email) {
            $user = User::where('email', $email)->first();
        }

        if (! $user && $telephone) {
            $user = User::where('telephone', $telephone)->first();
        }

        // Skip logging failed attempts for accounts we monitor is only
        // user/super_user. Unknown emails are still logged (user_id = null)
        // so brute-force sweeps are visible.
        if ($user && ! in_array($user->role, ['user', 'super_user'], true)) {
            return;
        }

        UserLoginLog::create([
            'user_id' => $user?->id,
            'event' => 'login_failed',
            'email_attempted' => $email ?? $telephone,
            'ip' => $this->request->ip(),
            'user_agent' => substr((string) $this->request->userAgent(), 0, 2000),
            'created_at' => now(),
        ]);
    }
}
