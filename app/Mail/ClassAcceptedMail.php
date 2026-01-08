<?php

namespace App\Mail;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Support\GoogleCalendarLink;

class ClassAcceptedMail extends Mailable
{
    use Queueable, SerializesModels;

    public Booking $booking;

    /** Lista de sesiones con su link */
    public array $sessionCalendarLinks = [];

    public function __construct(Booking $booking)
    {
        $this->booking = $booking;

        // ✅ Traer sesiones (pivot)
        $booking->load('sessions');

        // ✅ Construir links por sesión
        $this->sessionCalendarLinks = collect($booking->sessions ?? [])
            ->sortBy(fn ($s) => ($s->date_iso ?? '') . '|' . ($s->time_range ?? ''))
            ->map(function ($s) use ($booking) {

                // 1) Si la sesión ya tiene calendar_url guardado, úsalo
                $url = !empty($s->calendar_url)
                    ? $s->calendar_url
                    : GoogleCalendarLink::build(
                        title: $s->title ?? $booking->name ?? 'Training Session',
                        dateIso: $s->date_iso,
                        timeRange: $s->time_range ?? '',
                        details: "Trainer: " . ($booking->trainer_name ?? '—') . "\nBooking ID: {$booking->id}\nNotes: " . ($booking->notes ?? ''),
                        location: $s->modality ?? null,
                        tz: 'America/Bogota'
                    );

                return [
                    'session_id'   => $s->id,
                    'title'        => $s->title ?? $booking->name ?? 'Training Session',
                    'date_iso'     => $s->date_iso,
                    'time_range'   => $s->time_range ?? '—',
                    'trainer_name' => $booking->trainer_name,
                    'modality'     => $s->modality ?? null,
                    'calendar_url' => $url,
                ];
            })
            ->values()
            ->toArray();
    }

    public function build()
    {
        return $this->subject('✅ Tu clase ha sido confirmada')
            ->view('emails.class_accepted')
            ->with([
                'booking' => $this->booking,
                'sessions' => $this->sessionCalendarLinks,
            ]);
    }
}
