<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\ContactInfo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ContactInfoController extends Controller
{
    public function index()
    {
        return ContactInfo::all()->map(function ($contact) {
            return [
                'id' => $contact->id,
                'name' => $contact->name,
                'url' => $contact->url,
                'logo' => $contact->logo,
                'logo_url' => $contact->logo
                    ? env('APP_URL') . '/public/storage/' . $contact->logo
                    : null,
            ];
        });
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'url'  => 'required|url',
            'logo' => 'nullable|image|mimes:png,jpg,jpeg,svg|max:2048',
        ]);

        if ($request->hasFile('logo')) {
            $file = $request->file('logo');
            $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();

            $data['logo'] = $file->storeAs(
                'contact-logos',
                $filename,
                'public'
            );
        }

        $contact = ContactInfo::create($data);

        return response()->json($contact, 201);
    }

    public function update(Request $request, $id)
    {
        $contact = ContactInfo::findOrFail($id);

        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'url'  => 'sometimes|url',
            'logo' => 'nullable|image|mimes:png,jpg,jpeg,svg|max:2048',
        ]);

        if ($request->hasFile('logo')) {

            if ($contact->logo && Storage::disk('public')->exists($contact->logo)) {
                Storage::disk('public')->delete($contact->logo);
            }

            $file = $request->file('logo');
            $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();

            $data['logo'] = $file->storeAs(
                'contact-logos',
                $filename,
                'public'
            );
        }

        $contact->update($data);

        return response()->json($contact);
    }

    public function destroy($id)
    {
        $contact = ContactInfo::findOrFail($id);

        if ($contact->logo && Storage::disk('public')->exists($contact->logo)) {
            Storage::disk('public')->delete($contact->logo);
        }

        $contact->delete();

        return response()->json([
            'message' => 'Deleted successfully'
        ]);
    }
}
