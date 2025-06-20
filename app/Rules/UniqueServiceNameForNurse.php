<?php

namespace App\Rules;

use App\Models\NurseService;
use Closure;
use Illuminate\Contracts\Validation\Rule;

class UniqueServiceNameForNurse implements Rule
{
    protected $nurse_id;

    public function __construct($nurse_id)
    {
        $this->nurse_id = $nurse_id;
    }

    public function passes($attribute, $value)
    {

        return !NurseService::where('nurse_id', $this->nurse_id)
            ->where('name', $value)
            ->exists();
    }

    public function message()
    {
        return 'The service name has already been taken for this service.';
    }
}
