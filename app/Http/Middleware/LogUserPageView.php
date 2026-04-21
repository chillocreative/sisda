<?php

namespace App\Http\Middleware;

use App\Models\UserPageView;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class LogUserPageView
{
    /**
     * Route-name prefixes we log. Only voter-data-relevant endpoints —
     * profile/login/dashboard-plain GETs are left out to keep the signal
     * dense and DB writes cheap.
     */
    private const WHITELIST = [
        'reports.',
        'dashboard',
        'dashboard.',
        'users.index',
        'users.show',
        'master-data.',
        'upload-database.',
        'dpt-upload.',
        'call-center.',
        'api.voter.',
        'api.hasil-culaan.',
        'api.edit-history',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        try {
            $this->maybeLog($request, $response);
        } catch (\Throwable $e) {
            // Never let logging break the request.
            report($e);
        }

        return $response;
    }

    private function maybeLog(Request $request, Response $response): void
    {
        if ($request->method() !== 'GET') {
            return;
        }

        $user = $request->user();
        if (! $user || ! in_array($user->role, ['user', 'super_user'], true)) {
            return;
        }

        $routeName = optional($request->route())->getName();
        if (! $this->matchesWhitelist($routeName)) {
            return;
        }

        // Skip Inertia version polling (partial reloads still hit here,
        // but those represent real interactive navigation — log them).
        if ($request->header('X-Inertia-Version') && $request->method() === 'HEAD') {
            return;
        }

        // Strip any voter PII from route params. We only keep integer IDs.
        $routeParams = [];
        if ($request->route()) {
            foreach ($request->route()->parameters() as $key => $value) {
                if (is_object($value) && method_exists($value, 'getKey')) {
                    $routeParams[$key] = $value->getKey();
                } elseif (is_scalar($value)) {
                    $routeParams[$key] = Str::limit((string) $value, 64, '');
                }
            }
        }

        UserPageView::create([
            'user_id' => $user->id,
            'route_name' => $routeName,
            'url' => Str::limit($request->fullUrl(), 2000, ''),
            'method' => $request->method(),
            'ip' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 2000),
            'params' => $routeParams ?: null,
            'created_at' => now(),
        ]);
    }

    private function matchesWhitelist(?string $routeName): bool
    {
        if (! $routeName) {
            return false;
        }

        foreach (self::WHITELIST as $prefix) {
            if ($routeName === $prefix || str_starts_with($routeName, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
