<?php

namespace App\Observers;

use App\Models\SiteContent;
use Illuminate\Support\Facades\Cache;

/**
 * Site content is cached forever (Cache::rememberForever) because it barely
 * changes and is read on every single landing-page hit. Whenever the admin
 * creates, updates, or deletes a section/setting — through any code path,
 * not just the controller — we simply forget the cached entries so the next
 * read rebuilds and re-remembers them.
 */
class SiteContentObserver
{
    public function saved(SiteContent $siteContent): void
    {
        $this->flush();
    }

    public function deleted(SiteContent $siteContent): void
    {
        $this->flush();
    }

    private function flush(): void
    {
        Cache::forget('site_content');
        Cache::forget('site_content.sections');
        Cache::forget('site_content.theme');
        Cache::forget('landing_page');
    }
}
