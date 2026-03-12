<?php

namespace App\Http\Controllers;

use App\Models\Nurse;
use App\Models\NurseService;
use App\Http\Requests\StoreNurseServiceRequest;
use App\Http\Requests\UpdateNurseServiceRequest;
use App\Models\NurseServiceRequest;
use App\Models\Service;
use App\Rules\UniqueServiceNameForNurse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
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
            'services.*.certificate' => 'nullable|file|image|max:2048'
        ]);

        $serviceIds = collect($validated['services'])
            ->pluck('service_id')
            ->unique()
            ->values();

        $existingServices = NurseService::where('nurse_id', $nurse->id)
            ->whereIn('service_id', $serviceIds)
            ->pluck('service_id');

        if ($existingServices->isNotEmpty()) {
            return response()->json([
                'message' => 'بعض الخدمات مضافة مسبقاً.',
                'duplicate_service_ids' => $existingServices
            ], 422);
        }

        $pendingRequests = NurseServiceRequest::where('nurse_id', $nurse->id)
            ->whereIn('service_id', $serviceIds)
            ->where('status', 'pending')
            ->pluck('service_id');

        if ($pendingRequests->isNotEmpty()) {
            return response()->json([
                'message' => 'بعض الخدمات لديها طلب قيد المراجعة.',
                'pending_service_ids' => $pendingRequests
            ], 422);
        }

        $services = Service::whereIn('id', $serviceIds)->get()->keyBy('id');

        $directInsert = [];
        $requestInsert = [];

        foreach ($validated['services'] as $index => $item) {

            $service = $services[$item['service_id']];

            if ($service->requires_certificate) {

                if (!isset($item['certificate'])) {
                    throw ValidationException::withMessages([
                        "services.$index.certificate" => "هذه الخدمة تتطلب رفع صورة شهادة."
                    ]);
                }

                $file = $item['certificate'];

                $extension = $file->getClientOriginalExtension();

                $fileName = Str::uuid() . '.' . $extension;

                $certificatePath = $file->storeAs(
                    'service-certificates',
                    $fileName,
                    'private'
                );

                $requestInsert[] = [
                    'nurse_id' => $nurse->id,
                    'service_id' => $service->id,
                    'price' => $item['price'],
                    'certificate_path' => $certificatePath,
                    'status' => 'pending',
                ];

            } else {

                $directInsert[] = [
                    'nurse_id' => $nurse->id,
                    'service_id' => $service->id,
                    'price' => $item['price'],
                ];
            }
        }

        DB::transaction(function () use ($directInsert, $requestInsert) {

            if (!empty($directInsert)) {
                NurseService::insert($directInsert);
            }

            if (!empty($requestInsert)) {
                NurseServiceRequest::insert($requestInsert);
            }

        });

        return response()->json([
            'message' => 'تم إرسال الطلبات بنجاح.'
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
