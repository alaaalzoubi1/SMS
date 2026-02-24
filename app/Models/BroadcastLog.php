<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BroadcastLog extends Model
{
    protected $fillable = [
        'title',
        'body',
        'groups',
        'tokens_count'
    ];

    protected $casts = [
        'groups' => 'array'
    ];
}
