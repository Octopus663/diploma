<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Видаляти записи може лише адміністратор.
        Gate::define('delete-records', fn (User $user) => $user->isAdmin());

        // Обмеження спроб входу через API (захист від перебору паролів).
        RateLimiter::for('auth', fn (Request $request) => Limit::perMinute(60)->by($request->ip()));
    }
}
