<?php

namespace App\Observers;

use App\Models\ContactInfo;
use Illuminate\Support\Facades\Cache;

class ContactInfoObserver
{
    public function saved(ContactInfo $contactInfo): void
    {
        $this->flush();
    }

    public function deleted(ContactInfo $contactInfo): void
    {
        $this->flush();
    }

    private function flush(): void
    {
        Cache::forget('contact_info');
        Cache::forget('landing_page');
    }
}
