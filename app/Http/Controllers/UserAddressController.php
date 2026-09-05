<?php

namespace App\Http\Controllers;

use App\Models\UserAddress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserAddressController extends Controller
{
    private function getUserProfile()
    {
        return auth()->user()->user;
    }

    private function findAddress($id)
    {
        $address = $this->getUserProfile()
            ->addresses()
            ->find($id);

        if (!$address) {
            abort(404, 'Address not found.');
        }

        return $address;
    }

    public function index(): JsonResponse
    {
        $addresses = $this->getUserProfile()
            ->addresses()
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'addresses' => $addresses,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'required|string|max:255',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
        ]);

        $address = $this->getUserProfile()->addresses()->create($validated);

        return response()->json([
            'message' => 'تم إضافة العنوان بنجاح.',
            'address' => $address,
        ], 201);
    }

    public function show($id): JsonResponse
    {
        return response()->json([
            'address' => $this->findAddress($id),
        ]);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'address' => 'sometimes|string|max:255',
            'latitude' => 'sometimes|numeric|between:-90,90|required_with:longitude',
            'longitude' => 'sometimes|numeric|between:-180,180|required_with:latitude',
        ]);

        $address = $this->findAddress($id);
        $address->fill($validated);

        if ($address->isDirty()) {
            $address->save();
        }

        return response()->json([
            'message' => 'تم تحديث العنوان بنجاح.',
            'address' => $address->fresh(),
        ]);
    }

    public function destroy($id): JsonResponse
    {
        $address = $this->findAddress($id);
        $address->delete();

        return response()->json([
            'message' => 'تم حذف العنوان بنجاح.',
        ]);
    }
}