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
            static $sosCount = null;
            if ($sosCount === null) {
                $sosCount = Incident::where('emergency_type', 'SOS Emergency')
                    ->where('status', 'pending')
                    ->count();
            }
            $view->with('pendingSosCount', $sosCount);

            // Same idea as the SOS badge above, for the "Incidents" nav
            // item — excludes SOS Emergency since that's already counted
            // (and shown) separately; this is specifically how many
            // regular incidents are sitting unaddressed.
            static $incidentCount = null;
            if ($incidentCount === null) {
                $incidentCount = Incident::where('emergency_type', '!=', 'SOS Emergency')
                    ->where('status', 'pending')
                    ->count();
            }
            $view->with('pendingIncidentsCount', $incidentCount);
        });
}
}