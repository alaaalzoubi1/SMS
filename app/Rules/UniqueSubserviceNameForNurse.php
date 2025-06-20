<?php
namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Facades\Auth;
use App\Models\NurseService;
use App\Models\NurseSubservice;

class UniqueSubserviceNameForNurse implements Rule
{
    protected $serviceId;

    public function __construct($serviceId)
    {
        $this->serviceId = $serviceId;
    }

    public function passes($attribute, $value)
    {

        return !NurseSubservice::where('service_id', $this->serviceId)
            ->where('name', $value)
            ->exists();
    }

    public function message()
    {
        return 'The subservice name has already been taken for this service.';
    }
}
