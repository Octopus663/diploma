<?php

namespace App\Providers;

use App\LeakDemo\BoundedReportMemo;
use App\LeakDemo\ReportMemo;
use App\LeakDemo\RequestContext;
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
        if (config('bench.leak_demo')) {
            $this->registerLeakDemo();
        }
    }

    /**
     * Прив'язки для демонстрації витоків (етап 6). Див. docs/memory-leaks.md.
     */
    private function registerLeakDemo(): void
    {
        // Неправильно: singleton отримує Request першого запиту воркера.
        $this->app->singleton('leak.context.singleton', fn ($app) => new RequestContext($app['request']));

        // Правильно: scoped створюється заново для кожного запиту.
        $this->app->scoped('leak.context.scoped', fn ($app) => new RequestContext($app['request']));

        // Неправильно: необмежений масив у синглтоні.
        $this->app->singleton(ReportMemo::class);

        // Правильно: обмежений розмір.
        $this->app->singleton(BoundedReportMemo::class);
    }

    public function boot(): void
    {
        if (config('bench.leak_demo')) {
            // Типова помилка: сервіси «прогріваються» в boot() (або через octane.warm).
            // В Octane boot() виконується один раз на воркер, тому ці екземпляри живуть між запитами.
            $this->app->make('leak.context.singleton');
            $this->app->make(ReportMemo::class);
            $this->app->make(BoundedReportMemo::class);
        }

        // Видаляти записи може лише адміністратор.
        Gate::define('delete-records', fn (User $user) => $user->isAdmin());

        // Обмеження спроб входу через API (захист від перебору паролів).
        RateLimiter::for('auth', fn (Request $request) => Limit::perMinute(60)->by($request->ip()));
    }
}
