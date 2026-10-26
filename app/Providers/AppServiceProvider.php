<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        RateLimiter::for('login', function (Request $request): array {
            $email = $request->input('email');
            $identity = is_string($email) ? Str::lower(trim($email)) : '';

            return [
                Limit::perMinute(20)->by('login-ip:'.$request->ip()),
                Limit::perMinute(5)->by('login-account:'.hash('sha256', $identity.'|'.$request->ip())),
            ];
        });

        RateLimiter::for('fbr-invoice', fn (Request $request): Limit => Limit::perMinute(10)->by('fbr:'.$request->user()->id.':'.$request->route('operation')));
        RateLimiter::for('fbr-reconcile', fn (Request $request): Limit => Limit::perMinute(6)->by('fbr-reconcile:'.$request->user()->id));

        RateLimiter::for('registration', fn (Request $request): array => [
            Limit::perMinute(3)->by('register-minute:'.$request->ip()),
            Limit::perHour(20)->by('register-hour:'.$request->ip()),
        ]);
    }
}
