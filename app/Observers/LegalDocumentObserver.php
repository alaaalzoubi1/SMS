<?php

namespace App\Observers;

use App\Models\LegalDocument;
use Illuminate\Support\Facades\Cache;

class LegalDocumentObserver
{
    private const SUPPORTED_LOCALES = ['en', 'ar'];

    public function saved(LegalDocument $legalDocument): void
    {
        $this->flush($legalDocument);
    }

    public function deleted(LegalDocument $legalDocument): void
    {
        $this->flush($legalDocument);
    }

    private function flush(LegalDocument $legalDocument): void
    {
        foreach (self::SUPPORTED_LOCALES as $locale) {
            Cache::forget("legal_document.{$legalDocument->type}.{$locale}");
        }

        Cache::forget('landing_page');
    }
}
