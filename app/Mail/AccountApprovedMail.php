<?php
// app/Mail/AccountApprovedMail.php

namespace App\Mail;

use App\Models\Account;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AccountApprovedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $account;

    /**
     * Create a new message instance.
     *
     * @param Account $account
     */
    public function __construct(Account $account)
    {
        $this->account = $account;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build(): static
    {
        $this->account->loadMissing(['doctor:id,account_id,full_name', 'nurse:id,account_id,full_name', 'hospital:id,account_id,full_name']);

        $name = $this->account->doctor?->full_name
            ?? $this->account->nurse?->full_name
            ?? $this->account->hospital?->full_name;

        return $this->subject('تمت الموافقة على حسابك في معافى')
            ->view('emails.account_approved', ['name' => $name]);
    }
}
