<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NurseCancellation extends Model
{
    protected $fillable = [
        'reservation_id',
        'reason'
    ];
}
