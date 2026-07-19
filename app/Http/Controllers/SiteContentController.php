<?php

namespace App\Http\Controllers;

use App\Models\SiteContent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class SiteContentController extends Controller
{
    public function index()
    {
        $data = Cache::rememberForever('site_content', function () {
            return SiteContent::pluck('value', 'key');
        });

        return response()->json($data);
    }
    public function storeOrUpdate(Request $request)
    {
        $data = $request->validate([
            'key'   => 'required|string|max:255',
            'value' => 'required|array',
        ]);

        SiteContent::updateOrCreate(
            ['key' => $data['key']],
            ['value' => $data['value']]
        );

        Cache::forget('site_content');

        return response()->json([
            'message' => 'Content saved successfully.'
        ]);
    }
    public function destroy($key)
    {
        SiteContent::where('key', $key)->delete();

        Cache::forget('site_content');

        return response()->json([
            'message' => 'Content deleted successfully.'
        ]);
    }


}
