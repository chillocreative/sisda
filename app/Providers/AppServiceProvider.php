<?php

namespace App\Providers;

use App\Listeners\LogFailedLogin;
use App\Listeners\LogLogout;
use App\Listeners\LogSuccessfulLogin;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        Event::listen(Login::class, LogSuccessfulLogin::class);
        Event::listen(Failed::class, LogFailedLogin::class);
        Event::listen(Logout::class, LogLogout::class);
    }
}
