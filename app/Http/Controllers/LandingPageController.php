<?php

namespace App\Http\Controllers;

use App\Enums\LegalDocumentType;
use App\Models\ContactInfo;
use App\Models\LegalDocument;
use App\Models\SiteContent;
use App\Services\PlatformStatsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class LandingPageController extends Controller
{
    /**
     * GET /api/landing
     *
     * Single aggregate endpoint for the React landing page: active sections
     * in render order (each with both "en" and "ar" content plus resolved
     * image URLs), the theme setting, contact links, and legal document
     * metadata (for footer links — fetch the full text from
     * GET /api/legal/{type}).
     *
     * Cached forever under "landing_page". SiteContentObserver,
     * ContactInfoObserver, and LegalDocumentObserver forget this key on
     * any create/update/delete, so the next request rebuilds it — no TTL,
     * no manual invalidation calls needed anywhere else.
     */
    public function index(): JsonResponse
    {
        $data = Cache::rememberForever('landing_page', function () {
            $sections = SiteContent::query()
                ->sections()
                ->active()
                ->ordered()
                ->get()
                ->map(fn (SiteContent $section) => [
                    'key' => $section->key,
                    'sort_order' => $section->sort_order,
                    'value' => $section->value,
                    'images' => $section->image_urls,
                ])
                ->values();

            $theme = SiteContent::query()
                ->settings()
                ->where('key', 'theme')
                ->first();

            $contactInfo = ContactInfo::all()->map(fn (ContactInfo $contact) => [
                'id' => $contact->id,
                'name' => $contact->name,
                'url' => $contact->url,
                'logo_url' => $contact->logo
                    ? \Illuminate\Support\Facades\Storage::disk('public')->url($contact->logo)
                    : null,
            ]);

            $legalDocuments = LegalDocument::query()
                ->whereIn('type', LegalDocumentType::values())
                ->get(['type', 'version', 'updated_at'])
                ->map(fn (LegalDocument $doc) => [
                    'type' => $doc->type,
                    'version' => $doc->version,
                    'updated_at' => $doc->updated_at?->toIso8601String(),
                ]);

            return [
                'theme' => $theme?->value,
                'sections' => $sections,
                'contact_info' => $contactInfo,
                'legal_documents' => $legalDocuments,
            ];
        });

        // Deliberately done OUTSIDE the landing_page cache closure above:
        // platform_stats has its own forever-cache that only gets busted by
        // PlatformStatsObserver on a new doctor/nurse/hospital/user
        // registration. If this merge happened inside the closure instead,
        // the numbers would be frozen at whatever they were the moment
        // landing_page was last rebuilt (i.e. the last time a section was
        // edited) instead of reflecting every registration.
        $data['sections'] = $this->withLiveStats($data['sections']);

        return response()->json($data);
    }

    /**
     * Swaps each stats-section item's `value` for a live count when the
     * admin tagged that item with a `metric` key matching a known stat
     * (see SiteContentSeeder). Items without a `metric` — e.g. a static
     * "Provinces covered" figure — are left exactly as the admin set them.
     */
    private function withLiveStats($sections)
    {
        $statsIndex = $sections->search(fn ($section) => $section['key'] === 'stats');

        if ($statsIndex === false) {
            return $sections;
        }

        $liveStats = PlatformStatsService::get();
        $statsSection = $sections[$statsIndex];

        foreach (['en', 'ar'] as $locale) {
            if (empty($statsSection['value'][$locale]['items'])) {
                continue;
            }

            foreach ($statsSection['value'][$locale]['items'] as &$item) {
                if (!empty($item['metric']) && isset($liveStats[$item['metric']])) {
                    $item['value'] = (string) $liveStats[$item['metric']];
                }
            }
            unset($item);
        }

        $sections[$statsIndex] = $statsSection;

        return $sections;
    }
}
