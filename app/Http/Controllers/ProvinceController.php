<?php

namespace App\Http\Controllers;

use App\Models\Province;
use Illuminate\Http\Request;

class ProvinceController extends Controller
{
    public function index()
    {
        return Province::orderBy('name_ar')->get();
    }

    public function store(Request $request)
    {
        $request->validate([
            'name_ar' => 'required|string|max:255',
            'name_en' => 'nullable|string|max:255',
        ]);

        $province = Province::create($request->only('name_ar', 'name_en'));

        return response()->json([
            'message' => 'تم إضافة المحافظة',
            'data' => $province
        ]);
    }

    public function update(Request $request)
    {
        $request->validate([
            'name_ar' => 'sometimes|string|max:255',
            'name_en' => 'sometimes|string|max:255',
            'province_id' => 'required|integer'
        ]);
        $province = Province::findOrFail($request->province_id);

        $province->update($request->only('name_ar', 'name_en'));

        return response()->json([
            'message' => 'تم تحديث المحافظة',
            'data' => $province
        ]);
    }

    public function destroy($id)
    {
        $province = Province::findOrFail($id);
        $province->delete();

        return response()->json([
            'message' => 'تم حذف المحافظة'
        ]);
    }
}

