<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Models\Rating;

class RatingController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'entity' => 'required|string|in:doctor,hospital,nurse',
            'rateable_id' => 'required|integer',
            'rating' => 'required|integer|min:1|max:5',
            'review' => 'nullable|string',
        ]);
        $map = [
            'doctor'   => \App\Models\Doctor::class,
            'hospital' => \App\Models\Hospital::class,
            'nurse'    => \App\Models\Nurse::class,
        ];

        $modelClass = $map[$request->entity];


        $model = $modelClass::findOrFail($request->rateable_id);
        $userId = auth()->user()->user->id;

        $rating = $model->addOrUpdateRating($userId, $request->rating, $request->review);
        $avg_rating = max($model->average_rating,4);

        return response()->json([
            'message' => 'Rating saved successfully.',
            'avg_rating' => $avg_rating,
        ]);
    }
    public function myRatings()
    {
        $user = auth()->user();
        $role = $user->getRoleNames()->first();

        $modelClass = match ($role) {
            'doctor'   => \App\Models\Doctor::class,
            'hospital' => \App\Models\Hospital::class,
            'nurse'    => \App\Models\Nurse::class,
            default    => null,
        };

        if (!$modelClass) {
            return response()->json(['message' => 'Unsupported role'], 400);
        }

        $userModel = $modelClass::where('account_id', $user->id)->first();

        if (!$userModel) {
            return response()->json(['message' => 'Entity not found'], 404);
        }

        // Paginate instead of get()
        $ratings = $userModel->ratings()
            ->with('user:id,full_name')
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return response()->json([
            'id' => $userModel->id,
            'avg_rating' => $userModel->avg_rating,
            'ratings_count' => $userModel->ratings_count,

            // map the current page items
            'ratings' => $ratings->getCollection()->map(function ($r) {
                return [
                    'id' => $r->id,
                    'rating' => $r->rating,
                    'review' => $r->review,
                    'created_at' => $r->created_at->toDateTimeString(),
                    'user' => [
                        'id' => $r->user->id,
                        'full_name' => $r->user->full_name,
                    ]
                ];
            }),

            // metadata needed by the frontend
            'pagination' => [
                'current_page' => $ratings->currentPage(),
                'last_page' => $ratings->lastPage(),
                'per_page' => $ratings->perPage(),
                'total' => $ratings->total(),
                'has_more' => $ratings->hasMorePages(),
            ]
        ]);
    }
    public function entityRatings(Request $request): JsonResponse
    {
        $request->validate([
            'entity' => 'required|string|in:doctor,hospital,nurse',
            'id'     => 'required|integer'
        ]);

        $modelClass = match ($request->entity) {
            'doctor'   => \App\Models\Doctor::class,
            'hospital' => \App\Models\Hospital::class,
            'nurse'    => \App\Models\Nurse::class,
        };

        $entity = $modelClass::find($request->id);

        if (!$entity) {
            return response()->json(['message' => 'Entity not found'], 404);
        }

        $ratings = $entity->ratings()
            ->with('user:id,full_name')
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return response()->json([
            'entity_id'      => $entity->id,
            'avg_rating'     => $entity->avg_rating,
            'ratings_count'  => $entity->ratings_count,
            'ratings'        => $ratings
        ]);
    }






}
