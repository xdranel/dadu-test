<?php

namespace App\Providers;

use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\GraduationRegistration;
use App\Policies\DocumentCategoryPolicy;
use App\Policies\DocumentPolicy;
use App\Policies\GraduationRegistrationPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     */
    protected $policies = [
        GraduationRegistration::class => GraduationRegistrationPolicy::class,
        DocumentCategory::class       => DocumentCategoryPolicy::class,
        Document::class               => DocumentPolicy::class,
    ];

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
        // Register policies
        foreach ($this->policies as $model => $policy) {
            Gate::policy($model, $policy);
        }

        // Implicitly grant "admin" role all permissions
        // This works in the app by bypassing gate checks for the admin role
        Gate::before(function ($user, $ability) {
            return $user->hasRole('Akademik') ? true : null;
        });

        // Daftarkan Observers
        Document::observe(\App\Observers\DocumentObserver::class);

        // Rate Limiters untuk Modul Antrian
        \Illuminate\Support\Facades\RateLimiter::for('queue-status', function (\Illuminate\Http\Request $request) {
            $key = $request->cookie('device_token') ?: $request->ip();
            return \Illuminate\Cache\RateLimiting\Limit::perMinute(60)->by($key);
        });

        \Illuminate\Support\Facades\RateLimiter::for('queue-take', function (\Illuminate\Http\Request $request) {
            $key = $request->cookie('device_token') ?: $request->ip();
            return \Illuminate\Cache\RateLimiting\Limit::perMinute(10)->by($key);
        });
    }
}
