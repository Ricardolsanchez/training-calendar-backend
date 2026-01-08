<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\ClassSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use App\Services\GoogleScriptMailer;
use App\Services\GoogleMeetService;

class BookingController extends Controller
{
    // ==================== CREAR RESERVA (PÚBLICO) ====================

    public function store(Request $request)
    {
        $validated = $request->validate([
            'class_id' => 'nullable|integer',
            'name' => 'required|string|max:255',
            'email' => 'required|email',
            'notes' => 'nullable|string',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'trainer_name' => 'nullable|string|max:255',
            'original_start_date' => 'nullable|date',
            'original_end_date' => 'nullable|date',
            'original_training_days' => 'nullable|integer|min:0',
            'new_training_days' => 'nullable|integer|min:0',
        ]);

        $validated['status'] = 'pending';

        // buscar la sesión
        if (!empty($validated['class_id'])) {
            $class = ClassSession::find($validated['class_id']);
        } else {
            $class = ClassSession::where('title', $validated['name'])
                ->where('date_iso', $validated['start_date'])
                ->first();
        }

        if (!$class) {
            return response()->json([
                'ok' => false,
                'message' => 'Class is not available anymore.',
            ], 422);
        }

        $alreadyBooked = Booking::where('email', $validated['email'])
            ->where('name', $validated['name'])
            ->where('start_date', $validated['start_date'])
            ->exists();

        if ($alreadyBooked) {
            return response()->json([
                'ok' => false,
                'message' => 'You already have a reservation for this class.',
            ], 422);
        }

        if ((int) $class->spots_left <= 0) {
            return response()->json([
                'ok' => false,
                'message' => 'We’re sorry! We’ve run out of available seats for this class.',
            ], 422);
        }

        $bookingData = $validated;
        $bookingData['class_id'] = $validated['class_id'] ?? $class->id;

        $booking = Booking::create($bookingData);

        $class->spots_left = (int) $class->spots_left - 1;
        $class->save();

        // email "received"
        try {
            $html = View::make('emails.class_booked', [
                'booking' => $booking,
                'classSession' => $class,
            ])->render();

            GoogleScriptMailer::send(
                $booking->email,
                $booking->name,
                'Your class reservation has been received! ✅',
                $html,
                'Your class reservation has been received!'
            );
        } catch (\Throwable $e) {
            Log::error('Error enviando ClassBookedMail via GoogleScriptMailer', [
                'booking_id' => $booking->id,
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json([
            'ok' => true,
            'message' => 'Reserva creada correctamente',
            'booking' => $booking,
        ], 201);
    }

    // ==================== ADMIN: LISTAR RESERVAS ====================

    public function index(Request $request)
    {
        $user = $request->user();
        if (!$user || !$user->is_admin) {
            return response()->json([
                'ok' => false,
                'message' => 'No autorizado',
            ], 403);
        }

        // ✅ Traer sesiones + pivot
        $bookings = Booking::with([
            'sessions' => function ($q) {
                // ⚠️ ordena SOLO por columnas que existan en class_sessions
                if (Schema::hasColumn('class_sessions', 'date_iso')) {
                    $q->orderBy('date_iso');
                }
                if (Schema::hasColumn('class_sessions', 'time_range')) {
                    $q->orderBy('time_range');
                }
            }
        ])
            ->orderBy('created_at', 'desc')
            ->get();

        // Normalizar: exponer attended en cada session
        $bookings = $bookings->map(function (Booking $b) {

            // setear calendar_url base (no rompe)
            $baseClass = null;

            if (!empty($b->class_id)) {
                $baseClass = ClassSession::find($b->class_id);
            } elseif ($b->relationLoaded('sessions') && $b->sessions->count() > 0) {
                $baseClass = $b->sessions->first();
            } else {
                $baseClass = ClassSession::where('title', $b->name)
                    ->where('date_iso', $b->start_date)
                    ->first();
            }

            $b->setAttribute('calendar_url', $baseClass?->calendar_url);

            if ($b->relationLoaded('sessions')) {
                $b->sessions->transform(function ($s) {
                    $s->attended = $s->pivot->attended ?? null;
                    return $s;
                });
            }

            return $b;
        });

        return response()->json([
            'ok' => true,
            'message' => 'Listado de reservas',
            'bookings' => $bookings,
        ]);
    }

    // ==================== ADMIN: ELIMINAR RESERVA ====================

    public function destroy(string $id)
    {
        $booking = Booking::find($id);

        if (!$booking) {
            return response()->json([
                'ok' => false,
                'message' => 'Reserva no encontrada',
            ], 404);
        }

        $class = !empty($booking->class_id)
            ? ClassSession::find($booking->class_id)
            : ClassSession::where('title', $booking->name)
                ->where('date_iso', $booking->start_date)
                ->first();

        if ($class) {
            $class->spots_left = (int) $class->spots_left + 1;
            $class->save();
        }

        // limpiar pivots si existen (opcional pero sano)
        try {
            $booking->sessions()->detach();
        } catch (\Throwable $e) {
            // no bloquea el delete
        }

        $booking->delete();

        return response()->json([
            'ok' => true,
            'message' => 'Reserva eliminada correctamente',
        ]);
    }

    // ==================== ADMIN: ACTUALIZAR RESERVA ====================

    public function update(Request $request, string $id)
    {
        $booking = Booking::find($id);

        if (!$booking) {
            return response()->json([
                'ok' => false,
                'message' => 'Reserva no encontrada',
            ], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|email',
            'notes' => 'nullable|string',
            'start_date' => 'sometimes|required|date',
            'end_date' => 'sometimes|required|date|after_or_equal:start_date',
            'trainer_name' => 'nullable|string|max:255',
        ]);

        $booking->update($validated);

        return response()->json([
            'ok' => true,
            'message' => 'Reserva actualizada correctamente',
            'booking' => $booking,
        ]);
    }

    // ==================== HELPERS ====================

    private function getTrainerEmail(?string $trainerName): ?string
    {
        if (!$trainerName) return null;

        $map = [
            'Sergio Osorio' => 'seosorio@alonsoalonsolaw.com',
            'Monica Mendoza' => 'mmendoza@alonsoalonsolaw.com',
            'Kelvin Hodgson' => 'kelvinh@alonsoalonsolaw.com',
            'Edma Murillo' => 'emurillo@alonsoalonsolaw.com',
            'Dora Ramirez' => 'dramirez@alonsoalonsolaw.com',
            'Ada Perez' => 'adaperez@alonsoalonsolaw.com',
            'Josias Mendez' => 'josias@alonsoalonsolaw.com',
            'Ricardo Sanchez' => 'risanchez@alonsoalonsolaw.com',
            'Giselle Cárdenas' => 'giscardenas@alonsoalonsolaw.com',
        ];

        return $map[$trainerName] ?? null;
    }

    // ==================== ADMIN: CAMBIAR ESTADO (ACCEPT / DENY) ====================

    public function updateStatus(Request $request, string $id)
    {
        try {
            $booking = Booking::findOrFail($id);

            $validated = $request->validate([
                'status' => 'required|in:accepted,denied',
                'calendar_url' => 'nullable|string|max:2048',
            ]);

            $booking->status = $validated['status'];
            $booking->save();

            // sesión base
            $class = !empty($booking->class_id)
                ? ClassSession::find($booking->class_id)
                : ClassSession::where('title', $booking->name)
                    ->where('date_iso', $booking->start_date)
                    ->first();

            $calendarUrlFromAdmin = $validated['calendar_url'] ?? null;

            // ✅ Si aceptas: adjuntar TODAS las sesiones del grupo al booking (pivot)
            if ($booking->status === 'accepted' && $class) {
                $sessions = collect([$class]);

                if (Schema::hasColumn('class_sessions', 'group_code') && !empty($class->group_code)) {
                    $sessions = ClassSession::where('group_code', $class->group_code)
                        ->when(Schema::hasColumn('class_sessions', 'date_iso'), fn($q) => $q->orderBy('date_iso'))
                        ->when(Schema::hasColumn('class_sessions', 'time_range'), fn($q) => $q->orderBy('time_range'))
                        ->get();
                }

                $attachData = [];
                foreach ($sessions as $s) {
                    $attachData[$s->id] = ['attended' => null];
                }

                // si falla pivot, NO quieres tumbar el accept
                try {
                    $booking->sessions()->syncWithoutDetaching($attachData);
                } catch (\Throwable $e) {
                    Log::error('Error adjuntando sesiones al booking (pivot)', [
                        'booking_id' => $booking->id,
                        'error' => $e->getMessage(),
                    ]);
                }

                // el correo de confirmación se manda con la PRIMERA del grupo
                $class = $sessions->first() ?? $class;
            }

            // si admin manda link manual, guardarlo en esa sesión
            if ($class && $calendarUrlFromAdmin && Schema::hasColumn('class_sessions', 'calendar_url')) {
                $class->calendar_url = $calendarUrlFromAdmin;
                $class->save();
            }

            // asegurar link autogenerado si es online y no hay calendar_url
            if (
                $booking->status === 'accepted'
                && $class
                && Schema::hasColumn('class_sessions', 'calendar_url')
                && empty($class->calendar_url)
                && (empty($class->modality) || $class->modality === 'Online')
            ) {
                try {
                    $generatedUrl = GoogleMeetService::ensureSessionEvent($class);
                    if (!empty($generatedUrl)) {
                        $class->calendar_url = $generatedUrl;
                        $class->save();
                    }
                } catch (\Throwable $e) {
                    Log::warning('Meet generation failed (ensureSessionEvent)', [
                        'booking_id' => $booking->id,
                        'class_id' => $class->id ?? null,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // calendarUrl final
            $finalCalendarUrl = null;
            if ($class && Schema::hasColumn('class_sessions', 'calendar_url') && !empty($class->calendar_url)) {
                $finalCalendarUrl = $class->calendar_url;
            } else {
                $finalCalendarUrl = $calendarUrlFromAdmin;
            }

            // email solo cuando aceptas
            if ($booking->status === 'accepted' && $class) {
                // user
                try {
                    $htmlUser = View::make('emails.class_accepted', [
                        'booking' => $booking,
                        'class' => $class,
                        'calendarUrl' => $finalCalendarUrl,
                    ])->render();

                    GoogleScriptMailer::send(
                        $booking->email,
                        $booking->name,
                        '✅ Your class has been confirmed',
                        $htmlUser,
                        'Your class has been confirmed.'
                    );
                } catch (\Throwable $e) {
                    Log::warning('Error enviando ClassAcceptedMail (GoogleScriptMailer)', [
                        'booking_id' => $booking->id,
                        'error' => $e->getMessage(),
                    ]);
                }

                // trainer
                try {
                    $trainerEmail = $this->getTrainerEmail($class->trainer_name);
                    if ($trainerEmail) {
                        $htmlTrainer = View::make('emails.trainer_class_accepted', [
                            'booking' => $booking,
                            'class' => $class,
                            'calendarUrl' => $finalCalendarUrl,
                        ])->render();

                        GoogleScriptMailer::send(
                            $trainerEmail,
                            $class->trainer_name ?? 'Trainer',
                            'New training session assigned',
                            $htmlTrainer,
                            'New training session assigned.'
                        );
                    }
                } catch (\Throwable $e) {
                    Log::warning('Error enviando TrainerClassAcceptedMail (GoogleScriptMailer)', [
                        'booking_id' => $booking->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $booking->setAttribute('calendar_url', $finalCalendarUrl);

            return response()->json([
                'ok' => true,
                'message' => 'Booking Updated',
                'booking' => $booking,
            ]);
        } catch (\Throwable $e) {
            Log::error('Error en updateStatus', [
                'booking_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'ok' => false,
                'message' => 'Server error updating booking status.',
            ], 500);
        }
    }

    // ==================== ✅ NUEVO: ATTENDANCE POR SESIÓN (PIVOT) ====================

    public function setSessionAttendance(Request $request, Booking $booking, ClassSession $session)
    {
        // ✅ validación (si no viene attended, lo tratamos como null)
        $validated = $request->validate([
            'attended' => 'nullable|boolean',
        ]);

        $attended = array_key_exists('attended', $validated) ? $validated['attended'] : null;

        // 1) Guardar pivot (si esto falla, ahí sí devolvemos 500)
        try {
            $booking->sessions()->syncWithoutDetaching([
                $session->id => ['attended' => $attended],
            ]);
        } catch (\Throwable $e) {
            Log::error('Pivot sync failed in setSessionAttendance', [
                'booking_id' => $booking->id,
                'session_id' => $session->id,
                'attended' => $attended,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'ok' => false,
                'message' => 'Could not update attendance in DB.',
            ], 500);
        }

        // 2) si NO es true, respondemos OK y listo (esto evita 500)
        if ($attended !== true) {
            return response()->json(['ok' => true]);
        }

        // 3) Desbloqueo + correo: "best effort" (nunca tumba el toggle)
        try {
            $q = $booking->sessions();
            if (Schema::hasColumn('class_sessions', 'date_iso')) $q->orderBy('date_iso');
            if (Schema::hasColumn('class_sessions', 'time_range')) $q->orderBy('time_range');

            $all = $q->get()->values();

            $idx = $all->search(fn($s) => (int) $s->id === (int) $session->id);
            $next = ($idx !== false) ? $all->get($idx + 1) : null;

            if (!$next) {
                return response()->json(['ok' => true]);
            }

            $calendarUrl = null;

            try {
                $calendarUrl = GoogleMeetService::ensureSessionEvent($next);
            } catch (\Throwable $e) {
                Log::warning('Meet generation failed (ensureSessionEvent)', [
                    'booking_id' => $booking->id,
                    'next_session_id' => $next->id,
                    'error' => $e->getMessage(),
                ]);
            }

            try {
                $htmlUser = View::make('emails.class_accepted', [
                    'booking' => $booking,
                    'class' => $next,
                    'calendarUrl' => $calendarUrl,
                ])->render();

                GoogleScriptMailer::send(
                    $booking->email,
                    $booking->name,
                    '✅ Next session unlocked',
                    $htmlUser,
                    'Your next session has been unlocked.'
                );
            } catch (\Throwable $e) {
                Log::warning('Email send failed (GoogleScriptMailer)', [
                    'booking_id' => $booking->id,
                    'next_session_id' => $next->id,
                    'error' => $e->getMessage(),
                ]);
            }

            return response()->json(['ok' => true]);
        } catch (\Throwable $e) {
            Log::warning('Unlock logic failed after pivot was saved', [
                'booking_id' => $booking->id,
                'session_id' => $session->id,
                'error' => $e->getMessage(),
            ]);

            // ✅ pivot ya se guardó => respondemos ok para no romper el toggle
            return response()->json(['ok' => true]);
        }
    }

    // ==================== (LEGACY) ADMIN: ASISTENCIA POR BOOKING ====================

    public function updateAttendance(Request $request, int $id)
    {
        try {
            $booking = Booking::findOrFail($id);

            $validated = $request->validate([
                'attendedbutton' => 'nullable|boolean',
            ]);

            if (!array_key_exists('attendedbutton', $validated)) {
                return response()->json([
                    'ok' => true,
                    'message' => 'No changes',
                    'booking' => $booking,
                ]);
            }

            $attended = $validated['attendedbutton']; // true/false/null
            $booking->attendedbutton = $attended;
            $booking->save();

            // Mantener compatibilidad con class_id (una sola sesión)
            if (!empty($booking->class_id)) {
                try {
                    $booking->sessions()->syncWithoutDetaching([
                        (int) $booking->class_id => ['attended' => $attended]
                    ]);
                } catch (\Throwable $e) {
                    // no bloquea legacy
                }
            }

            // email solo cuando false
            if ($attended === false) {
                try {
                    $name = e($booking->name);

                    $html = "
                        <p>Hola {$name},</p>
                        <p><strong>It looks like you missed your class session!</strong> 😕</p>
                        <p>Please check our classes list and <strong>select a new available date</strong> for rescheduling.</p>
                        <p>
                          👉 Check our available dates<br>
                          <a href=\"https://training-calendar-managment.netlify.app/\" target=\"_blank\">
                            Available Classes
                          </a>
                        </p>
                        <p>Best Regards<br>Alonso & Alonso Academy</p>
                    ";

                    GoogleScriptMailer::send(
                        $booking->email,
                        $booking->name,
                        'We missed you in training',
                        $html,
                        'We missed you in training'
                    );
                } catch (\Throwable $e) {
                    Log::error('Error enviando mail via GoogleScriptMailer (updateAttendance)', [
                        'booking_id' => $booking->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            return response()->json([
                'ok' => true,
                'message' => 'Attendance updated',
                'booking' => $booking,
            ]);
        } catch (\Throwable $e) {
            Log::error('Error updating attendance', [
                'booking_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'ok' => false,
                'message' => 'Server error updating attendance.',
            ], 500);
        }
    }
}