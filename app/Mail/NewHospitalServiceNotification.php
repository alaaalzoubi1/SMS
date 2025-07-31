<?php
namespace App\Mail;

use App\Models\Hospital;
use App\Models\Service;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class NewHospitalServiceNotification extends Mailable
{
    use Queueable, SerializesModels;

    public Hospital $hospital;
    public Service $service;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(Hospital $hospital, Service $service)
    {
        $this->hospital = $hospital;
        $this->service = $service;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->subject('New Service Added by Hospital')
            ->view('emails.new_hospital_service')
            ->with([
                'hospitalName' => $this->hospital->full_name,
                'serviceName' => $this->service->name,
            ]);
    }
}
