<?php

namespace App\Providers;

use App\Models\ContactInfo;
use App\Models\LegalDocument;
use App\Models\SiteContent;
use App\Observers\ContactInfoObserver;
use App\Observers\LegalDocumentObserver;
use App\Observers\SiteContentObserver;
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
        SiteContent::observe(SiteContentObserver::class);
        ContactInfo::observe(ContactInfoObserver::class);
        LegalDocument::observe(LegalDocumentObserver::class);
    }
}
