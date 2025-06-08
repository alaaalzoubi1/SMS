<?php
namespace App\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class SendResetPasswordCode extends Notification
{
    public $code;

    public function __construct($code)
    {
        $this->code = $code;
    }

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('Password Reset Code')
            ->line("Your reset code is: {$this->code}")
            ->line('This code will expire in 5 minutes.')
            ->line('If you didn’t request this, ignore this message.')
            ->salutation('Regards, Sahtee Team');
    }
}
