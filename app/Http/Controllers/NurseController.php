<?php

namespace App\Http\Controllers;

use App\Http\Requests\NurseFilterRequest;
use App\Models\Nurse;
use App\Http\Requests\StoreNurseRequest;
use App\Http\Requests\UpdateNurseRequest;
use App\Models\NurseReservation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use MatanYadaev\EloquentSpatial\Objects\Point;

class NurseController extends Controller
{
    /**
     * Display a listing of the resource.
     */

    public function listForUsers(NurseFilterRequest $request): JsonResponse
    {
        $query = Nurse::query()
            ->with(['services.service', 'province'])
            ->Approved()
            ->Active()
            ->when($request->filled('gender'),
                fn ($q) => $q->where('gender', $request->gender)
            )
            ->when($request->filled('graduation_type'),
                fn ($q) => $q->where('graduation_type', $request->graduation_type)
            )
            ->when($request->filled('address'),
                fn ($q) => $q->where('address', 'like', '%' . $request->address . '%')
            )
            ->when($request->filled('full_name'),
                fn ($q) => $q->where('full_name', 'like', '%' . $request->full_name . '%')
            )
            ->when($request->filled('service_name'), function ($q) use ($request) {
                $q->whereHas('services.service', function ($sub) use ($request) {
                    $sub->where('service_name', 'like', '%' . $request->service_name . '%');
                });
            })

            ->when(
                $request->filled('latitude') && $request->filled('longitude'),
                function ($q) use ($request) {
                    $point = new Point($request->latitude, $request->longitude);

                    $q->withDistanceSphere('location', $point, 'distance_meters')
                        ->orderByDistanceSphere('location', $point, 'asc');
                }
            );

        $nurses = $query->paginate(10);

        $nurses->getCollection()->transform(function ($nurse) {

            return [
                'id' => $nurse->id,
                'full_name' => $nurse->full_name,
                'address' => $nurse->address,
                'graduation_type' => $nurse->graduation_type,
                'age' => $nurse->age,
                'gender' => $nurse->gender,
                'profile_description' => $nurse->profile_description,
                'location' => $nurse->location,
                'services' => $nurse->services->map(fn ($service) => [
                    'id' => $service->id,
                    'name' => $service->name,
                    'price' => $service->price,
                ]),
                'avg_rating' => max(4, $nurse->avg_rating),
                'distance_meters' => $nurse->distance_meters ?? null,
                'profile_image' => $nurse->profile_image_path,
                'province' => $nurse->province,
            ];
        });

        return response()->json($nurses);
    }

    public function getNearestNurses(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
        ], [
            'latitude.required' => 'يجب إدخال خط العرض.',
            'latitude.numeric' => 'خط العرض يجب أن يكون رقمًا.',
            'latitude.between' => 'قيمة خط العرض يجب أن تكون بين -90 و 90.',
            'longitude.required' => 'يجب إدخال خط الطول.',
            'longitude.numeric' => 'خط الطول يجب أن يكون رقمًا.',
            'longitude.between' => 'قيمة خط الطول يجب أن تكون بين -180 و 180.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors(),
            ], 422);
        }
        $latitude = $request->input('latitude');
        $longitude = $request->input('longitude');

        $point = new Point($latitude, $longitude);

        $nurses = Nurse::query()
            ->Active()
            ->Approved()
            ->whereNotNull('location')
            ->withDistanceSphere('location', $point, 'distance_meters')
            ->orderBy('distance_meters')
            ->limit(10)
            ->get()
            ->transform(function ($nurse){
                $nurse->avg_rating = max(4,$nurse->avg_rating);
                return $nurse;
            })
            ->makeHidden(['license_image_path', 'deleted_at', 'created_at', 'updated_at']);
        return response()->json([
            'nurses' => $nurses,
        ]);
    }
    public function activate(): JsonResponse
    {
        $nurse = auth()->user()->nurse;
        $nurse->is_active = !$nurse->is_active;
        $nurse->save();
        return response()->json([
            'is_active' => $nurse->is_active
        ]);
    }
    public function refreshLocation(Request $request)
    {
         $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
        ]);
        $nurse = auth()->user()->nurse;
        $location = new Point($request->latitude,$request->longitude);
        $nurse->update(['location' => $location]);
        return response()->json([
            'message' => 'تم تحديث الموقع بنجاح'
        ]);
    }
    public function statistics(Request $request): JsonResponse
    {
        $request->validate([
            'range' => 'nullable|in:today,month,year,custom',
            'from'  => 'required_if:range,custom|date',
            'to'    => 'required_if:range,custom|date|after_or_equal:from',
        ]);

        $nurse = Nurse::where('account_id', auth()->id())->firstOrFail();

        $range = $request->input('range', 'today');

        switch ($range) {
            case 'today':
                $from = now()->startOfDay();
                $to   = now()->endOfDay();
                break;

            case 'month':
                $from = now()->startOfMonth();
                $to   = now()->endOfMonth();
                break;

            case 'year':
                $from = now()->startOfYear();
                $to   = now()->endOfYear();
                break;

            case 'custom':
                $from = $request->from;
                $to   = $request->to;
                break;

            default:
                $from = now()->startOfDay();
                $to   = now()->endOfDay();
        }

        $stats = NurseReservation::where('nurse_id', $nurse->id)
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw("
            COUNT(*) as total_reservations,
            SUM(status = 'pending') as pending_count,
            SUM(status = 'accepted') as accepted_count,
            SUM(status = 'completed') as completed_count,
            SUM(status = 'rejected') as rejected_count,
            SUM(status = 'cancelled') as cancelled_count,
            SUM(CASE WHEN status = 'completed' THEN price ELSE 0 END) as total_revenue
        ")
            ->first();

        return response()->json([
            'range' => $range,
            'from'  => $from,
            'to'    => $to,
            'data'  => $stats,
            'avg_rate' => $nurse->avg_rating,
            'ratings_count' => $nurse->ratings_count
        ]);
    }
}
