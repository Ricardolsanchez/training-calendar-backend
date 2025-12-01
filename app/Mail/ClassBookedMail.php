<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ClassBookedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $booking;
    public $classSession;

    public function __construct($booking, $classSession)
    {
        $this->booking = $booking;
        $this->classSession = $classSession;
    }

    public function build()
    {
        return $this->subject('Your class reservation has been received! ✅')
                    ->view('emails.class_booked');
    }
}