<?php

namespace App\Policies;

use App\Models\Account;
use App\Models\DoctorWorkSchedule;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class DoctorWorkSchedulePolicy
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
    public function view(Account $user, DoctorWorkSchedule $schedule)
    {
        return $schedule->doctor_id === $user->doctor->id;
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
    public function update(User $user, DoctorWorkSchedule $doctorWorkSchedule): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function all(Account $user, DoctorWorkSchedule $schedule): bool
    {
        return $user->doctor && $user->doctor->id === $schedule->doctor_id;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, DoctorWorkSchedule $doctorWorkSchedule): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, DoctorWorkSchedule $doctorWorkSchedule): bool
    {
        return false;
    }
}
