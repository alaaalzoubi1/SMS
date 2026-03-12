<?php

namespace App\Http\Controllers;

use App\Jobs\SendFirebaseNotificationJob;
use App\Models\NurseService;
use App\Models\NurseServiceRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class NurseServiceRequestController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'status' => 'sometimes|in:pending,rejected,approved'
        ]);
        $query = NurseServiceRequest::with(['nurse','service']);

        if ($request->status) {
            $query->where('status', $request->status);
        }

        $requests = $query->latest()->paginate(20);

        return response()->json($requests);
    }
    public function approve(Request $request, $id)
    {
        $request->validate([
            'note' => 'nullable|string|max:255'
        ]);

        $serviceRequest = NurseServiceRequest::with('nurse.account','service')->findOrFail($id);

        if ($serviceRequest->status !== 'pending') {
            return response()->json([
                'message' => 'هذا الطلب تمت معالجته مسبقاً'
            ],403);
        }

        DB::transaction(function () use ($request, $serviceRequest) {
            NurseService::create([
                'nurse_id' => $serviceRequest->nurse_id,
                'service_id' => $serviceRequest->service_id,
                'price' => $serviceRequest->price
            ]);

            $serviceRequest->update([
                'status' => 'approved',
                'admin_note' => $request->note
            ]);
        });

        $nurse = $serviceRequest->nurse;

        if ($nurse && $nurse->account && $nurse->account->fcm_token) {
            $body = sprintf(
                "تمت الموافقة على طلبك لإضافة خدمة \"%s\".",
                $serviceRequest->service->service_name ?? "خدمة"
            );

            SendFirebaseNotificationJob::dispatch(
                $nurse->account->fcm_token,
                "طلب خدمة تمت الموافقة عليه",
                $body
            );
        }

        return response()->json([
            'message' => 'تمت الموافقة على الطلب'
        ]);
    }
    public function reject(Request $request, $id)
    {
        $request->validate([
            'note' => 'nullable|string|max:255'
        ]);

        $serviceRequest = NurseServiceRequest::with('nurse.account','service')->findOrFail($id);

        if ($serviceRequest->status !== 'pending') {
            return response()->json([
                'message' => 'هذا الطلب تمت معالجته مسبقاً'
            ],403);
        }

        $serviceRequest->update([
            'status' => 'rejected',
            'admin_note' => $request->note
        ]);

        $nurse = $serviceRequest->nurse;

        if ($nurse && $nurse->account && $nurse->account->fcm_token) {
            $body = sprintf(
                "تم رفض طلبك لإضافة خدمة \"%s\".",
                $serviceRequest->service->service_name ?? "خدمة"
            );

            SendFirebaseNotificationJob::dispatch(
                $nurse->account->fcm_token,
                "طلب خدمة تم رفضه",
                $body
            );
        }

        return response()->json([
            'message' => 'تم رفض الطلب'
        ]);
    }
    public function showCertificate($id)
    {
        $request = NurseServiceRequest::findOrFail($id);

        if (!$request->certificate_path) {
            return response()->json([
                'message' => 'certificate not found'
            ], 404);
        }

        if (!Storage::disk('private')->exists($request->certificate_path)) {
            return response()->json([
                'message' => 'file missing'
            ], 404);
        }

        return Storage::disk('private')->response($request->certificate_path);
    }
}
