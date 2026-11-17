<?php

namespace App\Providers;

use App\Models\Admin;
use App\Services\Connectors\OperationCatalog;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use WittyGekko\SbuConnector\Registry\OperationRegistry;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Schema::defaultStringLength(191);
        Paginator::useBootstrapFive();
        Password::defaults(fn () => Password::min(6));

        RateLimiter::for('login', function (Request $request): Limit {
            return Limit::perMinute(5)->by(strtolower((string) $request->input('username')).'|'.$request->ip());
        });
        RateLimiter::for('ai', function (Request $request): Limit {
            $id = $request->user('admin')?->id ?? $request->ip();

            return Limit::perMinute(30)->by((string) $id);
        });

        Gate::define('settings.manage', fn (Admin $admin): bool => $admin->isSuperAdmin());

        $registry = $this->app->make(OperationRegistry::class);
        foreach (OperationCatalog::definitions() as $definition) {
            if (! $registry->has($definition->name)) {
                $registry->register($definition);
            }
        }
    }
}
