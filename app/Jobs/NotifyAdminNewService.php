<?php
namespace App\Jobs;

use App\Mail\NewHospitalServiceNotification;
use App\Models\Account;
use App\Models\Hospital;
use App\Models\Service;
use Illuminate\Bus\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class NotifyAdminNewService implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public Hospital $hospital;
    public Service $service;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Hospital $hospital, Service $service)
    {
        $this->hospital = $hospital;
        $this->service = $service;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(): void
    {
        $admin = Account::role('admin')->first();

        if ($admin) {
            // Send email to the admin about the new service
            Mail::to($admin->email)->send(new NewHospitalServiceNotification($this->hospital, $this->service));
        } else {
            // Log if no admin is found
            Log::error('No admin found for notification');
        }
    }
}
