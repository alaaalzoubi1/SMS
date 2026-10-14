<?php

namespace App\Http\Controllers;

use App\Models\Specialization;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Spatie\Image\Enums\Fit;
use Spatie\Image\Image;

class SpecializationController extends Controller
{
    // Show all specializations
    public function index()
    {
        $specializations = Specialization::all()->select('id','name_en','name_ar','image','image_url');
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
            $data['image'] = $this->storeSquareImage($request->file('image'));
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
            $data['image'] = $this->storeSquareImage($request->file('image'));
        }

        // Update the specialization
        $specialization = Specialization::updateSpecialization($id, $data);

        return response()->json($specialization->makeHidden('deleted_at'));
    }

    /**
     * Store the department image as a square, cropped to fill the frame.
     *
     * Images were stored as uploaded, so a wide/tall picture (or one with a
     * lot of transparent padding) was shrunk to fit the square box in the
     * apps and looked tiny. Cropping it to a 512×512 square ("cover") makes
     * it fill the whole box. SVGs and anything the image library cannot
     * process are stored unchanged.
     */
    private function storeSquareImage($image): string
    {
        $extension = strtolower($image->getClientOriginalExtension() ?: 'png');
        $imageName = Str::uuid() . '.' . $extension;

        if (!Storage::disk('public')->exists('specializations')) {
            Storage::disk('public')->makeDirectory('specializations');
        }

        if (in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
            try {
                Image::load($image->getPathname())
                    ->fit(Fit::Crop, 512, 512)
                    ->optimize()
                    ->save(Storage::disk('public')->path('specializations/' . $imageName));

                return $imageName;
            } catch (\Throwable $e) {
                Log::warning('Specialization image could not be cropped, stored as uploaded: ' . $e->getMessage());
            }
        }

        $image->storeAs('specializations', $imageName, 'public');

        return $imageName;
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
