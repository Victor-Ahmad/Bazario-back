<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
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
         Schema::defaultStringLength(191);
        // Global API limiter - used by throttleApi('api')
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by(
                optional($request->user())->id ?: $request->ip()
            );
        });

        // Stricter limiter for login / password flows (brute-force protection)
        RateLimiter::for('auth', function (Request $request) {
            $email = (string) $request->input('email');

            return [
                // 5 attempts per minute per email+IP
                Limit::perMinute(5)
                    ->by($email . '|' . $request->ip())
                    ->response(function () {
                        return response()->json([
                            'message' => 'Too many attempts. Please try again in a minute.',
                        ], 429);
                    }),

                // Safety net: 50 attempts per minute per IP for all emails
                Limit::perMinute(50)->by($request->ip()),
            ];
        });
    }
}
