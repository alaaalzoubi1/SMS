<?php

namespace App\Http\Controllers;

use App\Models\Specialization;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SpecializationController extends Controller
{
    // Show all specializations
    public function index()
    {
        $specializations = Specialization::all()->select('id','name_en','name_ar','image');
        return response()->json($specializations);
    }

    // Show a single specialization by ID
    public function show($id)
    {
        $specialization = Specialization::findOrFail($id);
        return response()->json($specialization->makeHidden('deleted_at'));
    }

    // Create a new specialization
    public function create(Request $request)
    {
        $request->validate([
            'name_en' => 'required|string|max:255|unique:specializations,name_en',
            'name_ar' => 'required|string|max:255',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',  // Validate the image extension
        ]);

        $data = $request->only('name_en', 'name_ar');

        // Handle image upload if present
        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $imageName = Str::uuid() . '.' . $image->getClientOriginalExtension(); // Generate a unique name for the image
            $image->storeAs('specializations', $imageName, 'public'); // Store in 'public/specializations' folder
            $data['image'] = $imageName;
        }

        // Create the specialization
        $specialization = Specialization::createSpecialization($data);

        return response()->json($specialization, 201);
    }

    // Update an existing specialization
    public function update(Request $request, $id)
    {
        $request->validate([
            'name_en' => 'required|string|max:255|unique:specializations,name_en,' . $id,
            'name_ar' => 'required|string|max:255',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',  // Validate the image extension
        ]);

        $data = $request->only('name_en', 'name_ar');

        // Handle image upload if present
        if ($request->hasFile('image')) {
            // Delete the old image if exists
            $specialization = Specialization::findOrFail($id);
            if ($specialization->image) {
                Storage::disk('public')->delete('specializations/' . $specialization->image);  // Delete the old image
            }

            // Upload new image
            $image = $request->file('image');
            $imageName = Str::uuid() . '.' . $image->getClientOriginalExtension(); // Generate a unique name for the image
            $image->storeAs('specializations', $imageName, 'public'); // Store in 'public/specializations' folder
            $data['image'] = $imageName;
        }

        // Update the specialization
        $specialization = Specialization::updateSpecialization($id, $data);

        return response()->json($specialization->makeHidden('deleted_at'));
    }

    // Delete a specialization
    public function destroy($id)
    {
        $specialization = Specialization::findOrFail($id);

        // Delete the image if it exists
        if ($specialization->image) {
            Storage::disk('public')->delete('specializations/' . $specialization->image);  // Delete the image
        }

        // Delete the specialization record
        Specialization::deleteSpecialization($id);

        return response()->json(['message' => 'Specialization deleted successfully']);
    }
}
