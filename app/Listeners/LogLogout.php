<?php

namespace App\Listeners;

use App\Models\User;
use App\Models\UserLoginLog;
use Illuminate\Auth\Events\Logout;
use Illuminate\Http\Request;

class LogLogout
{
    public function __construct(protected Request $request) {}

    public function handle(Logout $event): void
    {
        $user = $event->user;

        if (! $user instanceof User) {
            return;
        }

        if (! in_array($user->role, ['user', 'super_user'], true)) {
            return;
        }

        UserLoginLog::create([
            'user_id' => $user->id,
            'event' => 'logout',
            'ip' => $this->request->ip(),
            'user_agent' => substr((string) $this->request->userAgent(), 0, 2000),
            'session_id' => $this->request->hasSession() ? $this->request->session()->getId() : null,
            'created_at' => now(),
        ]);
    }
}
