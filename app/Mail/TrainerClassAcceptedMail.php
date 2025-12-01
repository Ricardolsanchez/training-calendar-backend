<?php

namespace App\Mail;

use App\Models\Booking;
use App\Models\ClassSession;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TrainerClassAcceptedMail extends Mailable
{
    use Queueable, SerializesModels;

    public Booking $booking;
    public ClassSession $class;

    /**
     * Create a new message instance.
     */
    public function __construct(Booking $booking, ClassSession $class)
    {
        $this->booking = $booking;
        $this->class   = $class;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        return $this->subject('New Training Session Assigned')
                    ->view('emails.trainer_class_accepted');
    }
}