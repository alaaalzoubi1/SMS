<?php
namespace App\Services;

use App\Models\User;
use Exception;

class ReservationUserService
{
    /**
     * @throws \Exception
     */
    public function resolveUser($userId = null, $userData = null)
    {
        if ($userId) {
            return User::findOrFail($userId);
        }

        if ($userData) {
            return User::create([
                'full_name' => $userData['full_name'],
                'age' => $userData['age'],
                'gender' => $userData['gender'],
                'account_id' => null
            ]);
        }

        throw new Exception("User information required");
    }
}
