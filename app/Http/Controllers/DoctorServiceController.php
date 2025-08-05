<?php

namespace App\Http\Controllers;
use App\Http\Requests\StoreDoctorServiceRequest;
use App\Http\Requests\UpdateDoctorServiceRequest;
use App\Models\DoctorService;
use App\Models\Doctor;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class DoctorServiceController extends Controller
{
    use AuthorizesRequests;
    public function index(): JsonResponse
    {
        $doctor = Doctor::where('account_id', Auth::id())->firstOrFail();

        $services = DoctorService::where('doctor_id', $doctor->id)->paginate(10);
        return response()->json($services);
    }

    public function trashed(): JsonResponse
    {
        $doctor = Doctor::where('account_id', Auth::id())->firstOrFail();

        $services = DoctorService::onlyTrashed()->where('doctor_id', $doctor->id)->paginate(10);

        return response()->json($services);
    }

    public function store(Request $request): JsonResponse
    {
        $doctor = Doctor::where('account_id', Auth::id())->firstOrFail();

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:doctor_services,name,NULL,id,doctor_id,' . $doctor->id,
            'price' => 'required|numeric|min:0',
            'duration_minutes' => 'required|integer|min:1',
        ]);

        $validated['doctor_id'] = $doctor->id;

        $service = DoctorService::create($validated);

        return response()->json($service, 201);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $service = DoctorService::findOrFail($id);
        $this->authorize('manage', $service);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255|unique:doctor_services,name,' . $service->id . ',id,doctor_id,' . $service->doctor_id,
            'price' => 'sometimes|numeric|min:0',
            'duration_minutes' => 'sometimes|integer|min:1',
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
                    "doctor_id"=> $service->doctor_id,
                    "name"=> $service->name,
                    "price"=> $service->price,
                    "duration_minutes"=> $service->duration_minutes,
                    "created_at"=>  $service->created_at,
                    "updated_at"=> $service->updated_at] ]);
        }

        return response()->json(['message' => 'No changes detected.']);
    }

    public function destroy($id): JsonResponse
    {
        $service = DoctorService::findOrFail($id);
        $this->authorize('manage', $service);
        $service->delete();
        return response()->json(['message' => 'Service deleted.']);
    }

    public function restore($id): JsonResponse
    {
        $service = DoctorService::onlyTrashed()->findOrFail($id);
        $this->authorize('manage', $service);
        $service->restore();
        return response()->json(['message' => 'Service restored.']);
    }
    public function getDoctorServices($doctorId)
    {
        $doctor = Doctor::find($doctorId);

        if (!$doctor) {
            return response()->json(['message' => 'Doctor not found'], 404);
        }

        // Load services with the necessary data (optional: you can join with other tables if needed)
        $services = $doctor->services()
            ->select('id','name','price')
            ->get();

        return response()->json([
            'doctor_id' => $doctor->id,
            'doctor_name' => $doctor->full_name,
            'services' => $services
        ]);
    }

}
