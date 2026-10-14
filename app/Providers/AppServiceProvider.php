<?php

namespace App\Providers;

use App\Models\ContactInfo;
use App\Models\Doctor;
use App\Models\Hospital;
use App\Models\LegalDocument;
use App\Models\Nurse;
use App\Models\SiteContent;
use App\Models\User;
use App\Observers\ContactInfoObserver;
use App\Observers\LegalDocumentObserver;
use App\Observers\PlatformStatsObserver;
use App\Observers\SiteContentObserver;
use Illuminate\Support\Facades\App;
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
        // All API messages (including Laravel's own validation errors) in
        // Arabic — see lang/ar/validation.php.
        App::setLocale(config('app.api_locale', 'ar'));

        SiteContent::observe(SiteContentObserver::class);
        ContactInfo::observe(ContactInfoObserver::class);
        LegalDocument::observe(LegalDocumentObserver::class);

        Doctor::observe(PlatformStatsObserver::class);
        Nurse::observe(PlatformStatsObserver::class);
        Hospital::observe(PlatformStatsObserver::class);
        User::observe(PlatformStatsObserver::class);
    }
}
