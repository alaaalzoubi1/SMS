<?php

namespace App\Policies;

use App\Models\Account;
use App\Models\DoctorReservation;
use App\Models\DoctorService;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class DoctorReservationPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, DoctorReservation $doctorReservation): bool
    {
        return false;
    }
    public function manageReservations(Account $account, DoctorReservation $reservation): bool
    {
        return $reservation->doctor->account_id === $account->id;
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
    public function update(User $user, DoctorReservation $doctorReservation): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, DoctorReservation $doctorReservation): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, DoctorReservation $doctorReservation): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, DoctorReservation $doctorReservation): bool
    {
        return false;
    }
}
