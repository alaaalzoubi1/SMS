<?php

namespace App\Policies;

use App\Models\Account;
use App\Models\DoctorService;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class DoctorServicePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function manage(Account $account, DoctorService $service): bool
    {
        return $service->doctor->account_id === $account->id;
    }
    public function viewAny(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, DoctorService $doctorService): bool
    {
        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, DoctorService $doctorService): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, DoctorService $doctorService): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, DoctorService $doctorService): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, DoctorService $doctorService): bool
    {
        return false;
    }
}
