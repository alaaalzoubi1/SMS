<?php

namespace App\Http\Controllers;

use App\Models\HospitalType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Hospital types (تصنيفات المشافي).
 *
 * Public:  GET api/hospital-types   (user-app categories with hospitals_count, and
 *                                    the type dropdown the hospital chooses from at registration)
 * Admin:   GET/POST/PUT/DELETE api/admin/hospital-types…  (CRUD + restore + trashed)
 */
class HospitalTypeController extends Controller
{
    /* ─────────────── public ─────────────── */

    /**
     * Categories for the user app and the registration form.
     * `hospitals_count` = visible (active, not suspended) hospitals of that
     * type, computed in the same query (withCount → one sub-select, no N+1).
     */
    public function publicIndex(Request $request): JsonResponse
    {
        $request->validate([
            'province_id' => 'nullable|integer|exists:provinces,id',
            'with_empty'  => 'nullable|in:0,1,true,false',
        ]);

        $provinceId = $request->input('province_id');

        $types = HospitalType::query()
            ->withCount(['hospitals' => fn ($q) => $q
                ->whereHas('account', fn ($a) => $a->where('is_approved', 'approved'))
                ->when($provinceId, fn ($h) => $h->where('hospitals.province_id', $provinceId))])
            ->when(!filter_var($request->input('with_empty', true), FILTER_VALIDATE_BOOLEAN),
                fn ($q) => $q->having('hospitals_count', '>', 0))
            ->orderBy('sort_order')
            ->orderBy('name_ar')
            ->get(['id', 'name_ar', 'name_en', 'icon', 'sort_order']);

        return response()->json(['data' => $types]);
    }

    /* ─────────────── admin CRUD ─────────────── */

    /** GET api/admin/hospital-types */
    public function index(): JsonResponse
    {
        $types = HospitalType::query()
            ->withCount('hospitals')
            ->orderBy('sort_order')
            ->orderBy('name_ar')
            ->get();

        return response()->json(['data' => $types]);
    }

    /** GET api/admin/hospital-types/trashed */
    public function trashed(): JsonResponse
    {
        return response()->json([
            'data' => HospitalType::onlyTrashed()->withCount('hospitals')->orderBy('name_ar')->get()->makeVisible('deleted_at'),
        ]);
    }

    /** GET api/admin/hospital-types/{id} */
    public function show(int $id): JsonResponse
    {
        return response()->json([
            'data' => HospitalType::withCount('hospitals')->findOrFail($id),
        ]);
    }

    /** POST api/admin/hospital-types  (multipart when sending an icon) */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate($this->rules(), $this->messages());

        $type = new HospitalType($request->only('name_ar', 'name_en', 'sort_order'));

        if ($request->hasFile('icon')) {
            $type->icon = $this->storeIcon($request->file('icon'));
        }

        $type->save();

        return response()->json([
            'message' => 'تمت إضافة نوع المشفى بنجاح.',
            'data' => $type->fresh(),
        ], 201);
    }

    /**
     * POST api/admin/hospital-types/{id}  (POST so multipart/icon works;
     * PUT is accepted too). Every field is optional.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $type = HospitalType::findOrFail($id);

        $request->validate($this->rules($type->id, true), $this->messages());

        $type->fill($request->only(array_filter(
            ['name_ar', 'name_en', 'sort_order'],
            fn ($f) => $request->has($f)
        )));

        if ($request->hasFile('icon')) {
            $this->deleteIcon($type->icon);
            $type->icon = $this->storeIcon($request->file('icon'));
        } elseif ($request->boolean('remove_icon')) {
            $this->deleteIcon($type->icon);
            $type->icon = null;
        }

        $type->save();

        return response()->json([
            'message' => 'تم تعديل نوع المشفى بنجاح.',
            'data' => $type->fresh(),
        ]);
    }

    /**
     * DELETE api/admin/hospital-types/{id}
     *
     * Soft delete. Hospitals keep their type (shown as deleted) — the type
     * just disappears from the public lists and can no longer be chosen.
     */
    public function destroy(int $id): JsonResponse
    {
        $type = HospitalType::findOrFail($id);
        $count = $type->hospitals()->withSuspended()->count();
        $type->delete();

        return response()->json([
            'message' => $count > 0
                ? "تم حذف نوع المشفى. تنبيه: يوجد {$count} مشفى مرتبط بهذا النوع، يرجى تغيير نوعها."
                : 'تم حذف نوع المشفى.',
            'linked_hospitals_count' => $count,
        ]);
    }

    /** PATCH api/admin/hospital-types/{id}/restore */
    public function restore(int $id): JsonResponse
    {
        $type = HospitalType::onlyTrashed()->findOrFail($id);
        $type->restore();

        return response()->json([
            'message' => 'تمت استعادة نوع المشفى.',
            'data' => $type,
        ]);
    }

    /* ─────────────── helpers ─────────────── */

    private function rules(?int $ignoreId = null, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return [
            'name_ar' => [$required, 'string', 'max:100',
                Rule::unique('hospital_types', 'name_ar')->ignore($ignoreId)->whereNull('deleted_at')],
            'name_en' => ['nullable', 'string', 'max:100'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'icon' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'remove_icon' => ['nullable', 'boolean'],
        ];
    }

    private function messages(): array
    {
        return [
            'name_ar.required' => 'اسم النوع بالعربية مطلوب.',
            'name_ar.unique'   => 'يوجد نوع مشفى بهذا الاسم بالفعل.',
            'icon.image'       => 'الأيقونة يجب أن تكون صورة.',
            'icon.max'         => 'حجم الأيقونة يجب ألا يتجاوز 2 ميجابايت.',
        ];
    }

    private function storeIcon($file): string
    {
        $name = Str::uuid() . '.' . strtolower($file->getClientOriginalExtension() ?: 'png');
        $file->storeAs('hospital_types', $name, 'public');

        return $name;
    }

    private function deleteIcon(?string $icon): void
    {
        if ($icon && Storage::disk('public')->exists('hospital_types/' . $icon)) {
            Storage::disk('public')->delete('hospital_types/' . $icon);
        }
    }
}
