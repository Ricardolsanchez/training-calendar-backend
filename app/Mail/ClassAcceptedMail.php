<?php

namespace App\Mail;

use App\Models\Booking;
use App\Models\ClassSession;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ClassAcceptedMail extends Mailable
{
    use Queueable, SerializesModels;

    public Booking $booking;
    public ClassSession $class;
    public ?string $calendarUrl; // 👈 ahora puede ser null

    /**
     * @param string|null $calendarUrl  Link que viene del admin (popup) o null
     */
    public function __construct(Booking $booking, ClassSession $class, ?string $calendarUrl = null)
    {
        $this->booking = $booking;
        $this->class = $class;

        // 1) Si el admin mandó calendarUrl desde el panel, usamos ese
        if (!empty($calendarUrl)) {
            $this->calendarUrl = $calendarUrl;
            return;
        }

        // 2) Si la clase ya tiene calendar_url guardado en BD, usarlo
        if (!empty($class->calendar_url)) {
            $this->calendarUrl = $class->calendar_url;
            return;
        }

        // 3) Si no hay nada, generamos un link de Google Calendar como fallback
        $startTime = null;
        $endTime = null;

        if ($class->time_range && str_contains($class->time_range, '-')) {
            [$startTime, $endTime] = array_map('trim', explode('-', $class->time_range));
        }

        $start = Carbon::parse($class->date_iso . ' ' . $startTime, 'America/Bogota');
        $end = Carbon::parse($class->date_iso . ' ' . $endTime, 'America/Bogota');

        // Formato para Google Calendar YYYYMMDDTHHMMSSZ
        $startStr = $start->copy()->setTimezone('UTC')->format('Ymd\THis\Z');
        $endStr = $end->copy()->setTimezone('UTC')->format('Ymd\THis\Z');

        $this->calendarUrl =
            'https://calendar.google.com/calendar/render?action=TEMPLATE'
            . '&text=' . urlencode($class->title)
            . '&dates=' . $startStr . '/' . $endStr
            . '&details=' . urlencode("Trainer: {$class->trainer_name}\nNotas: {$booking->notes}")
            . '&location=' . urlencode($class->modality === 'Online' ? 'Online' : 'Presencial');
    }

    public function build()
    {
        return $this->subject('✅ Tu clase ha sido confirmada')
            ->view('emails.class_accepted')
            ->with([
                'booking'     => $this->booking,
                'class'       => $this->class,
                'calendarUrl' => $this->calendarUrl, // puede ser null, el blade lo maneja
            ]);
    }
}
