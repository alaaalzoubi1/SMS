<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Http\Requests\StoreAccountRequest;
use App\Http\Requests\UpdateAccountRequest;

class AccountController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreAccountRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Account $account)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Account $account)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateAccountRequest $request, Account $account)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy()
    {
        // Get the currently authenticated user
        $account = auth()->user();

        if (!$account) {
            return response()->json([
                'message' => 'Invalid token.'
            ], 401);  // Returning a proper error code (Unauthorized)
        }

        // Get the role of the user
        $role = $account->getRoleNames()->first();

        // Handle the account deletion based on the role
        try {
            // Check the role and delete the related data
            if ($role == 'doctor') {
                // Ensure the doctor has the related entities before trying to delete them
                if ($account->doctor) {
                    $account->doctor->doctorWorkSchedule()->delete();
                    $account->doctor->services()->delete();
                    $account->doctor()->delete();  // Delete doctor (soft delete by default)
                }
            } elseif ($role == 'nurse') {
                // Ensure the nurse has related data before deletion
                if ($account->nurse) {
                    $account->nurse->services()->delete();
                    $account->nurse()->delete();  // Soft delete nurse
                }
            } elseif ($role == 'hospital') {
                // Ensure hospital has related services and schedule before deletion
                if ($account->hospital) {
                    $account->hospital->services()->delete();
                    $account->hospital->workSchedule()->delete();
                    $account->hospital()->delete();  // Soft delete hospital
                }
            } elseif ($role == 'user') {
                // Ensure the user exists and delete it
                if ($account->user) {
                    $account->user()->delete();  // Soft delete user
                }
            }

            // Delete the account (soft delete by default)
            $account->delete();

            return response()->json([
                'message' => 'Account deleted successfully.'
            ], 200);  // Success message with HTTP 200 status code
        } catch (\Exception $e) {
            // Handle errors (e.g., if something goes wrong during the deletion)
            return response()->json([
                'message' => 'An error occurred while deleting the account. Please try again.',
                'error' => $e->getMessage()
            ], 500);  // Internal Server Error
        }
    }

}
