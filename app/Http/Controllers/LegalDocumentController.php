<?php

namespace App\Http\Controllers;

use App\Enums\LegalDocumentType;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateLegalDocumentRequest;
use App\Http\Resources\LegalDocumentResource;
use App\Models\LegalDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class LegalDocumentController extends Controller
{
    private const SUPPORTED_LOCALES = ['en', 'ar'];
    private const DEFAULT_LOCALE = 'en';

    /**
     * GET /api/legal/{type}
     *
     * Public, no auth. Language comes from the standard "Accept-Language"
     * header (e.g. "ar", "en", or full values like "ar-SA" / "en-US" — only
     * the first two letters are read). Defaults to English if the header
     * is missing or not one of the supported locales.
     */
    public function show(Request $request, string $type): JsonResponse
    {
        $documentType = LegalDocumentType::tryFrom($type);

        if (!$documentType) {
            return response()->json([
                'message' => 'Invalid document type.',
                'allowed' => LegalDocumentType::values(),
            ], 404);
        }

        $locale = $this->resolveLocale($request);
        $cacheKey = "legal_document.{$documentType->value}.{$locale}";

        $payload = Cache::rememberForever(
            $cacheKey,
            function () use ($documentType, $locale) {
                $document = LegalDocument::where('type', $documentType->value)->first();

                if (!$document) {
                    return null;
                }

                return [
                    'type' => $document->type,
                    'language' => $locale,
                    'version' => $document->version,
                    'content' => $document->getLocalizedContent($locale),
                    'updated_at' => $document->updated_at?->toIso8601String(),
                ];
            }
        );

        if (!$payload) {
            return response()->json(['message' => 'Document not found.'], 404);
        }

        return response()->json(['data' => $payload]);
    }

    /**
     * GET /api/admin/legal
     *
     * All legal document types with both languages, for an admin listing
     * screen. Types that haven't been created yet are returned as
     * `null` so the frontend still knows they exist and can render a
     * "create" form for them.
     */
    public function adminIndex(): JsonResponse
    {
        $existing = LegalDocument::query()
            ->whereIn('type', LegalDocumentType::values())
            ->get()
            ->keyBy('type');

        $documents = collect(LegalDocumentType::cases())->map(
            fn (LegalDocumentType $type) => $existing->has($type->value)
                ? new LegalDocumentResource($existing->get($type->value))
                : ['type' => $type->value, 'version' => null, 'content' => null, 'updated_by' => null, 'updated_at' => null]
        );

        return response()->json(['data' => $documents->values()]);
    }

    /**
     * GET /api/admin/legal/{type}
     *
     * Single document with both languages — what an edit form needs,
     * unlike the public show() above which resolves to one locale.
     */
    public function adminShow(string $type): JsonResponse
    {
        $documentType = LegalDocumentType::tryFrom($type);

        if (!$documentType) {
            return response()->json([
                'message' => 'Invalid document type.',
                'allowed' => LegalDocumentType::values(),
            ], 404);
        }

        $document = LegalDocument::where('type', $documentType->value)->first();

        if (!$document) {
            return response()->json(['message' => 'Document not found.'], 404);
        }

        return response()->json(['data' => new LegalDocumentResource($document)]);
    }

    /**
     * POST /api/legal/{type}
     *
     * Restricted to super_admin (see route middleware + the FormRequest's
     * authorize()). Body: { "content": { "en": "...", "ar": "..." }, "version": "1.1" }
     */
    public function update(UpdateLegalDocumentRequest $request, string $type): JsonResponse
    {
        $documentType = LegalDocumentType::tryFrom($type);

        if (!$documentType) {
            return response()->json([
                'message' => 'Invalid document type.',
                'allowed' => LegalDocumentType::values(),
            ], 404);
        }

        $document = LegalDocument::firstOrNew(['type' => $documentType->value]);
        $document->content = $request->validated('content');
        $document->version = $request->validated('version') ?? $document->version ?? '1.0';
        $document->updated_by = $request->user()->id;
        $document->save();

        foreach (self::SUPPORTED_LOCALES as $locale) {
            Cache::forget("legal_document.{$documentType->value}.{$locale}");
        }

        return response()->json([
            'message' => 'Document updated successfully.',
            'data' => new LegalDocumentResource($document),
        ]);
    }

    private function resolveLocale(Request $request): string
    {
        $header = $request->header('Accept-Language', self::DEFAULT_LOCALE);
        $locale = strtolower(substr(trim($header), 0, 2));

        return in_array($locale, self::SUPPORTED_LOCALES, true)
            ? $locale
            : self::DEFAULT_LOCALE;
    }
}
