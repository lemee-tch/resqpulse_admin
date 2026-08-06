<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;
use App\Models\Incident;

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
        View::composer('*', function ($view) {
            static $count = null;
            if ($count === null) {
                $count = Incident::where('emergency_type', 'SOS Emergency')
                    ->where('status', 'pending')
                    ->count();
            }
            $view->with('pendingSosCount', $count);
        });
}
}
