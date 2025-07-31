<?php

namespace App\Http\Controllers;

use App\Models\Service;
use App\Http\Requests\StoreServiceRequest;
use App\Http\Requests\UpdateServiceRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ServiceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $services = Service::select('id','service_name')->get();
        return response()->json([
            'services' => $services
        ],200);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        $request->validate([
            'service_name' => 'required|string|max:40|unique:services,service_name'
        ]);

        DB::beginTransaction();

        try {
            $service = Service::create([
                'service_name' => $request->service_name
            ]);

            DB::commit();


            return response()->json([
                'message' => 'service added successfully',
                'service' => $service
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to add service try again later.'], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreServiceRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Service $service)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Service $service)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request,$service)
    {
        $request->validate([
            'service_name' => 'required|string|max:40|unique:services,service_name'
        ]);

        $service = Service::find($service);
        if (!$service)
            return response()->json([
                'message' => 'service not found'
            ],404);
        DB::beginTransaction();

        try {
            $service->service_name = $request->service_name;
            $service->save();

            DB::commit();


            return response()->json([
                'message' => 'service updated successfully',
                'service' => $service
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to update service try again later'], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id): JsonResponse
    {

        $service = Service::find($id);

        if (!$service) {
            return response()->json(['message' => 'Service not found'], 404);
        }

        DB::beginTransaction();
        try {
            $service->delete(); // Soft delete
            DB::commit();
            return response()->json(['message' => 'Service deleted successfully'], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to delete service', 'error' => $e->getMessage()], 500);
        }
    }
    public function trashed(): JsonResponse
    {
        $services = Service::onlyTrashed()
            ->get();
        if ($services->isEmpty()) {
            return response()->json(['message' => 'There are no deleted services'], 404);
        }

        return response()->json($services);
    }
    public function restore($id): JsonResponse
    {

        $service = Service::onlyTrashed()
            ->where('id', $id)
            ->first();

        if (!$service) {
            return response()->json(['message' => 'Trashed service not found'], 404);
        }

        DB::beginTransaction();
        try {
            $service->restore();
            DB::commit();
            return response()->json(['message' => 'Service restored successfully'], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to restore service', 'error' => $e->getMessage()], 500);
        }
    }
}
