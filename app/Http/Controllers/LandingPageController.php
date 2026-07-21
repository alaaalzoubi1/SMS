<?php

namespace App\Http\Controllers;

use App\Enums\LegalDocumentType;
use App\Models\ContactInfo;
use App\Models\LegalDocument;
use App\Models\SiteContent;
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

        return response()->json($data);
    }
}
