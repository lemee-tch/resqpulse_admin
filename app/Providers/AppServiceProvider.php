<?php

namespace App\Providers;

use Illuminate\Auth\Events\Login;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\View;
use App\Models\Incident;
use App\Services\AuditLogService;

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
        // Page links ($logs->links() on the Audit Log) in Bootstrap 5
        // markup: the admin panel uses Bootstrap, not Tailwind, so
        // Laravel's default links showed giant arrows (item 60).
        Paginator::useBootstrapFive();

        // Item 67: an admin let in by "Remember me" (no password typed,
        // because their browser remembered them) goes in the Audit Log
        // too, next to "… logged in.".
        Event::listen(Login::class, function (Login $event) {
            if ($event->guard === 'web' && $event->remember && Auth::guard('web')->viaRemember()) {
                AuditLogService::log('login', $event->user->name . ' logged in with Remember me.');
            }
        });

        View::composer('*', function ($view) {
            static $sosCount = null;
            if ($sosCount === null) {
                // A declined alert keeps status "pending", so leave it out.
                $sosCount = Incident::where('emergency_type', 'SOS Emergency')
                    ->where('status', 'pending')
                    ->whereNull('declined_at')
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
                    ->whereNull('declined_at')
                    ->count();
            }
            $view->with('pendingIncidentsCount', $incidentCount);

            // The teams' reports waiting on For Review — the purple counts
            // (items 46, 49). partials/report-alert keeps them up to date.
            static $forReview = null;
            if ($forReview === null) {
                $forReview = Incident::forReviewCounts();
            }
            $view->with('forReviewIncidentsCount', $forReview['incidents']);
            $view->with('forReviewSosCount', $forReview['sos']);
        });
}
}