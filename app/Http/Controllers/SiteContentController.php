<?php

namespace App\Http\Controllers;

use App\Enums\SiteContentType;
use App\Models\SiteContent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class SiteContentController extends Controller
{
    /**
     * Public, flat "key => value" map. Kept for backward compatibility with
     * any existing consumer of this endpoint. For the structured payload
     * the landing page actually renders with (ordered, active-only,
     * images, etc.), see LandingPageController.
     */
    public function index()
    {
        $data = Cache::rememberForever('site_content', function () {
            return SiteContent::pluck('value', 'key');
        });

        return response()->json($data);
    }

    /**
     * GET /api/admin/site-content
     *
     * Full detail for every row — both types (sections + settings),
     * active and inactive, every field — ordered for a landing-page
     * editor UI. Not cached: this is an admin-only, low-traffic read.
     */
    public function adminIndex()
    {
        return response()->json(
            SiteContent::query()->ordered()->get()
        );
    }

    /**
     * GET /api/admin/site-content/{key}
     */
    public function show(string $key)
    {
        $siteContent = SiteContent::where('key', $key)->first();

        if (! $siteContent) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        return response()->json($siteContent);
    }

    /**
     * PATCH /api/admin/site-content/reorder
     *
     * Body: {"order": [{"key": "hero", "sort_order": 10}, {"key": "cta", "sort_order": 20}, ...]}
     * Lets the admin drag-and-drop reorder sections in one request instead
     * of one storeOrUpdate call per section.
     */
    public function reorder(Request $request)
    {
        $data = $request->validate([
            'order' => ['required', 'array', 'min:1'],
            'order.*.key' => ['required', 'string'],
            'order.*.sort_order' => ['required', 'integer', 'min:0'],
        ]);

        // Deliberately looping with ->save() per row (not a single mass
        // ->update() query) so SiteContentObserver fires and busts the
        // cache — Eloquent mass updates skip model events entirely.
        foreach ($data['order'] as $item) {
            $siteContent = SiteContent::where('key', $item['key'])->first();
            if ($siteContent) {
                $siteContent->sort_order = $item['sort_order'];
                $siteContent->save();
            }
        }

        return response()->json(['message' => 'Order updated successfully.']);
    }

    /**
     * POST /api/admin/site-content
     *
     * Accepts either a JSON body (application/json) or a multipart form
     * (multipart/form-data) when uploading images. Because file uploads
     * require multipart, `value` is sent as a JSON-encoded string in that
     * case and decoded below.
     *
     * Body:
     *  - key         (required) e.g. "hero", "how_we_are", "theme"
     *  - type        (optional) "section" | "setting", defaults to "section"
     *  - value       (required) array, or JSON string of an array —
     *                shape convention: {"en": {...}, "ar": {...}} for
     *                sections, flat object for settings (e.g. theme colors)
     *  - images[]    (optional) new image files to attach/append
     *  - remove_images[] (optional) storage paths to remove from `images`
     *  - sort_order  (optional) integer, controls landing page ordering
     *  - is_active   (optional) boolean, hide without deleting
     */
    public function storeOrUpdate(Request $request)
    {
        $payload = $request->all();

        if ($request->has('value') && is_string($request->input('value'))) {
            $decoded = json_decode($request->input('value'), true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $payload['value'] = $decoded;
            }
        }

        $validator = Validator::make($payload, [
            'key' => ['required', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'in:'.implode(',', SiteContentType::values())],
            'value' => ['required', 'array'],
            'images' => ['nullable', 'array'],
            'images.*' => ['image', 'mimes:png,jpg,jpeg,webp,svg', 'max:4096'],
            'remove_images' => ['nullable', 'array'],
            'remove_images.*' => ['string'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data = $validator->validate();

        $siteContent = SiteContent::firstOrNew(['key' => $data['key']]);

        $existingImages = $siteContent->images ?? [];

        if (! empty($data['remove_images'])) {
            foreach ($data['remove_images'] as $path) {
                if (Storage::disk('public')->exists($path)) {
                    Storage::disk('public')->delete($path);
                }
            }
            $existingImages = array_values(array_diff($existingImages, $data['remove_images']));
        }

        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $file) {
                $filename = Str::uuid().'.'.$file->getClientOriginalExtension();
                $existingImages[] = $file->storeAs('site-content', $filename, 'public');
            }
        }

        $siteContent->type = $data['type'] ?? $siteContent->type ?? SiteContentType::SECTION->value;
        $siteContent->value = $data['value'];
        $siteContent->images = $existingImages;
        $siteContent->sort_order = $data['sort_order'] ?? $siteContent->sort_order ?? 0;
        $siteContent->is_active = array_key_exists('is_active', $data) ? $data['is_active'] : ($siteContent->is_active ?? true);
        $siteContent->save();

        return response()->json([
            'message' => 'Content saved successfully.',
            'data' => $siteContent,
        ]);
    }

    public function destroy($key)
    {
        $siteContent = SiteContent::where('key', $key)->first();

        if ($siteContent) {
            foreach ($siteContent->images ?? [] as $path) {
                if (Storage::disk('public')->exists($path)) {
                    Storage::disk('public')->delete($path);
                }
            }
            $siteContent->delete();
        }

        return response()->json([
            'message' => 'Content deleted successfully.',
        ]);
    }
}
