<?php

namespace App\Http\Controllers;

use App\Models\Nurse;
use App\Models\NurseService;
use App\Models\NurseSubservice;
use App\Http\Requests\StoreNurseSubsercviceRequest;
use App\Http\Requests\UpdateNurseSubsercviceRequest;
use App\Rules\UniqueSubserviceNameForNurse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class NurseSubsercviceController extends Controller
{
    use AuthorizesRequests;
    public function index($id): JsonResponse
    {
        if (!is_numeric($id)) {
            return response()->json(['message' => 'Invalid service ID.'], 422);
        }
        $sub_service = NurseSubservice::where('service_id',$id)->paginate(10);
        return response()->json($sub_service);
    }



    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'service_id' => 'required|exists:nurse_services,id',
            'name' => [
                'required',
                'string',
                'max:255',
                new UniqueSubserviceNameForNurse($request->service_id),
            ],
            'price' => 'required|numeric|min:0',
        ]);
        $service = NurseService::findOrFail($validated['service_id']);
        $this->authorize('manageNurse',$service );
        $service = NurseSubservice::create($validated);

        return response()->json($service, 201);

    }

    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function update(Request $request, $id): JsonResponse
    {
        if (!is_numeric($id)) {
            return response()->json(['message' => 'Invalid service ID.'], 422);
        }
        $sub_service = NurseSubservice::findOrFail($id);
        $this->authorize('manageSubservice', $sub_service);

        $validator = Validator::make($request->all(), [

            'name' => [
                'required',
                'string',
                'max:255',
                new UniqueSubserviceNameForNurse($sub_service->service_id),
            ],
            'price' => 'sometimes|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $validated = $validator->validated();

        $sub_service->fill($validated);

        if ($sub_service->isDirty()) {
            $sub_service->save();
            return response()->json(['message' => 'Service updated successfully.',
                'service' => ["id"=> $sub_service->id,
                    "nurse_id"=> $sub_service->service_id,
                    "name"=> $sub_service->name,
                    "price"=> $sub_service->price,
                    "created_at"=>  $sub_service->created_at,
                    "updated_at"=> $sub_service->updated_at] ]);
        }

        return response()->json(['message' => 'No changes detected.']);
    }

    public function destroy($id): JsonResponse
    {
        $sub_service = NurseSubservice::findOrFail($id);

        $this->authorize('manageSubservice', $sub_service);
        $sub_service->delete();
        return response()->json(['message' => 'Service deleted.']);
    }
}
