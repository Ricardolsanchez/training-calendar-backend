<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\ClassSession;
use App\Services\GoogleMeetService;
use App\Services\GoogleScriptMailer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;

class BookingController extends Controller
{
    // ==================== CREAR RESERVA (PÚBLICO) ====================

    public function store(Request $request)
    {
        $validated = $request->validate([
            'class_id' => 'nullable|integer|exists:class_sessions,id',

            // ✅ NUEVO: sesiones seleccionadas
            'session_ids' => 'nullable|array|min:1',
            'session_ids.*' => 'integer|exists:class_sessions,id',

            'name' => 'required|string|max:255',
            'email' => 'required|email',
            'notes' => 'nullable|string',
            'trainer_name' => 'nullable|string|max:255',

            // ⛔️ YA NO obligues start/end del front
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
        ]);

        $validated['status'] = 'pending';

        // =========================================================
        // 1) Resolver sesiones que el usuario quiere reservar
        // =========================================================
        $sessions = collect();

        if (!empty($validated['session_ids'])) {
            $sessions = ClassSession::whereIn('id', $validated['session_ids'])
                ->orderBy('date_iso')
                ->orderBy('time_range')
                ->get();
        } elseif (!empty($validated['class_id'])) {
            $base = ClassSession::find($validated['class_id']);
            if ($base) {
                // si tiene group_code, reserva todo el grupo (si eso es tu regla)
                $sessions = $base->group_code
                    ? ClassSession::where('group_code', $base->group_code)
                        ->orderBy('date_iso')
                        ->orderBy('time_range')
                        ->get()
                    : collect([$base]);
            }
        }

        if ($sessions->isEmpty()) {
            return response()->json([
                'ok' => false,
                'message' => 'No sessions selected / class not found.',
            ], 422);
        }

        // =========================================================
        // 2) Calcular rango REAL desde DB
        // =========================================================
        $rangeStart = $sessions->min('date_iso');
        $rangeEnd = $sessions->max('date_iso');

        // =========================================================
        // 3) Validar cupos (si tu regla es cupo por sesión)
        // =========================================================
        foreach ($sessions as $s) {
            if ((int) $s->spots_left <= 0) {
                return response()->json([
                    'ok' => false,
                    'message' => "No seats left for session {$s->id} ({$s->date_iso}).",
                ], 422);
            }
        }

        // =========================================================
        // 4) Evitar doble reserva (mejor por grupo + email)
        // =========================================================
        $groupCode = $sessions->first()->group_code ?? null;

        $alreadyBooked = Booking::where('email', $validated['email'])
            ->where('group_code', $groupCode)
            ->where('status', '!=', 'denied')
            ->exists();

        if ($alreadyBooked) {
            return response()->json([
                'ok' => false,
                'message' => 'You already have a reservation for this class group.',
            ], 422);
        }

        // =========================================================
        // 5) Crear booking con rango REAL + group_code
        // =========================================================
        $booking = Booking::create([
            'class_id' => $sessions->first()->id,
            'group_code' => $groupCode,
            'name' => $validated['name'],
            'email' => $validated['email'],
            'notes' => $validated['notes'] ?? null,
            'trainer_name' => $validated['trainer_name'] ?? null,
            'start_date' => $rangeStart,
            'end_date' => $rangeEnd,
            'status' => 'pending',
        ]);

        // =========================================================
        // 6) Decrementar cupos por sesión seleccionada
        // =========================================================
        foreach ($sessions as $s) {
            $s->spots_left = (int) $s->spots_left - 1;
            $s->save();
        }

        // email best-effort (igual al tuyo, pero usa la sesión base)
        try {
            $base = $sessions->first();
            $html = View::make('emails.class_booked', [
                'booking' => $booking,
                'classSession' => $base,
            ])->render();

            GoogleScriptMailer::send(
                $booking->email,
                $booking->name,
                'Your class reservation has been received! ✅',
                $html,
                'Your class reservation has been received!'
            );
        } catch (\Throwable $e) {
            Log::warning('store(): error sending booked email', [
                'booking_id' => $booking->id,
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json([
            'ok' => true,
            'message' => 'Booking created',
            'booking' => $booking,
        ], 201);
    }

    // ==================== ADMIN: LISTAR RESERVAS ====================

    public function index(Request $request)
    {
        $user = $request->user();

        if (!$user || !$user->is_admin) {
            return response()->json(['ok' => false, 'message' => 'No autorizado'], 403);
        }

        $perPage = (int) $request->query('per_page', 10);
        $perPage = max(1, min(50, $perPage)); // límite de seguridad

        $paginator = Booking::with([
            'sessions' => function ($q) {
                $q->orderBy('date_iso');
                if (\Illuminate\Support\Facades\Schema::hasColumn('class_sessions', 'start_time')) {
                    $q->orderBy('start_time');
                } else {
                    $q->orderBy('time_range');
                }
            }
        ])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        // ✅ asegurar attended en el JSON
        $paginator->getCollection()->transform(function (\App\Models\Booking $b) {
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
            'bookings' => $paginator->items(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
                'has_more' => $paginator->hasMorePages(),
            ],
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

        $class = null;
        if (!empty($booking->class_id)) {
            $class = ClassSession::find($booking->class_id);
        } else {
            $class = ClassSession::where('title', $booking->name)
                ->where('date_iso', $booking->start_date)
                ->first();
        }

        if ($class) {
            $class->spots_left = (int) $class->spots_left + 1;
            $class->save();
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
        if (!$trainerName)
            return null;

        $map = [
            'Sergio Osorio' => 'seosorio@alonsoalonsolaw.com',
            'Monica Mendoza' => 'mmendoza@alonsoalonsolaw.com',
            'Alexandria Yorkman' => 'ayorkman@alonsoalonsolaw.com',
            'Kelvin Hodgson' => 'kelvinh@alonsoalonsolaw.com',
            'Rachel Rodriguez' => 'racrodriguez@alonsoalonsolaw.com',
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
                'calendar_url' => 'nullable|string|max:2048', // link manual opcional (admin)
            ]);

            $booking->status = $validated['status'];
            $booking->save();

            // ✅ Si NO aceptó, no hacemos attach ni emails
            if ($booking->status !== 'accepted') {
                return response()->json([
                    'ok' => true,
                    'message' => 'Booking Updated',
                    'booking' => $booking,
                ]);
            }

            // =========================================================
            // 1) Resolver sesiones del grupo (PRIORIDAD: booking.group_code)
            // =========================================================
            $sessions = collect();

            if (!empty($booking->group_code)) {
                $sessions = ClassSession::where('group_code', $booking->group_code)
                    ->orderBy('date_iso')
                    ->orderBy('time_range')
                    ->get();
            }

            // =========================================================
            // 2) Fallback: buscar sesión base y si tiene group_code, úsalo
            //    (también guarda booking.group_code para no perderlo)
            // =========================================================
            if ($sessions->isEmpty()) {
                $base = !empty($booking->class_id)
                    ? ClassSession::find($booking->class_id)
                    : ClassSession::where('title', $booking->name)
                        ->where('date_iso', $booking->start_date)
                        ->first();

                if ($base) {
                    if (empty($booking->group_code) && !empty($base->group_code)) {
                        $booking->group_code = $base->group_code;
                        $booking->save();
                    }

                    $sessions = !empty($base->group_code)
                        ? ClassSession::where('group_code', $base->group_code)
                            ->orderBy('date_iso')
                            ->orderBy('time_range')
                            ->get()
                        : collect([$base]);
                }
            }

            if ($sessions->isEmpty()) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Could not resolve sessions for this booking.',
                ], 422);
            }

            // =========================================================
            // 3) Adjuntar TODAS las sesiones al booking (pivot attended = null)
            // =========================================================
            $attachData = [];
            foreach ($sessions as $s) {
                $attachData[$s->id] = ['attended' => null];
            }
            $booking->sessions()->syncWithoutDetaching($attachData);

            // =========================================================
            // 4) Guardar link manual si vino (solo en 1ra sesión)
            // =========================================================
            $calendarUrlFromAdmin = $validated['calendar_url'] ?? null;
            if ($calendarUrlFromAdmin && Schema::hasColumn('class_sessions', 'calendar_url')) {
                $first = $sessions->first();
                if ($first) {
                    $first->calendar_url = $calendarUrlFromAdmin;
                    $first->save();
                }
            }

            // =========================================================
            // 5) (Opcional) Si es Online, generar Meet por sesión (best-effort)
            // =========================================================
            if (Schema::hasColumn('class_sessions', 'calendar_url')) {
                foreach ($sessions as $s) {
                    if (empty($s->calendar_url) && (empty($s->modality) || $s->modality === 'Online')) {
                        try {
                            $generatedUrl = GoogleMeetService::ensureSessionEvent($s);
                            if (!empty($generatedUrl)) {
                                $s->calendar_url = $generatedUrl;
                                $s->save();
                            }
                        } catch (\Throwable $e) {
                            Log::warning('updateStatus(): ensureSessionEvent failed', [
                                'booking_id' => $booking->id,
                                'class_session_id' => $s->id,
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }
                }
            }

            // =========================================================
            // ✅ 6) SOLO desbloqueada inicial: la PRIMERA sesión (no todas)
            // =========================================================
            $firstUnlocked = $sessions
                ->sortBy(fn($s) => ($s->date_iso ?? '') . '|' . ($s->time_range ?? ''))
                ->values()
                ->take(1);

            $sessionsForEmail = $firstUnlocked
                ->map(function ($s) use ($booking) {

                    $url = !empty($s->calendar_url)
                        ? $s->calendar_url
                        : \App\Support\GoogleCalendarLink::build(
                            title: $s->title ?? $booking->name ?? 'Training Session',
                            dateIso: $s->date_iso,
                            timeRange: $s->time_range ?? '',
                            details: "Trainer: " . ($booking->trainer_name ?? '—')
                            . "\nBooking ID: {$booking->id}\nNotes: " . ($booking->notes ?? ''),
                            location: $s->modality ?? null,
                            tz: 'America/Bogota'
                        );

                    return [
                        'session_id' => $s->id,
                        'title' => $s->title ?? $booking->name ?? 'Training Session',
                        'date_iso' => $s->date_iso,
                        'time_range' => $s->time_range ?? '—',
                        'trainer_name' => $booking->trainer_name,
                        'modality' => $s->modality ?? null,
                        'calendar_url' => $url,
                    ];
                })
                ->values()
                ->toArray();

            // =========================================================
            // 7) Email al usuario (best-effort) -> SOLO 1 sesión
            // =========================================================
            try {
                $htmlUser = View::make('emails.class_accepted', [
                    'booking' => $booking,
                    'sessions' => $sessionsForEmail,
                ])->render();

                GoogleScriptMailer::send(
                    $booking->email,
                    $booking->name,
                    '✅ Your class has been confirmed',
                    $htmlUser,
                    'Your class has been confirmed.'
                );
            } catch (\Throwable $e) {
                Log::warning('updateStatus(): user email failed', [
                    'booking_id' => $booking->id,
                    'error' => $e->getMessage(),
                ]);
            }

            // =========================================================
            // 8) Email al trainer (best-effort) -> SOLO 1 sesión (igual)
            // =========================================================
            try {
                $trainerEmail = $this->getTrainerEmail($booking->trainer_name);
                if ($trainerEmail) {
                    $htmlTrainer = View::make('emails.trainer_class_accepted', [
                        'booking' => $booking,
                        'sessions' => $sessionsForEmail,
                    ])->render();

                    GoogleScriptMailer::send(
                        $trainerEmail,
                        $booking->trainer_name ?? 'Trainer',
                        'New training session assigned',
                        $htmlTrainer,
                        'New training session assigned.'
                    );
                }
            } catch (\Throwable $e) {
                Log::warning('updateStatus(): trainer email failed', [
                    'booking_id' => $booking->id,
                    'error' => $e->getMessage(),
                ]);
            }

            // =========================================================
            // 9) Respuesta: cargar sesiones (para adminpanel)
            // =========================================================
            $booking->load('sessions');

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
    /**
     * ✅ Attendance POR SESIÓN (pivot)
     * - Guarda SIEMPRE el pivot
     * - Si attended === true => intenta desbloquear la siguiente sesión (best-effort, NO rompe el toggle)
     */
    public function setSessionAttendance(Request $request, Booking $booking, ClassSession $session)
    {
        $validated = $request->validate([
            'attended' => 'nullable|boolean',
        ]);

        $attended = array_key_exists('attended', $validated) ? $validated['attended'] : null;

        // 1) Guardar pivot (si esto falla, sí devolvemos 500)
        try {
            $booking->sessions()->syncWithoutDetaching([
                $session->id => ['attended' => $attended],
            ]);
        } catch (\Throwable $e) {
            Log::error('setSessionAttendance(): pivot sync failed', [
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

        // 2) Si no es true, listo (no desbloquea nada / no manda mail)
        if ($attended !== true) {
            return response()->json(['ok' => true]);
        }

        // 3) Best-effort: calcular siguiente + correo
        try {
            $q = $booking->sessions()->orderBy('date_iso');
            if (Schema::hasColumn('class_sessions', 'start_time')) {
                $q->orderBy('start_time');
            } else {
                $q->orderBy('time_range');
            }

            $all = $q->get()->values();

            $idx = $all->search(fn($s) => (int) $s->id === (int) $session->id);
            $next = ($idx !== false) ? $all->get($idx + 1) : null;

            if (!$next) {
                return response()->json(['ok' => true]);
            }

            // Generar/asegurar Meet (best-effort)
            try {
                if (
                    Schema::hasColumn('class_sessions', 'calendar_url') && empty($next->calendar_url)
                    && (empty($next->modality) || $next->modality === 'Online')
                ) {

                    $generatedUrl = GoogleMeetService::ensureSessionEvent($next);
                    if (!empty($generatedUrl)) {
                        $next->calendar_url = $generatedUrl;
                        $next->save();
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('setSessionAttendance(): ensureSessionEvent failed', [
                    'booking_id' => $booking->id,
                    'next_session_id' => $next->id,
                    'error' => $e->getMessage(),
                ]);
            }

            // ✅ Construir SOLO 1 item para el mismo blade (sessions[])
            $url = !empty($next->calendar_url)
                ? $next->calendar_url
                : \App\Support\GoogleCalendarLink::build(
                    title: $next->title ?? $booking->name ?? 'Training Session',
                    dateIso: $next->date_iso,
                    timeRange: $next->time_range ?? '',
                    details: "Trainer: " . ($booking->trainer_name ?? '—')
                    . "\nBooking ID: {$booking->id}\nNotes: " . ($booking->notes ?? ''),
                    location: $next->modality ?? null,
                    tz: 'America/Bogota'
                );

            $sessionsForEmail = [
                [
                    'session_id' => $next->id,
                    'title' => $next->title ?? $booking->name ?? 'Training Session',
                    'date_iso' => $next->date_iso,
                    'time_range' => $next->time_range ?? '—',
                    'trainer_name' => $booking->trainer_name,
                    'modality' => $next->modality ?? null,
                    'calendar_url' => $url,
                ]
            ];

            // ✅ Enviar email al usuario con SOLO la siguiente sesión desbloqueada
            try {
                $htmlUser = View::make('emails.class_accepted', [
                    'booking' => $booking,
                    'sessions' => $sessionsForEmail,
                ])->render();

                GoogleScriptMailer::send(
                    $booking->email,
                    $booking->name,
                    '✅ Next session unlocked',
                    $htmlUser,
                    'Your next session has been unlocked.'
                );
            } catch (\Throwable $e) {
                Log::warning('setSessionAttendance(): mail failed', [
                    'booking_id' => $booking->id,
                    'next_session_id' => $next->id,
                    'error' => $e->getMessage(),
                ]);
            }

            return response()->json(['ok' => true]);
        } catch (\Throwable $e) {
            Log::warning('setSessionAttendance(): unlock logic failed (pivot already saved)', [
                'booking_id' => $booking->id,
                'session_id' => $session->id,
                'error' => $e->getMessage(),
            ]);

            // ✅ pivot ya se guardó => no rompas el toggle
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

            if (!empty($booking->class_id)) {
                $booking->sessions()->syncWithoutDetaching([
                    (int) $booking->class_id => ['attended' => $attended]
                ]);
            }

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
                    Log::warning('updateAttendance(): mail failed', [
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
