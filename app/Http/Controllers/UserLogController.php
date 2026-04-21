<?php

namespace App\Http\Controllers;

use App\Models\EditHistory;
use App\Models\User;
use App\Models\UserActivityAlert;
use App\Models\UserLoginLog;
use App\Models\UserPageView;
use App\Services\UserLogAlertService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;

class UserLogController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->only(['user_id', 'date_from', 'date_to', 'event', 'tab']);

        [$dateFrom, $dateTo] = $this->resolveDateRange($filters);

        $monitoredIds = User::whereIn('role', ['user', 'super_user'])->pluck('id');

        $alerts = UserActivityAlert::query()
            ->with('user:id,name,role,bandar_id')
            ->unacknowledged()
            ->when($filters['user_id'] ?? null, fn ($q, $uid) => $q->where('user_id', $uid))
            ->orderByRaw("FIELD(severity,'critical','high','medium','low')")
            ->orderByDesc('created_at')
            ->paginate(10, ['*'], 'alerts_page')
            ->withQueryString();

        $logins = UserLoginLog::query()
            ->with('user:id,name,role')
            ->when($filters['user_id'] ?? null, fn ($q, $uid) => $q->where('user_id', $uid))
            ->when($filters['event'] ?? null, fn ($q, $ev) => $q->where('event', $ev))
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->orderByDesc('created_at')
            ->paginate(15, ['*'], 'logins_page')
            ->withQueryString();

        $pageViews = UserPageView::query()
            ->with('user:id,name,role')
            ->when($filters['user_id'] ?? null, fn ($q, $uid) => $q->where('user_id', $uid))
            ->whereIn('user_id', $monitoredIds)
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->orderByDesc('created_at')
            ->paginate(20, ['*'], 'views_page')
            ->withQueryString();

        $edits = EditHistory::query()
            ->with('user:id,name,role')
            ->when($filters['user_id'] ?? null, fn ($q, $uid) => $q->where('user_id', $uid))
            ->whereIn('user_id', $monitoredIds)
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->orderByDesc('created_at')
            ->paginate(15, ['*'], 'edits_page')
            ->withQueryString();

        $stats = [
            'open_alerts' => UserActivityAlert::unacknowledged()->count(),
            'high_alerts' => UserActivityAlert::unacknowledged()->highPriority()->count(),
            'logins_24h' => UserLoginLog::where('created_at', '>=', now()->subDay())->count(),
            'page_views_24h' => UserPageView::where('created_at', '>=', now()->subDay())->count(),
            'edits_24h' => EditHistory::whereIn('user_id', $monitoredIds)
                ->where('created_at', '>=', now()->subDay())->count(),
        ];

        $usersForFilter = User::whereIn('role', ['user', 'super_user'])
            ->orderBy('name')
            ->get(['id', 'name', 'role']);

        $latestVerdict = UserActivityAlert::query()
            ->with('user:id,name,role')
            ->highPriority()
            ->unacknowledged()
            ->orderByDesc('created_at')
            ->first();

        return Inertia::render('UserLog/Index', [
            'alerts' => $alerts,
            'logins' => $logins,
            'pageViews' => $pageViews,
            'edits' => $edits,
            'stats' => $stats,
            'users' => $usersForFilter,
            'filters' => [
                'user_id' => $filters['user_id'] ?? null,
                'event' => $filters['event'] ?? null,
                'date_from' => $dateFrom->toDateString(),
                'date_to' => $dateTo->toDateString(),
                'tab' => $filters['tab'] ?? 'alerts',
            ],
            'latestVerdict' => $latestVerdict ? [
                'id' => $latestVerdict->id,
                'severity' => $latestVerdict->severity,
                'verdict' => $latestVerdict->verdict,
                'summary' => $latestVerdict->summary,
                'rule_code' => $latestVerdict->rule_code,
                'user' => $latestVerdict->user ? [
                    'id' => $latestVerdict->user->id,
                    'name' => $latestVerdict->user->name,
                    'role' => $latestVerdict->user->role,
                ] : null,
                'created_at' => $latestVerdict->created_at,
            ] : null,
        ]);
    }

    public function show(Request $request, User $user)
    {
        abort_if(! in_array($user->role, ['user', 'super_user'], true), 403, 'Akaun ini bukan dalam skop pemantauan.');

        [$dateFrom, $dateTo] = $this->resolveDateRange($request->only(['date_from', 'date_to']));

        $logins = UserLoginLog::where('user_id', $user->id)
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->orderByDesc('created_at')
            ->limit(500)
            ->get()
            ->map(fn ($r) => [
                'type' => 'login',
                'ts' => $r->created_at,
                'event' => $r->event,
                'ip' => $r->ip,
                'user_agent' => $r->user_agent,
            ]);

        $views = UserPageView::where('user_id', $user->id)
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->orderByDesc('created_at')
            ->limit(500)
            ->get()
            ->map(fn ($r) => [
                'type' => 'view',
                'ts' => $r->created_at,
                'route_name' => $r->route_name,
                'url' => $r->url,
                'method' => $r->method,
                'ip' => $r->ip,
            ]);

        $edits = EditHistory::where('user_id', $user->id)
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->orderByDesc('created_at')
            ->limit(500)
            ->get()
            ->map(fn ($r) => [
                'type' => 'edit',
                'ts' => $r->created_at,
                'model_type' => $r->model_type,
                'model_id' => $r->model_id,
                'action' => $r->action,
                'fields_changed' => array_keys($r->changes ?? []),
            ]);

        $timeline = collect()
            ->merge($logins)
            ->merge($views)
            ->merge($edits)
            ->sortByDesc('ts')
            ->values();

        $alerts = UserActivityAlert::where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return Inertia::render('UserLog/Show', [
            'monitoredUser' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'telephone' => $user->telephone,
                'last_login' => $user->last_login,
                'last_login_ip' => $user->last_login_ip,
            ],
            'timeline' => $timeline,
            'alerts' => $alerts,
            'filters' => [
                'date_from' => $dateFrom->toDateString(),
                'date_to' => $dateTo->toDateString(),
            ],
        ]);
    }

    public function analyze(Request $request, UserLogAlertService $service)
    {
        $scopeUserId = $request->integer('user_id') ?: null;
        $result = $service->analyzeAndAlert(null, null, $scopeUserId);

        return back()->with('success', sprintf(
            'Analisis selesai. Dapatan: %d, Amaran baru: %d, WhatsApp dihantar: %d.',
            $result['findings'],
            $result['new_alerts'],
            $result['whatsapp_sent'],
        ));
    }

    public function acknowledge(UserActivityAlert $alert)
    {
        $alert->update([
            'acknowledged_at' => now(),
            'acknowledged_by' => auth()->id(),
        ]);

        return back()->with('success', 'Amaran telah diakui.');
    }

    public function destroy(UserActivityAlert $alert)
    {
        $alert->delete();
        return back()->with('success', 'Amaran dipadam.');
    }

    private function resolveDateRange(array $filters): array
    {
        $to = CarbonImmutable::now()->endOfDay();
        $from = CarbonImmutable::now()->subDays(7)->startOfDay();

        if (! empty($filters['date_from'])) {
            try {
                $from = CarbonImmutable::parse($filters['date_from'])->startOfDay();
            } catch (\Throwable) {}
        }

        if (! empty($filters['date_to'])) {
            try {
                $to = CarbonImmutable::parse($filters['date_to'])->endOfDay();
            } catch (\Throwable) {}
        }

        return [$from, $to];
    }
}
