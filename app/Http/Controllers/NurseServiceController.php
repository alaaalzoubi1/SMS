<?php

namespace App\Http\Controllers;

use App\Models\Nurse;
use App\Models\NurseService;
use App\Http\Requests\StoreNurseServiceRequest;
use App\Http\Requests\UpdateNurseServiceRequest;
use App\Models\Service;
use App\Rules\UniqueServiceNameForNurse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class NurseServiceController extends Controller
{
    use AuthorizesRequests;
    public function index(Request $request): JsonResponse
    {
        $nurse = auth()->user()->nurse;

        $services = NurseService::query()
            ->with('service:id,service_name')
            ->where('nurse_id', $nurse->id)

            ->when($request->filled('service_name'), function ($q) use ($request) {
                $q->whereHas('service', function ($sub) use ($request) {
                    $sub->where('service_name', 'like', '%' . $request->service_name . '%');
                });
            })

            ->paginate(10);

        return response()->json($services);
    }



    public function store(Request $request): JsonResponse
    {
        $nurse = Nurse::where('account_id', Auth::id())->firstOrFail();

        $validated = $request->validate([
            'services' => 'required|array|min:1',
            'services.*.service_id' => 'required|exists:services,id',
            'services.*.price' => 'required|numeric|min:0',
        ]);

        $serviceIds = collect($validated['services'])
            ->pluck('service_id')
            ->unique()
            ->values();

        $existing = NurseService::where('nurse_id', $nurse->id)
            ->whereIn('service_id', $serviceIds)
            ->pluck('service_id');

        if ($existing->isNotEmpty()) {
            return response()->json([
                'message' => 'بعض الخدمات مضافة مسبقاً لهذا الممرض.',
                'duplicate_service_ids' => $existing
            ], 422);
        }

        $dataToInsert = collect($validated['services'])
            ->map(function ($item) use ($nurse) {
                return [
                    'nurse_id' => $nurse->id,
                    'service_id' => $item['service_id'],
                    'price' => $item['price'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            })
            ->all();

        NurseService::insert($dataToInsert);

        return response()->json([
            'message' => 'تم إضافة الخدمات بنجاح.'
        ], 201);
    }

    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function update(Request $request, $id): JsonResponse
    {
        $service = NurseService::findOrFail($id);

        $this->authorize('manageNurse', $service);

        $validator = Validator::make($request->all(), [
            'price' => 'sometimes|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $validated = $validator->validated();

        $service->fill($validated);

        if ($service->isDirty()) {
            $service->save();
            return response()->json(['message' => 'تم تحديث الخدمة بنجاح.',
                'service' => ["id"=> $service->id,
                    "nurse_id"=> $service->nurse_id,
                    "name"=> $service->name,
                    "price"=> $service->price,
                    "created_at"=>  $service->created_at,
                    "updated_at"=> $service->updated_at] ]);
        }

        return response()->json(['message' => 'لا يوجد تغييرات.']);
    }

    public function destroy($id): JsonResponse
    {
        $service = NurseService::findOrFail($id);
        $this->authorize('manageNurse', $service);
        $service->delete();
        return response()->json(['message' => 'تم حذف الخدمة بنجاح.']);
    }


    public function getFilteredServices(Request $request)
    {
        $request->validate([
            'name'  => 'string|nullable',
            'price' => 'numeric|nullable',
        ]);

        $query = NurseService::query()
            ->with([
                'service:id,service_name',
                'nurse:id,full_name,address,gender,graduation_type'
            ])
            ->whereHas('nurse', function ($q) {
                $q->Active()->Approved();
            })
            ->when($request->filled('name'), function ($q) use ($request) {
                $q->whereHas('service', function ($sub) use ($request) {
                    $sub->where('service_name', 'like', '%' . $request->name . '%');
                });
            })
            ->when($request->filled('price'), function ($q) use ($request) {
                $q->where('price', '<=', $request->price);
            });

        $services = $query
            ->select('id', 'service_id', 'price', 'nurse_id')
            ->paginate(10);

        return response()->json([
            'services' => $services
        ]);
    }
    public function getNurseServicesWithSubservices($nurseId)
    {
        // Retrieve the nurse with the services and subservices
        $nurse = Nurse::with(['services'])->find($nurseId);

        if (!$nurse) {
            return response()->json(['message' => 'Nurse not found'], 404);
        }

        // Map the response to the required structure
        $servicesData = $nurse->services->map(function ($service) {
            return [
                'service_id' => $service->id,
                'service_name' => $service->name,
                'service_price' => $service->price,
            ];
        });

        return response()->json([
            'nurse_id' => $nurse->id,
            'nurse_name' => $nurse->full_name,
            'services' => $servicesData
        ]);
    }


}
