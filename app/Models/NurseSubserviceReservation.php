<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class NurseSubserviceReservation extends Model
{
    use SoftDeletes;
    protected $table = "nurse_subservices_reservations";
    protected $fillable = [
      'subservice_id',
      'nurse_reservation_id'
    ];

    //
}
