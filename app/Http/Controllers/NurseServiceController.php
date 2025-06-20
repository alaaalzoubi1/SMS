<?php

namespace App\Http\Controllers;

use App\Models\Nurse;
use App\Models\NurseService;
use App\Http\Requests\StoreNurseServiceRequest;
use App\Http\Requests\UpdateNurseServiceRequest;
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
    public function index(): JsonResponse
    {
        $nurse = Nurse::where('account_id', Auth::id())->firstOrFail();

        $services = NurseService::with('subservices')->where('nurse_id', $nurse->id)->paginate(10);
        return response()->json($services);
    }



    public function store(Request $request): JsonResponse
    {
        $nurse = Nurse::where('account_id', Auth::id())->firstOrFail();

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                new UniqueServiceNameForNurse($nurse->id),
            ],
            'price' => 'required|numeric|min:0',
        ]);

        $validated['nurse_id'] = $nurse->id;

        $service = NurseService::create($validated);

        return response()->json($service, 201);
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
            'name' => 'sometimes|string|max:255|unique:nurse_services,name,' . $service->id . ',id,nurse_id,' . $service->nurse_id,
            'price' => 'sometimes|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $validated = $validator->validated();

        $service->fill($validated);

        if ($service->isDirty()) {
            $service->save();
            return response()->json(['message' => 'Service updated successfully.',
                'service' => ["id"=> $service->id,
                    "nurse_id"=> $service->nurse_id,
                    "name"=> $service->name,
                    "price"=> $service->price,
                    "created_at"=>  $service->created_at,
                    "updated_at"=> $service->updated_at] ]);
        }

        return response()->json(['message' => 'No changes detected.']);
    }

    public function destroy($id): JsonResponse
    {
        $service = NurseService::findOrFail($id);
        $this->authorize('manageNurse', $service);
        $service->delete();
        return response()->json(['message' => 'Service deleted.']);
    }

}
